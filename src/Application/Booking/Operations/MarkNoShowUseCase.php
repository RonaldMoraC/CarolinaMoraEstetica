<?php

declare(strict_types=1);

namespace App\Application\Booking\Operations;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;

/**
 * Caso de uso: MarkNoShowUseCase
 * Registra que el cliente no se presentó a la cita (TRANSICIÓN: CONFIRMED -> NOSHOW).
 */
final class MarkNoShowUseCase
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $repository
    ) {}

    public function execute(int $appointmentId, int $actorId, string $reason = 'Cliente no se presentó a la hora pactada.'): bool
    {
        $appointment = $this->repository->findById($appointmentId);

        if (!$appointment) {
            throw new NotFoundException("Cita no encontrada con ID: $appointmentId");
        }

        // El repositorio de citas ya maneja las transiciones de estado y la validación
        return $this->repository->markNoShow($appointmentId, $actorId, $reason);
    }
}
