<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Payment;

interface PaymentRepositoryInterface
{
    /**
     * Registra un nuevo pago en el sistema (Append-Only).
     */
    public function save(Payment $payment): int;

    /**
     * Busca un pago por su ID único.
     */
    public function findById(int $paymentId): ?Payment;

    /**
     * Busca pagos asociados a una cita específica.
     * @return Payment[]
     */
    public function findByAppointmentId(int $appointmentId): array;

    /**
     * Busca un pago mediante su referencia externa (clave de idempotencia de pasarela).
     */
    public function findByTransactionReference(string $reference): ?Payment;
}
