<?php

declare(strict_types=1);

namespace App\Application\Booking\Operations;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * Caso de uso: CompleteServiceUseCase
 * Registra la finalización del servicio técnico (TRANSICIÓN: IN_PROGRESS -> COMPLETED).
 */
final class CompleteServiceUseCase
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

        if ($appointment->getAppointmentStatus() !== 'IN_PROGRESS') {
            throw new DomainException("Solo se pueden completar servicios que estén en progreso. Estado actual: " . $appointment->getAppointmentStatus());
        }

        return $this->repository->complete($appointmentId, $actorId);
    }
}
