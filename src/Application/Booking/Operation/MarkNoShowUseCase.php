<?php
declare(strict_types=1);

namespace App\Application\Booking\Operation;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;

/**
 * MarkNoShowUseCase
 *
 * Orquesta la lógica de negocio para marcar una cita como "no asistió".
 * Cumple Skill 1 (Clean Code), Skill 9 (Auditoría) y TAREA 04.
 */
class MarkNoShowUseCase
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepo,
        private SystemAuditLogRepository $auditRepo
    ) {}

    /**
     * Marca una cita como No Asistió (NOSHOW).
     *
     * @param int $appointmentId ID de la cita.
     * @param int $actorId ID del usuario (recepcionista/admin) que realiza la acción.
     * @param string $reason Motivo de la inasistencia.
     * @return bool
     * @throws NotFoundException Si la cita no existe.
     * @throws DomainException Si el estado actual no permite marcar como no-show.
     * @throws ConflictException Si hay un bloqueo por concurrencia (Skill 2).
     */
    public function execute(int $appointmentId, int $actorId, string $reason): bool
    {
        // 1. Recuperar la cita (el repositorio devuelve un array hidratado)
        $appointment = $this->appointmentRepo->findById($appointmentId);

        if ($appointment === null) {
            throw new NotFoundException("La cita #{$appointmentId} no fue encontrada.");
        }

        // 2. Validar estado actual (Tarea 04: Solo PENDING o CONFIRMED pueden ser marcadas como no-show)
        $currentStatus = $appointment['estado'] ?? '';

        if (!in_array($currentStatus, ['PENDING', 'CONFIRMED'], true)) {
            throw new DomainException(
                "No es posible marcar como 'No Asistió'. La cita se encuentra en estado '{$currentStatus}'."
            );
        }

        // 3. Ejecutar mutación en el repositorio (Delegando bloqueo pesimista y transaccionalidad)
        $success = $this->appointmentRepo->markNoShow($appointmentId, $actorId, $reason);

        if ($success) {
            // 4. Registrar Auditoría Forense (Skill 9)
            $updatedAppointment = $this->appointmentRepo->findById($appointmentId);

            $this->auditRepo->insert(
                actorId: (string) $actorId,
                action: 'APPOINTMENT_NOSHOW',
                entityType: 'appointment',
                entityId: $appointmentId,
                oldValues: $appointment,
                newValues: $updatedAppointment ?? []
            );
        }

        return $success;
    }
}
