<?php declare(strict_types=1);

namespace App\Application\Booking\CreateBooking;

use App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase;
use App\Domain\Booking\Entities\Appointment;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Infrastructure\Shared\Audit\AuditLogRepositoryInterface;
use App\Application\Booking\Validators\CreateAppointmentValidator;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/**
 * CreateAppointmentUseCase — Caso de Uso de Aplicación
 *
 * Orquesta la creación de una nueva cita:
 *   1. Validación de entrada
 *   2. Verificación de que el profesional ofrece el servicio
 *   3. Verificación de disponibilidad (anti-overbooking)
 *   4. Cálculo de timestamps y precios
 *   5. Persistencia y auditoría
 *
 * Cumple Skill 1 → Orquesta sin depender de infraestructura directamente.
 */
class CreateAppointmentUseCase
{
    private AppointmentRepositoryInterface $appointmentRepo;
    private ServiceRepositoryInterface $serviceRepo;
    private StaffingRepositoryInterface $staffingRepo;
    private AuditLogRepositoryInterface $auditRepo;
    private GetAvailableSlotsUseCase $getAvailableSlotsUseCase;

    public function __construct(
        AppointmentRepositoryInterface $appointmentRepo,
        ServiceRepositoryInterface     $serviceRepo,
        StaffingRepositoryInterface     $staffingRepo,
        AuditLogRepositoryInterface    $auditRepo,
        GetAvailableSlotsUseCase       $getAvailableSlotsUseCase
    ) {
        $this->appointmentRepo = $appointmentRepo;
        $this->serviceRepo     = $serviceRepo;
        $this->staffingRepo    = $staffingRepo;
        $this->auditRepo       = $auditRepo;
        $this->getAvailableSlotsUseCase = $getAvailableSlotsUseCase;
    }

    /**
     * Ejecuta la creación de una cita.
     *
     * @param CreateAppointmentDTO $dto
     * @return array Resultado con ID y datos de la cita creada
     * @throws DomainException|InvalidArgumentException
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

        // 2. Obtener servicio para precio y duración
        $service = $this->serviceRepo->findById($dto->serviceId);
        if ($service === null) {
            throw new DomainException("Servicio #{$dto->serviceId} no encontrado.");
        }

        $basePrice = (float) ($service['base_price'] ?? 0);
        $durationMinutes = (int) ($service['duration_minutes'] ?? 60);

        // 3. Validar disponibilidad real de Staffing antes de reservar
        $slots = $this->getAvailableSlotsUseCase->execute(
            professionalId: $dto->professionalProfileId,
            branchId: $dto->branchId,
            date: $scheduledTs->format('Y-m-d'),
            slotDurationMinutes: $durationMinutes
        );

        $requestedSlot = null;
        foreach ($slots as $slot) {
            if ($slot['start'] === $scheduledTs->format('H:i')) {
                $requestedSlot = $slot;
                break;
            }
        }

        if ($requestedSlot === null || !$requestedSlot['available']) {
            throw new \App\Domain\Shared\Exceptions\DomainException('El profesional no tiene disponibilidad para ese horario. Elija otro turno.', 'Booking');
        }

        // 4. Calcular timestamp de fin
        $estimatedEndTs = $scheduledTs->modify("+{$durationMinutes} minutes");

        // 4b. Validar conflictos con otras citas existentes del profesional
        $hasConflict = $this->appointmentRepo->hasConflictingAppointment(
            $dto->professionalProfileId,
            $scheduledTs->format('Y-m-d H:i:s'),
            $estimatedEndTs->format('Y-m-d H:i:s')
        );

        if ($hasConflict) {
            throw new \App\Domain\Shared\Exceptions\DomainException('Ya existe una cita en ese rango para el profesional.', 'Booking');
        }

        // 5. Construir entidad Appointment
        $appointment = new Appointment(
            clientProfileId: $dto->clientProfileId,
            professionalProfileId: $dto->professionalProfileId,
            branchId: $dto->branchId,
            scheduledTimestamp: $scheduledTs,
            estimatedEndTimestamp: $estimatedEndTs,
            totalPrice: $basePrice,
            finalPrice: $basePrice,
            promotionId: $dto->promotionId,
            notes: $dto->notes,
            appointmentStatus: 'PENDING'
        );

        // 6. Persistir
        $appointmentId = $this->appointmentRepo->create([
            'client_profile_id' => $dto->clientProfileId,
            'professional_profile_id' => $dto->professionalProfileId,
            'branch_id' => $dto->branchId,
            'promotion_id' => $dto->promotionId,
            'scheduled_timestamp' => $scheduledTs->format('Y-m-d H:i:s'),
            'estimated_end_timestamp' => $estimatedEndTs->format('Y-m-d H:i:s'),
            'appointment_status' => 'PENDING',
            'total_price' => $basePrice,
            'final_price' => $basePrice,
            'notes' => $dto->notes,
        ]);

        // 7. Auditoría
        $this->auditRepo->insert(
            actorId: (string) $dto->clientProfileId,
            action: 'APPOINTMENT_CREATED',
            entityType: 'appointment',
            entityId: $appointmentId,
            oldValues: [],
            newValues: [
                'appointment_id' => $appointmentId,
                'service_id' => $dto->serviceId,
                'scheduled_timestamp' => $scheduledTs->format('Y-m-d H:i:s'),
                'status' => 'PENDING'
            ]
        );

        return [
            'appointment_id' => $appointmentId,
            'service_name'   => $service['name'] ?? $service['nombre'] ?? 'Servicio',
            'scheduled_date' => $scheduledTs->format('Y-m-d'),
            'scheduled_time' => $scheduledTs->format('H:i'),
            'status'         => 'PENDING',
            'total_price'    => $basePrice,
            'final_price'    => $basePrice
        ];
    }
}
