<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

use App\Domain\Booking\Entities\ServiceRating;

interface RatingRepositoryInterface
{
    public function save(ServiceRating $rating): void;
    /** @return ServiceRating[] */
    public function findAll(): array;
    public function findByAppointmentAndClient(int $appointmentId, int $clientProfileId): ?ServiceRating;
    public function hide(int $ratingId): void;
}
