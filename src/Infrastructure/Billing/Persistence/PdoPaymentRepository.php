<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Entities\Payment;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use PDO;

final class PdoPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function save(Payment $payment): int
    {
        $sql = "INSERT INTO payment (
                    appointment_id, amount, payment_method, payment_status, transaction_reference, created_at
                ) VALUES (
                    :appointmentId, :amount, :method, :status, :ref, :createdAt
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointmentId', $payment->getAppointmentId(), PDO::PARAM_INT);
        $stmt->bindValue(':amount', $payment->getAmount()->toDecimal(), PDO::PARAM_STR);
        $stmt->bindValue(':method', $payment->getPaymentMethod(), PDO::PARAM_STR);
        $stmt->bindValue(':status', $payment->getPaymentStatus(), PDO::PARAM_STR);
        $stmt->bindValue(':ref', $payment->getTransactionReference(), $payment->getTransactionReference() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':createdAt', $payment->getCreatedAt()->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        $stmt->execute();
        $id = (int) $this->pdo->lastInsertId();
        $payment->setPaymentId($id);
        return $id;
    }

    public function findById(int $paymentId): ?Payment
    {
        $sql = "SELECT * FROM payment WHERE payment_id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByAppointmentId(int $appointmentId): array
    {
        $sql = "SELECT * FROM payment WHERE appointment_id = :appointmentId ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointmentId', $appointmentId, PDO::PARAM_INT);
        $stmt->execute();

        $payments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payments[] = $this->hydrate($row);
        }
        return $payments;
    }

    public function findByTransactionReference(string $reference): ?Payment
    {
        $sql = "SELECT * FROM payment WHERE transaction_reference = :ref LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':ref', $reference, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Payment
    {
        $money = Money::fromDecimal((float)$row['amount']);
        $payment = new Payment(
            (int)$row['appointment_id'],
            $money,
            $row['payment_method'],
            $row['payment_status'],
            $row['transaction_reference'] ?? null
        );
        $payment->setPaymentId((int)$row['payment_id']);
        return $payment;
    }
}
