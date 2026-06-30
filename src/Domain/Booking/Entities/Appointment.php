<?php declare(strict_types=1);

namespace App\Domain\Booking\Entities;

use DateTimeImmutable;

/**
 * Appointment — Entidad de Dominio del Eje Transaccional
 *
 * Representa una cita programada entre un cliente, un profesional,
 * una sucursal y un servicio del catálogo.
 *
 * Estados del ciclo de vida (máquina de estados):
 *   PENDING → CONFIRMED → IN_PROGRESS → COMPLETED
 *   PENDING → CONFIRMED → CANCELLED
 *   CONFIRMED → NOSHOW
 */
class Appointment
{
    private int $appointmentId;
    private int $clientProfileId;
    private int $professionalProfileId;
    private int $branchId;
    private ?int $promotionId;
    private DateTimeImmutable $scheduledTimestamp;
    private DateTimeImmutable $estimatedEndTimestamp;
    private string $appointmentStatus;
    private float $totalPrice;
    private float $finalPrice;
    private ?string $notes;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private const VALID_STATUSES = [
        'PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NOSHOW'
    ];

    private const CANCELABLE_STATUSES = ['PENDING', 'CONFIRMED'];

    public function __construct(
        int $clientProfileId,
        int $professionalProfileId,
        int $branchId,
        DateTimeImmutable $scheduledTimestamp,
        DateTimeImmutable $estimatedEndTimestamp,
        float $totalPrice,
        float $finalPrice,
        ?int $promotionId = null,
        ?string $notes = null,
        string $appointmentStatus = 'PENDING'
    ) {
        $this->clientProfileId = $clientProfileId;
        $this->professionalProfileId = $professionalProfileId;
        $this->branchId = $branchId;
        $this->scheduledTimestamp = $scheduledTimestamp;
        $this->estimatedEndTimestamp = $estimatedEndTimestamp;
        $this->totalPrice = $totalPrice;
        $this->finalPrice = $finalPrice;
        $this->promotionId = $promotionId;
        $this->notes = $notes;

        $this->setStatus($appointmentStatus);

        $now = new DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getAppointmentId(): int { return $this->appointmentId; }
    public function getClientProfileId(): int { return $this->clientProfileId; }
    public function getProfessionalProfileId(): int { return $this->professionalProfileId; }
    public function getBranchId(): int { return $this->branchId; }
    public function getPromotionId(): ?int { return $this->promotionId; }
    public function getScheduledTimestamp(): DateTimeImmutable { return $this->scheduledTimestamp; }
    public function getEstimatedEndTimestamp(): DateTimeImmutable { return $this->estimatedEndTimestamp; }
    public function getAppointmentStatus(): string { return $this->appointmentStatus; }
    public function getTotalPrice(): float { return $this->totalPrice; }
    public function getFinalPrice(): float { return $this->finalPrice; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function setAppointmentId(int $id): void { $this->appointmentId = $id; }

    private function setStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \DomainException("Estado de cita inválido: '{$status}'. Estados válidos: " . implode(', ', self::VALID_STATUSES));
        }
        $this->appointmentStatus = $status;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->appointmentStatus, self::CANCELABLE_STATUSES, true);
    }

    public function cancel(string $reason = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException("La cita #{$this->appointmentId} no puede ser cancelada. Estado actual: '{$this->appointmentStatus}'.");
        }
        $this->appointmentStatus = 'CANCELLED';
        $this->updatedAt = new DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
    }

    public function belongsToClient(int $clientProfileId): bool
    {
        return $this->clientProfileId === $clientProfileId;
    }
}
