<?php
declare(strict_types=1);

namespace App\Application\Booking\Ratings;

use App\Domain\Booking\Repositories\RatingRepositoryInterface;

final class GetRatingsUseCase
{
    public function __construct(private RatingRepositoryInterface $repository) {}

    public function execute(): array
    {
        return $this->repository->findAll();
    }
}
