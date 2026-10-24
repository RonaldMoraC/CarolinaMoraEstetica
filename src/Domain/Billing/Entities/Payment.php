<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

/**
 * Payment — Entidad Raíz de Agregado Financiero
 *
 * Modela un cobro o transacción monetaria asociada a una cita.
 * Cumple con la regla de inmutabilidad financiera (Append-Only):
 * una vez registrado el pago, sus montos no pueden modificarse mediante UPDATE.
 */
class Payment
{
    private ?int $paymentId = null;
    private int $appointmentId;
    private Money $amount;
    private string $paymentMethod; // CASH, CARD, TRANSFER, ONLINE
    private string $paymentStatus; // SUCCESS, PENDING, REFUNDED, FAILED
    private ?string $transactionReference;
    private DateTimeImmutable $createdAt;

    private const VALID_METHODS = ['CASH', 'CARD', 'TRANSFER', 'ONLINE'];
    private const VALID_STATUSES = ['SUCCESS', 'PENDING', 'REFUNDED', 'FAILED'];

    public function __construct(
        int $appointmentId,
        Money $amount,
        string $paymentMethod,
        string $paymentStatus = 'SUCCESS',
        ?string $transactionReference = null
    ) {
        $this->appointmentId = $appointmentId;
        $this->amount = $amount;
        $this->setPaymentMethod($paymentMethod);
        $this->setPaymentStatus($paymentStatus);
        $this->transactionReference = $transactionReference;
        $this->createdAt = new DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
    }

    private function setPaymentMethod(string $method): void
    {
        $upper = strtoupper($method);
        if (!in_array($upper, self::VALID_METHODS, true)) {
            throw new DomainException("Método de pago inválido: '{$method}'. Permitidos: " . implode(', ', self::VALID_METHODS));
        }
        $this->paymentMethod = $upper;
    }

    private function setPaymentStatus(string $status): void
    {
        $upper = strtoupper($status);
        if (!in_array($upper, self::VALID_STATUSES, true)) {
            throw new DomainException("Estado de pago inválido: '{$status}'. Permitidos: " . implode(', ', self::VALID_STATUSES));
        }
        $this->paymentStatus = $upper;
    }

    public function getPaymentId(): ?int { return $this->paymentId; }
    public function setPaymentId(int $id): void { $this->paymentId = $id; }
    public function getAppointmentId(): int { return $this->appointmentId; }
    public function getAmount(): Money { return $this->amount; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getTransactionReference(): ?string { return $this->transactionReference; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
