<?php

declare(strict_types=1);

namespace App\Application\Billing\CashRegister;

use App\Domain\Billing\Entities\CashRegisterSession;
use App\Domain\Billing\Repositories\CashRegisterRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;

final class CloseCashRegisterUseCase
{
    public function __construct(
        private readonly CashRegisterRepositoryInterface $repository
    ) {}

    public function execute(CloseCashRegisterDTO $dto): void
    {
        $session = $this->repository->findById($dto->sessionId);

        if (!$session) {
            throw new NotFoundException("Sesión de caja no encontrada.");
        }

        $session->close($dto->finalCash, $dto->notes);
        $this->repository->update($session);
    }
}
