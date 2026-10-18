<?php
declare(strict_types=1);

namespace App\Application\Booking\Ratings;

use App\Domain\Booking\Entities\ServiceRating;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\RatingRepositoryInterface;
use App\Domain\Shared\Exceptions\DomainException;

final class RegisterRatingUseCase
{
    public function __construct(
        private RatingRepositoryInterface $ratingRepository,
        private AppointmentRepositoryInterface $appointmentRepository
    ) {}

    public function execute(int $appointmentId, int $clientProfileId, int $score, ?string $comments): void
    {
        if ($score < 1 || $score > 5) {
            throw new DomainException("La calificación debe estar entre 1 y 5 estrellas.");
        }

        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (!$appointment) {
            throw new DomainException("La cita no existe.");
        }

        if ($appointment->getAppointmentStatus() !== 'COMPLETED') {
            throw new DomainException("Solo se pueden calificar citas que hayan sido completadas.");
        }

        if ($appointment->getClientProfileId() !== $clientProfileId) {
            throw new DomainException("No tienes permiso para calificar este servicio.");
        }

        $existing = $this->ratingRepository->findByAppointmentAndClient($appointmentId, $clientProfileId);
        if ($existing !== null) {
            throw new DomainException("Ya has calificado este servicio previamente.");
        }

        $rating = new ServiceRating(
            null,
            $appointmentId,
            $clientProfileId,
            $appointment->getProfessionalProfileId(),
            $score,
            $comments
        );

        $this->ratingRepository->save($rating);
    }
}
