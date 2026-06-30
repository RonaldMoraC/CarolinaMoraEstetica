<?php
declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

/**
 * BranchRepositoryInterface
 *
 * Contrato del Dominio para la persistencia de sucursales (sedes).
 * Cumple Skill 1 (Clean Code).
 */
interface BranchRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findAll(): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
}