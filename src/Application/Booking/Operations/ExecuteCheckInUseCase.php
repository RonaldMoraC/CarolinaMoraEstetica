<?php

declare(strict_types=1);

namespace App\Application\Booking\Operations;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * Caso de uso: ExecuteCheckInUseCase
 * Registra la llegada física del cliente a la sucursal (TRANSICIÓN: CONFIRMED -> IN_PROGRESS).
 */
final class ExecuteCheckInUseCase
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $repository
    ) {}

    public function execute(int $appointmentId, int $actorId): bool
    {
        $appointment = $this->repository->findById($appointmentId);

        if (!$appointment) {
            throw new NotFoundException("Cita no encontrada con ID: $appointmentId");
        }

        if ($appointment->getAppointmentStatus() !== 'CONFIRMED') {
            throw new DomainException("Solo se pueden realizar check-in a citas confirmadas. Estado actual: " . $appointment->getAppointmentStatus());
        }

        return $this->repository->checkIn($appointmentId, $actorId);
    }
}
