<?php declare(strict_types=1);

namespace App\Application\Booking\CreateBooking;

use App\Domain\Booking\Entities\Appointment;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\NotificationQueueRepositoryInterface;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Application\Shared\Audit\AuditService;
use App\Application\Booking\Validators\CreateAppointmentValidator;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use PDOException;

/**
 * CreateAppointmentUseCase — Caso de Uso de Aplicación
 *
 * Orquesta la creación de una nueva cita con protección estricta anti-overbooking
 * y despacho desacoplado de confirmación omnicanal:
 *   1. Validación perimetral
 *   2. Bloqueo de transaccionalidad (REPEATABLE READ) y bloqueo pesimista en mallas horarias
 *   3. Verificación de excepciones de agenda y solapamientos
 *   4. Persistencia y auditoría forense
 *   5. Encolamiento de notificación de confirmación de cita (Skill 5 y MEDIO-03)
 */
class CreateAppointmentUseCase
{
    private AppointmentRepositoryInterface $appointmentRepo;
    private ServiceRepositoryInterface     $serviceRepo;
    private StaffingRepositoryInterface    $staffingRepo;
    private AuditService                   $auditService;
    private PDO                            $pdo;
    private ?NotificationQueueRepositoryInterface $notificationQueueRepo;

    public function __construct(
        AppointmentRepositoryInterface $appointmentRepo,
        ServiceRepositoryInterface     $serviceRepo,
        StaffingRepositoryInterface    $staffingRepo,
        AuditService                   $auditService,
        PDO                            $pdo,
        ?NotificationQueueRepositoryInterface $notificationQueueRepo = null
    ) {
        $this->appointmentRepo = $appointmentRepo;
        $this->serviceRepo     = $serviceRepo;
        $this->staffingRepo    = $staffingRepo;
        $this->auditService    = $auditService;
        $this->pdo             = $pdo;
        $this->notificationQueueRepo = $notificationQueueRepo;
    }

    /**
     * Ejecuta la creación de una cita de forma atómica y segura contra concurrencia.
     *
     * @param CreateAppointmentDTO $dto
     * @return array Resultado con ID, datos de la cita y feedback en tiempo real
     * @throws DomainException|ConflictException
     */
    public function execute(CreateAppointmentDTO $dto): array
    {
        // 1. Validación perimetral
        CreateAppointmentValidator::validate(
            $dto->serviceId,
            $dto->professionalProfileId,
            $dto->branchId,
            $dto->scheduledDate,
            $dto->scheduledTime
        );

        $tz = new DateTimeZone('America/Bogota');
        $scheduledTs = new DateTimeImmutable($dto->scheduledDate . ' ' . $dto->scheduledTime, $tz);

        // Determinar el día de la semana (1 = Lunes, ..., 7 = Domingo)
        $dayOfWeek = (int) $scheduledTs->format('N');

        // 2. Obtener servicio para precio y duración
        $service = $this->serviceRepo->findById($dto->serviceId);
        if ($service === null) {
            throw new DomainException("Servicio #{$dto->serviceId} no encontrado.");
        }

        $basePrice = (float) ($service['base_price'] ?? 0);
        $durationMinutes = (int) ($service['duration_minutes'] ?? 60);

        $estimatedEndTs = $scheduledTs->modify("+{$durationMinutes} minutes");

        // 3. Iniciar Transacción y Bloqueo Pesimista (Skill 2)
        try {
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();

            // Bloquear la malla horaria del profesional para este día de la semana
            $this->staffingRepo->lockProfessionalWorkSchedule(
                $dto->professionalProfileId,
                $dto->branchId,
                $dayOfWeek
            );

            // Verificar si hay excepciones de agenda (bloqueos o vacaciones) en el rango
            $exceptions = $this->staffingRepo->getExceptionsInTimeRange(
                $dto->professionalProfileId,
                $scheduledTs->format('Y-m-d H:i:s'),
                $estimatedEndTs->format('Y-m-d H:i:s')
            );

            if (!empty($exceptions)) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new DomainException("El profesional tiene una excepción o bloqueo de agenda en el horario solicitado.");
            }

            // Verificar si ya existe otra cita solapada (Anti-Overbooking estricto)
            $hasOverlap = $this->appointmentRepo->hasOverlappingAppointment(
                $dto->professionalProfileId,
                $scheduledTs->format('Y-m-d H:i:s'),
                $estimatedEndTs->format('Y-m-d H:i:s')
            );

            if ($hasOverlap) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new ConflictException("El horario seleccionado ya se encuentra ocupado por otra cita (Overbooking evitado).", 409);
            }

            // 4. Construir entidad Appointment
            $appointment = new Appointment(
                clientProfileId: $dto->clientProfileId,
                professionalProfileId: $dto->professionalProfileId,
                serviceId: $dto->serviceId,
                branchId: $dto->branchId,
                scheduledTimestamp: $scheduledTs,
                estimatedEndTimestamp: $estimatedEndTs,
                totalPrice: $basePrice,
                finalPrice: $basePrice,
                promotionId: $dto->promotionId,
                notes: $dto->notes,
                appointmentStatus: 'PENDING'
            );

            // 5. Persistir
            $appointmentId = $this->appointmentRepo->save($appointment);

