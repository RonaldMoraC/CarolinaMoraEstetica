<?php

declare(strict_types=1);

namespace App\Application\Billing\CashRegister;

use App\Domain\Shared\ValueObjects\Money;

final class CloseCashRegisterDTO
{
    public function __construct(
        public readonly int $sessionId,
        public readonly Money $finalCash,
        public readonly ?string $notes = null
    ) {}
}
