<?php declare(strict_types=1);

namespace App\Application\Booking\CancelBooking;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * CancelAppointmentUseCase — Caso de Uso de Aplicación
 *
 * Orquesta la cancelación de una cita existente:
 *   1. Validación de entrada
 *   2. Verificación de existencia y ownership
 *   3. Verificación de que la cita es cancelable
 *   4. Cambio de estado + registro en historial
 *   5. Auditoría
 */
// Se eliminan las propiedades privadas y se usan directamente en el constructor para mayor concisión (Skill 1)
class CancelAppointmentUseCase
{
    private AppointmentRepositoryInterface $appointmentRepo;
    private SystemAuditLogRepository $auditRepo;

    public function __construct(
        AppointmentRepositoryInterface $appointmentRepo,
        SystemAuditLogRepository $auditRepo
    ) {
        $this->appointmentRepo = $appointmentRepo;
        $this->auditRepo = $auditRepo;
    }

    /**
     * Ejecuta la cancelación de una cita.
     *
     * @param CancelAppointmentDTO $dto Datos de la cancelación, incluyendo ID de cita, usuario y razón.
     * @return array Detalles de la cancelación.
     * @throws NotFoundException Si la cita no existe.
     * @throws DomainException Si la cita no pertenece al usuario, no es cancelable o no cumple la anticipación.
     * @throws ConflictException Si hay un bloqueo por concurrencia (Skill 2).
     */
    public function execute(CancelAppointmentDTO $dto): array
    {
        // 1. Recuperar la cita (el repositorio devuelve un array hidratado)
        $appointment = $this->appointmentRepo->findById($dto->appointmentId);

        if ($appointment === null) {
            throw new NotFoundException("La cita #{$dto->appointmentId} no fue encontrada.");
        }

        // 2. Validar pertenencia (ownership)
        if (($appointment['client_profile_id'] ?? 0) !== $dto->userId) {
            throw new DomainException("No tienes permisos para cancelar la cita #{$dto->appointmentId}. La cita pertenece a otro cliente.");
        }

        // 3. Validar estado actual de la cita (Tarea 04)
        $currentStatus = $appointment['appointment_status'] ?? '';
        if (!in_array($currentStatus, ['PENDING', 'CONFIRMED'], true)) {
            throw new DomainException(
                "La cita #{$dto->appointmentId} no puede ser cancelada. Estado actual: '{$currentStatus}'. " .
                "Solo se pueden cancelar citas en estado PENDING o CONFIRMED."
            );
        }

        // 4. Validar la regla de 24 horas de anticipación (Skill 2, Skill 7)
        $scheduledTimestamp = new DateTimeImmutable($appointment['scheduled_timestamp'], new DateTimeZone('America/Bogota'));
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));

        // Calcular la diferencia en segundos
        $diffSeconds = $scheduledTimestamp->getTimestamp() - $now->getTimestamp();
        $hoursRemaining = $diffSeconds / 3600;

        // Asumimos 24 horas como el umbral, esto podría ser configurable en el futuro
        if ($hoursRemaining < 24) {
            throw new DomainException(
                "La cita #{$dto->appointmentId} no puede ser cancelada con menos de 24 horas de anticipación. " .
                "Faltan " . round($hoursRemaining, 1) . " horas para la cita."
            );
        }

        // 5. Ejecutar mutación en el repositorio (delegando transaccionalidad y bloqueo pesimista)
        $success = $this->appointmentRepo->cancel(
            $dto->appointmentId,
            $dto->userId,
            $dto->reason ?? 'Cancelación solicitada por el cliente'
        );

        if ($success) {
            // 6. Registrar Auditoría Forense (Skill 9)
            $updatedAppointment = $this->appointmentRepo->findById($dto->appointmentId);
            $this->auditRepo->insert(
                actorId: (string) $dto->userId,
                action: 'APPOINTMENT_CANCELLED',
                entityType: 'appointment',
                entityId: $dto->appointmentId,
                oldValues: $appointment, // Estado completo antes del cambio
                newValues: $updatedAppointment ?? [] // Estado completo después del cambio
            );
        }

        return [
            'appointment_id' => $dto->appointmentId,
            'previous_status' => $currentStatus,
            'new_status' => 'CANCELLED',
            'cancelled_at' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
                ->format('Y-m-d H:i:s')
        ];
    }
}