            $this->pdo->commit();

            // 6. Auditoría
            $this->auditService->recordCreation(
                actorId: $dto->clientProfileId,
                action: 'APPOINTMENT_CREATED',
                entityType: 'appointment',
                entityId: $appointmentId,
                newValues: [
                    'appointment_id' => $appointmentId,
                    'service_id' => $dto->serviceId,
                    'scheduled_timestamp' => $scheduledTs->format('Y-m-d H:i:s'),
                    'status' => 'PENDING'
                ]
            );

            // 7. Enriquecer datos para feedback en tiempo real (MEDIO-03)
            $clientName = 'Cliente';
            $clientPhone = '';
            $clientEmail = '';
            $profName = 'Especialista Asignado';
            $branchName = 'Carolina Mora Estética y SPA';

            // Obtener datos del cliente
            $stmtClient = $this->pdo->prepare("SELECT first_name, last_name, auth_phone, email FROM user WHERE user_id = :uid LIMIT 1");
            $stmtClient->bindValue(':uid', $dto->clientProfileId, PDO::PARAM_INT);
            $stmtClient->execute();
            $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
            if ($clientRow) {
                $clientName = trim(($clientRow['first_name'] ?? '') . ' ' . ($clientRow['last_name'] ?? '')) ?: 'Cliente';
                $clientPhone = (string) ($clientRow['auth_phone'] ?? '');
                $clientEmail = (string) ($clientRow['email'] ?? '');
            }

            // Obtener nombre del profesional
            $stmtProf = $this->pdo->prepare("SELECT first_name, last_name FROM user WHERE user_id = :pid LIMIT 1");
            $stmtProf->bindValue(':pid', $dto->professionalProfileId, PDO::PARAM_INT);
            $stmtProf->execute();
            $profRow = $stmtProf->fetch(PDO::FETCH_ASSOC);
            if ($profRow) {
                $profName = trim(($profRow['first_name'] ?? '') . ' ' . ($profRow['last_name'] ?? '')) ?: 'Especialista Asignado';
            }

            // Obtener nombre de la sede
            $stmtBranch = $this->pdo->prepare("SELECT branch_name FROM branch WHERE branch_id = :bid LIMIT 1");
            $stmtBranch->bindValue(':bid', $dto->branchId, PDO::PARAM_INT);
            $stmtBranch->execute();
            $branchRow = $stmtBranch->fetch(PDO::FETCH_ASSOC);
            if ($branchRow) {
                $branchName = (string) ($branchRow['branch_name'] ?? $branchName);
            }

            // 8. Encolar notificación WhatsApp si el repositorio está disponible y el cliente tiene teléfono
            $notificationEnqueued = false;
            if ($this->notificationQueueRepo !== null && $clientPhone !== '') {
                try {
                    $this->notificationQueueRepo->enqueue(
                        templateId: 1,
                        recipientPhone: $clientPhone,
                        dynamicParameters: [
                            'client_name'       => $clientName,
                            'service_name'      => $service['name'] ?? 'Servicio',
                            'date'              => $scheduledTs->format('Y-m-d'),
                            'time'              => $scheduledTs->format('H:i'),
                            'professional_name' => $profName,
                            'appointment_id'    => $appointmentId,
                            'branch_name'       => $branchName
                        ]
                    );
                    $notificationEnqueued = true;
                } catch (\Throwable) {
                    // Silenciosamente continuar para no interrumpir la creación de la cita
                    $notificationEnqueued = false;
                }
            }

            // 9. Generar link directo de confirmación WhatsApp
            $adminPhone = preg_replace('/\D/', '', (string)($_ENV['BUSINESS_WHATSAPP_PHONE'] ?? '573001234567'));
            $dateFormatted = $scheduledTs->format('d/m/Y');
            $timeFormatted = $scheduledTs->format('h:i A');
            $serviceName = $service['name'] ?? 'Servicio';
            $msgText = "¡Hola! Acabo de agendar mi cita #{$appointmentId} para *{$serviceName}* el día {$dateFormatted} a las {$timeFormatted} con {$profName} en Carolina Mora Estética. Por favor confirmar mi reserva.";
            $whatsappDirectUrl = "https://wa.me/{$adminPhone}?text=" . rawurlencode($msgText);

            return [
                'appointment_id'          => $appointmentId,
                'service_name'            => $serviceName,
                'professional_name'       => $profName,
                'client_name'             => $clientName,
                'client_email'            => $clientEmail,
                'client_phone'            => $clientPhone,
                'scheduled_date'          => $scheduledTs->format('Y-m-d'),
                'scheduled_time'          => $scheduledTs->format('H:i'),
                'fecha_formateada'        => $scheduledTs->format('d \d\e F \d\e Y'),
                'branch_name'             => $branchName,
                'status'                  => 'PENDING',
                'total_price'             => $basePrice,
                'final_price'             => $basePrice,
                'notification_enqueued'   => $notificationEnqueued,
                'whatsapp_direct_url'     => $whatsappDirectUrl
            ];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '40001' || $e->getCode() === 'HY000') {
                throw new ConflictException("Conflicto de concurrencia detectado al reservar el turno. Intente nuevamente.", 409);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
