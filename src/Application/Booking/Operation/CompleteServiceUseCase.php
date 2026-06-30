<?php
declare(strict_types=1);

namespace App\Application\Booking\Operation;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;

/**
 * CompleteServiceUseCase
 *
 * Orquesta la finalización de un servicio en cabina.
 * Cumple Skill 1 (Clean Code), Skill 9 (Auditoría) y TAREA 04.
 */
class CompleteServiceUseCase
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepo,
        private SystemAuditLogRepository $auditRepo
    ) {}

    /**
     * Ejecuta la finalización de una cita.
     *
     * @param int $appointmentId ID de la cita.
     * @param int $actorId ID del usuario que realiza la acción.
     * @return bool
     * @throws NotFoundException Si la cita no existe.
     * @throws DomainException Si el estado no permite completar.
     * @throws ConflictException Si hay conflicto de concurrencia (Skill 2).
     */
    public function execute(int $appointmentId, int $actorId): bool
    {
        // 1. Recuperar la cita (el repositorio devuelve un array hidratado)
        $appointment = $this->appointmentRepo->findById($appointmentId);

        if ($appointment === null) {
            throw new NotFoundException("La cita #{$appointmentId} no fue encontrada.");
        }

        // 2. Validar estado actual (Tarea 04: Solo IN_PROGRESS puede ser completada)
        $currentStatus = $appointment['estado'] ?? '';

        if ($currentStatus !== 'IN_PROGRESS') {
            throw new DomainException(
                "No se puede completar el servicio. La cita debe estar 'En Atención' (actual: '{$currentStatus}')."
            );
        }

        // 3. Ejecutar actualización en repositorio (Transaccional con Bloqueo Pesimista)
        $success = $this->appointmentRepo->complete($appointmentId, $actorId);

        if ($success) {
            // 4. Registrar Auditoría Forense (Skill 9)
            $updatedAppointment = $this->appointmentRepo->findById($appointmentId);

            $this->auditRepo->insert(
                actorId: (string) $actorId,
                action: 'APPOINTMENT_COMPLETED',
                entityType: 'appointment',
                entityId: $appointmentId,
                oldValues: $appointment,
                newValues: $updatedAppointment ?? []
            );
        }

        return $success;
    }
}
