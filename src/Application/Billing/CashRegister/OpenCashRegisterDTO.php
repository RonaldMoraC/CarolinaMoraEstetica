<?php

declare(strict_types=1);

namespace App\Application\Billing\CashRegister;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

final class OpenCashRegisterDTO
{
    public function __construct(
        public readonly int $branchId,
        public readonly int $userId,
        public readonly Money $initialCash
    ) {}
}
