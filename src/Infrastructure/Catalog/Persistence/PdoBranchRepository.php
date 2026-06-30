<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Persistence;

use App\Domain\Catalog\Repositories\BranchRepositoryInterface;
use PDO;

/**
 * PdoBranchRepository
 *
 * Implementación PDO para la gestión de sucursales.
 * Cumple Skill 1 (Clean Code) y Skill 10 (Sentencias Preparadas).
 */
final class PdoBranchRepository implements BranchRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $sql = "SELECT branch_id, name, address, phone, is_active, created_at, updated_at 
                FROM branch 
                WHERE branch_id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT branch_id, name, address, phone, is_active 
                FROM branch 
                ORDER BY name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO branch (name, address, phone, is_active) 
                VALUES (:name, :address, :phone, :is_active)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name',      $data['name'],           PDO::PARAM_STR);
        $stmt->bindValue(':address',   $data['address'] ?? '',  PDO::PARAM_STR);
        $stmt->bindValue(':phone',     $data['phone'] ?? '',    PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'name'      => 'name = :name',
            'address'   => 'address = :address',
            'phone'     => 'phone = :phone',
            'is_active' => 'is_active = :is_active'
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($allowedFields as $key => $fragment) {
            if (array_key_exists($key, $data)) {
                $fields[] = $fragment;
                $params[':' . $key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE branch SET " . implode(', ', $fields) . " WHERE branch_id = :id";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        return $stmt->execute();
    }
}
