<?php

declare(strict_types=1);

namespace App\Application\Billing\PosPayment;

use App\Domain\Shared\ValueObjects\Money;

final class RegisterPosPaymentDTO
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly Money $amount,
        public readonly string $paymentMethod,
        public readonly ?string $transactionReference = null
    ) {}
}
