<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\CashRegisterSession;

interface CashRegisterRepositoryInterface
{
    public function save(CashRegisterSession $session): int;
    public function update(CashRegisterSession $session): void;
    public function findById(int $sessionId): ?CashRegisterSession;
    public function findActiveSessionByBranch(int $branchId): ?CashRegisterSession;
}
