<?php declare(strict_types=1);

namespace App\Infrastructure\Catalog\Persistence;

use PDO;

/**
 * PdoCategoryRepository
 *
 * Retorna las categorías de servicios desde la tabla service_category.
 * Cumple Skill 10 → Sentencias preparadas, Skill 12 → Query indexada.
 */
final class PdoCategoryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todas las categorías activas ordenadas por nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = "SELECT
                    sc.category_id,
                    sc.name,
                    sc.description,
                    sc.icon_url,
                    sc.created_at
                FROM service_category sc
                ORDER BY sc.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
