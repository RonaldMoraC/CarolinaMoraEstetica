<?php
declare(strict_types=1);

namespace App\Application\Booking\Operation;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;

/**
 * ExecuteCheckInUseCase
 *
 * Orquesta la transición de una cita al estado 'IN_PROGRESS'.
 * Cumple Skill 1 (Clean Code), Skill 9 (Auditoría) y TAREA 04.
 */
class ExecuteCheckInUseCase
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepo,
        private SystemAuditLogRepository $auditRepo
    ) {}

    /**
     * Ejecuta el proceso de Check-in para una cita.
     * 
     * @param int $appointmentId ID de la cita.
     * @param int $actorId ID del usuario (recepcionista/admin) que realiza la acción.
     * @return bool
     * @throws NotFoundException Si la cita no existe.
     * @throws DomainException Si el estado actual no permite el check-in.
     * @throws ConflictException Si hay un bloqueo por concurrencia (Skill 2).
     */
    public function execute(int $appointmentId, int $actorId): bool
    {
        // 1. Recuperar la cita (el repositorio devuelve un array hidratado)
        $appointment = $this->appointmentRepo->findById($appointmentId);

        if ($appointment === null) {
            throw new NotFoundException("La cita #{$appointmentId} no fue encontrada.");
        }

        // 2. Validar invariantes de estado (Tarea 04)
        // 'estado' contiene el valor original del ENUM (PENDING, CONFIRMED, etc.)
        $currentStatus = $appointment['estado'] ?? '';

        if (!in_array($currentStatus, ['PENDING', 'CONFIRMED'], true)) {
            throw new DomainException(
                "No es posible realizar el check-in. La cita se encuentra en estado '{$currentStatus}'."
            );
        }

        // 3. Ejecutar mutación en el repositorio
        // PdoAppointmentRepository lanzará ConflictException si ocurre un Lock Wait Timeout (Skill 2).
        $success = $this->appointmentRepo->checkIn($appointmentId, $actorId);

        if ($success) {
            // 4. Registrar Auditoría Forense (Skill 9)
            // Obtenemos el nuevo estado para registrar el cambio completo.
            $updatedAppointment = $this->appointmentRepo->findById($appointmentId);

            $this->auditRepo->insert(
                actorId: (string) $actorId,
                action: 'APPOINTMENT_CHECK_IN',
                entityType: 'appointment',
                entityId: $appointmentId,
                oldValues: $appointment,
                newValues: $updatedAppointment ?? []
            );
        }

        return $success;
    }
}
