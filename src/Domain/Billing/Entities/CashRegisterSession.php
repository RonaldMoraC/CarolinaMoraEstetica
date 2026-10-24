<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

/**
 * CashRegisterSession — Entidad de Sesión de Caja (POS)
 *
 * Modela la apertura, control de efectivo inicial/final y cierre de caja
 * para una sucursal y un cajero o recepcionista específico.
 */
class CashRegisterSession
{
    private ?int $sessionId = null;
    private int $branchId;
    private int $userId;
    private Money $initialCash;
    private ?Money $finalCash;
    private string $status; // OPEN, CLOSED
    private DateTimeImmutable $openedAt;
    private ?DateTimeImmutable $closedAt;
    private ?string $notes;

    public function __construct(
        int $branchId,
        int $userId,
        Money $initialCash,
        string $status = 'OPEN',
        ?DateTimeImmutable $openedAt = null,
        ?Money $finalCash = null,
        ?DateTimeImmutable $closedAt = null,
        ?string $notes = null
    ) {
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->initialCash = $initialCash;
        $this->status = strtoupper($status);
        $this->openedAt = $openedAt ?? new DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $this->finalCash = $finalCash;
        $this->closedAt = $closedAt;
        $this->notes = $notes;
    }

    public function close(Money $finalCash, string $notes = null): void
    {
        if ($this->status === 'CLOSED') {
            $this->status = 'CLOSED'; // or throw
            throw new DomainException("La sesión de caja ya se encuentra cerrada.");
        }
        $this->finalCash = $finalCash;
        $this->status = 'CLOSED';
        $this->closedAt = new DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $this->notes = $notes;
    }

    public function getSessionId(): ?int { return $this->sessionId; }
    public function setSessionId(int $id): void { $this->sessionId = $id; }
    public function getBranchId(): int { return $this->branchId; }
    public function getUserId(): int { return $this->userId; }
    public function getInitialCash(): Money { return $this->initialCash; }
    public function getFinalCash(): ?Money { return $this->finalCash; }
    public function getStatus(): string { return $this->status; }
    public function getOpenedAt(): DateTimeImmutable { return $this->openedAt; }
    public function getClosedAt(): ?DateTimeImmutable { return $this->closedAt; }
    public function getNotes(): ?string { return $this->notes; }
}
