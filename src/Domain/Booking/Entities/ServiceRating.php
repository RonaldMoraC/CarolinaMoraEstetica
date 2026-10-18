<?php
declare(strict_types=1);

namespace App\Domain\Booking\Entities;

use DateTimeImmutable;

final class ServiceRating
{
    public function __construct(
        private ?int $ratingId,
        private int $appointmentId,
        private int $clientProfileId,
        private int $professionalProfileId,
        private int $score,
        private ?string $comments,
        private ?DateTimeImmutable $createdAt = null
    ) {}

    public function getRatingId(): ?int { return $this->ratingId; }
    public function getAppointmentId(): int { return $this->appointmentId; }
    public function getClientProfileId(): int { return $this->clientProfileId; }
    public function getProfessionalProfileId(): int { return $this->professionalProfileId; }
    public function getScore(): int { return $this->score; }
    public function getComments(): ?string { return $this->comments; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
}
