<?php
declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

/**
 * PromotionRepositoryInterface
 * Contrato para la persistencia de promociones y campañas de descuento.
 */
interface PromotionRepositoryInterface
{
    public function create(array $data): int;
    public function findById(int $id): ?array;
    public function findAll(string $filter = 'all'): array;
    public function deactivate(int $id): bool;
}