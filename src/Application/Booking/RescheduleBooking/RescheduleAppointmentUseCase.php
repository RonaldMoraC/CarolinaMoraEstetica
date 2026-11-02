<?php
declare(strict_types=1);

namespace App\Application\Booking\RescheduleBooking;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Application\Shared\Audit\AuditService;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;

/**
 * RescheduleAppointmentUseCase — Caso de Uso de Aplicación
 *
 * Orquesta la reprogramación autónoma de una cita existente:
 *   1. Verificación de existencia de la cita.
 *   2. Validación de propiedad (ownership) o permisos administrativos.
 *   3. Verificación de estado mutable (PENDING, CONFIRMED).
 *   4. Validación de fecha y hora futura en huso horario 'America/Bogota'.
 *   5. Verificación de disponibilidad del profesional evitando colisiones concurrentes (Skill 2).
 *   6. Mutación atómica en repositorio con nivel REPEATABLE READ y bloqueo FOR UPDATE.
 *   7. Registro inmutable en auditoría forense (Skill 9).
 */
class RescheduleAppointmentUseCase
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepo,
        private readonly AuditService $auditService
    ) {}

    /**
     * @param RescheduleAppointmentDTO $dto
     * @return array<string, mixed>
     * @throws NotFoundException Si la cita no existe.
     * @throws DomainException Si no pertenece al usuario, estado inválido o fecha pasada.
     * @throws ConflictException Si hay un conflicto horario de profesional o bloqueo concurrente.
     */
    public function execute(RescheduleAppointmentDTO $dto): array
    {
        // 1. Recuperar la cita original
        $appointment = $this->appointmentRepo->findById($dto->appointmentId);
        if ($appointment === null) {
            throw new NotFoundException("La cita #{$dto->appointmentId} no fue encontrada.");
        }

        // 2. Validar propiedad (ownership) si el actor es un cliente
        $userRole = strtoupper(trim($dto->userRole));
        $isStaff = in_array($userRole, ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'RECEPTIONIST'], true);

        if (!$isStaff && !$appointment->belongsToClient($dto->userId)) {
            throw new DomainException("No tienes permisos para reprogramar la cita #{$dto->appointmentId}. La cita pertenece a otro cliente.");
        }

        // 3. Validar estado reprogramable
        if (!$appointment->canBeRescheduled()) {
            throw new DomainException(
                "La cita #{$dto->appointmentId} no puede ser reprogramada en su estado actual: '{$appointment->getAppointmentStatus()}'. " .
                "Solo se pueden reprogramar citas en estado PENDING o CONFIRMED."
            );
        }

        // 4. Procesar nueva fecha y hora con zona horaria America/Bogota (Skill 7)
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('now', $tz);

        $newTimeString = trim($dto->newScheduledTime);
        if (strlen($newTimeString) === 5) {
            $newTimeString .= ':00';
        }

        $newStartTimestamp = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dto->newScheduledDate . ' ' . $newTimeString,
            $tz
        );

        if ($newStartTimestamp === false) {
            throw new DomainException("El formato de fecha u hora suministrado es inválido. Use 'YYYY-MM-DD' y 'HH:MM'.");
        }

        if ($newStartTimestamp <= $now) {
            throw new DomainException("No es posible reprogramar una cita a una fecha u hora pasada.");
        }

        // 5. Calcular la duración del servicio en minutos
        $durationSeconds = $appointment->getEstimatedEndTimestamp()->getTimestamp() - $appointment->getScheduledTimestamp()->getTimestamp();
        $durationMinutes = (int) ($durationSeconds / 60);
        if ($durationMinutes <= 0) {
            $durationMinutes = 30; // fallback defensivo estándar
        }

        $newEndTimestamp = $newStartTimestamp->add(new DateInterval("PT{$durationMinutes}M"));

        $newStartFormatted = $newStartTimestamp->format('Y-m-d H:i:s');
        $newEndFormatted   = $newEndTimestamp->format('Y-m-d H:i:s');

        // 6. Verificar colisión horaria con otras citas activas del mismo profesional
        $hasOverlap = $this->appointmentRepo->hasOverlappingAppointment(
            $appointment->getProfessionalProfileId(),
            $newStartFormatted,
            $newEndFormatted,
            $appointment->getAppointmentId()
        );

        if ($hasOverlap) {
            throw new ConflictException("El especialista seleccionado ya cuenta con un servicio agendado o en progreso en el rango horario solicitado.", 409);
        }

        $oldStartFormatted = $appointment->getScheduledTimestamp()->format('Y-m-d H:i:s');
        $oldEndFormatted   = $appointment->getEstimatedEndTimestamp()->format('Y-m-d H:i:s');
        $reason = $dto->reason ?? ($isStaff ? 'Reprogramación administrativa' : 'Reprogramación solicitada por el cliente');

        // 7. Ejecutar reprogramación en repositorio
        $success = $this->appointmentRepo->reschedule(
            $dto->appointmentId,
            $newStartFormatted,
            $newEndFormatted,
            $dto->userId,
            $reason
        );

        if ($success) {
            // 8. Registrar auditoría forense (Skill 9)
            $this->auditService->recordMutation(
                actorId: $dto->userId,
                action: 'RESCHEDULE',
                entityType: 'appointment',
                entityId: $dto->appointmentId,
                oldValues: [
                    'scheduled_timestamp'     => $oldStartFormatted,
                    'estimated_end_timestamp' => $oldEndFormatted
                ],
                newValues: [
                    'scheduled_timestamp'     => $newStartFormatted,
                    'estimated_end_timestamp' => $newEndFormatted,
                    'reason'                  => $reason
                ]
            );
        }

        return [
            'appointment_id'          => $dto->appointmentId,
            'previous_start'          => $oldStartFormatted,
            'new_scheduled_timestamp' => $newStartFormatted,
            'new_estimated_end'       => $newEndFormatted,
            'status'                  => $appointment->getAppointmentStatus(),
            'message'                 => 'Cita reprogramada exitosamente.'
        ];
    }
}
