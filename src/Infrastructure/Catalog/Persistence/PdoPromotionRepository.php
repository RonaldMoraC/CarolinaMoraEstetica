<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Persistence;

use App\Domain\Catalog\Repositories\PromotionRepositoryInterface;
use PDO;

/**
 * PdoPromotionRepository
 * Implementación PDO para la gestión de promociones.
 * Cumple Skill 1, Skill 10 (Sentencias Preparadas).
 */
final class PdoPromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): int
    {
        $sql = "INSERT INTO promotion (name, discount_percentage, start_date, end_date, is_active) 
                VALUES (:name, :discount, :start, :end, :active)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name',     $data['name'],                PDO::PARAM_STR);
        $stmt->bindValue(':discount', $data['discount_percentage'], PDO::PARAM_STR);
        $stmt->bindValue(':start',    $data['start_date'],          PDO::PARAM_STR);
        $stmt->bindValue(':end',      $data['end_date'],            PDO::PARAM_STR);
        $stmt->bindValue(':active',   $data['is_active'] ?? 1,      PDO::PARAM_INT);
        $stmt->execute();

        $promotionId = (int) $this->pdo->lastInsertId();

        // Vincular servicios si vienen en el array (Tabla intermedia promotion_service)
        if (!empty($data['associated_services'])) {
            $linkSql = "INSERT INTO promotion_service (promotion_id, service_id) VALUES (:promo_id, :service_id)";
            $linkStmt = $this->pdo->prepare($linkSql);
            foreach ($data['associated_services'] as $serviceId) {
                $linkStmt->bindValue(':promo_id', $promotionId, PDO::PARAM_INT);
                $linkStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
                $linkStmt->execute();
            }
        }

        return $promotionId;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM promotion WHERE promotion_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findAll(string $filter = 'all'): array
    {
        $sql = "SELECT * FROM promotion";
        if ($filter === 'active') {
            $sql .= " WHERE is_active = 1 AND CURDATE() BETWEEN start_date AND end_date";
        }
        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deactivate(int $id): bool
    {
        $sql = "UPDATE promotion SET is_active = 0 WHERE promotion_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
