<?php declare(strict_types=1);

namespace App\Application\Booking\CreateBooking;

use App\Domain\Booking\Entities\Appointment;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
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
 * CreateAppointmentUseCase â¬ Caso de Uso de Aplicación
 *
 * Orquesta la creación de una nueva cita con protección estricta anti-overbooking:
 *   1. Validación perimetral
 *   2. Bloqueo de transaccionalidad (REPEATABLE READ) y bloqueo pesimista en mallas horarias
 *   3. Verificación de excepciones de agenda y solapamientos
 *   4. Persistencia y auditoría forense
 */
class CreateAppointmentUseCase
{
    private AppointmentRepositoryInterface $appointmentRepo;
    private ServiceRepositoryInterface     $serviceRepo;
    private StaffingRepositoryInterface    $staffingRepo;
    private AuditService                   $auditService;
    private PDO                            $pdo;

    public function __construct(
        AppointmentRepositoryInterface $appointmentRepo,
        ServiceRepositoryInterface     $serviceRepo,
        StaffingRepositoryInterface    $staffingRepo,
        AuditService                   $auditService,
        PDO                            $pdo
    ) {
        $this->appointmentRepo = $appointmentRepo;
        $this->serviceRepo     = $serviceRepo;
        $this->staffingRepo    = $staffingRepo;
        $this->auditService    = $auditService;
        $this->pdo             = $pdo;
    }

    /**
     * Ejecuta la creación de una cita de forma atómica y segura contra concurrencia.
     *
     * @param CreateAppointmentDTO $dto
     * @return array Resultado con ID y datos de la cita creada
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

            return [
                'appointment_id' => $appointmentId,
                'service_name'   => $service['name'] ?? 'Servicio',
                'scheduled_date' => $scheduledTs->format('Y-m-d'),
                'scheduled_time' => $scheduledTs->format('H:i'),
                'status'         => 'PENDING',
                'total_price'    => $basePrice,
                'final_price'    => $basePrice
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
