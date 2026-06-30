<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Persistence;

use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use PDO;

/**
 * PdoServiceRepository
 *
 * Implementación de infraestructura contra MySQL para el catálogo de servicios.
 * Soporta paginación server-side, búsqueda LIKE y filtro por categoría.
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección PDO por constructor
 *  - Skill 10 → Sentencias preparadas con bindValue, ZERO concatenación SQL
 *  - Skill 12 → Índice idx_service_lookup (is_active, category_id) para
 *                renderización paginada de alta velocidad
 */
final class PdoServiceRepository implements ServiceRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────────
    //  CONSULTA PAGINADA CON FILTROS
    // ─────────────────────────────────────────────────────────

    public function getPaginated(int $page = 1, int $perPage = 15, string $search = '', ?int $categoryId = null, ?bool $isActive = null): array
    {
        // Skill 12 — Construcción dinámica de WHERE con bindValue
        // para mantener sentencias preparadas sin concatenación SQL.
        $whereParts = [];
        $bindParams = [];

        if ($search !== '') {
            $whereParts[] = 's.name LIKE :search';
            $bindParams[':search'] = '%' . $search . '%';
        }

        if ($categoryId !== null) {
            $whereParts[] = 's.category_id = :category_id';
            $bindParams[':category_id'] = $categoryId;
        }

        if ($isActive !== null) {
            $whereParts[] = 's.is_active = :is_active';
            $bindParams[':is_active'] = $isActive ? 1 : 0;
        }

        $whereClause = $whereParts !== []
            ? 'WHERE ' . implode(' AND ', $whereParts)
            : '';

        // ── COUNT total ──────────────────────────────────────────────
        $countSql = "SELECT COUNT(*) AS total
                     FROM service s
                     {$whereClause}";

        $countStmt = $this->pdo->prepare($countSql);
        foreach ($bindParams as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();

        $totalRecords = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $totalPages   = $perPage > 0 ? (int) max(1, ceil($totalRecords / $perPage)) : 1;
        $offset       = ($page - 1) * $perPage;

        // ── DATA paginada ────────────────────────────────────────────
        // Skill 12 — JOIN con service_category para enriquecer la
        // respuesta sin N+1 (una sola consulta masiva indexada).
        $dataSql = "SELECT
                        s.service_id,
                        s.category_id,
                        sc.name AS category_name,
                        s.name,
                        s.description,
                        s.duration_minutes,
                        s.base_price,
                        s.cleanup_margin_minutes,
                        s.is_active,
                        s.created_at,
                        s.updated_at
                    FROM service s
                    LEFT JOIN service_category sc ON sc.category_id = s.category_id
                    {$whereClause}
                    ORDER BY s.is_active DESC, s.name ASC
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($bindParams as $key => $value) {
            $dataStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $rows,
            'meta' => [
                'total_records' => $totalRecords,
                'current_page'  => $page,
                'total_pages'   => $totalPages,
            ],
        ];
    }

    /**
     * Obtiene todos los servicios activos del catálogo.
     * Útil para asignaciones masivas y filtros.
     */
    public function findAllActive(): array
    {
        $sql = "SELECT service_id, name, base_price, duration_minutes
                FROM service
                WHERE is_active = 1
                ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los servicios (activos e inactivos) con categoría.
     * Usado por el panel administrativo para listados completos.
     */
    public function findAll(): array
    {
        $sql = "SELECT
                    s.service_id,
                    s.category_id,
                    sc.name AS category_name,
                    s.name,
                    s.description,
                    s.duration_minutes,
                    s.base_price,
                    s.cleanup_margin_minutes,
                    s.is_active,
                    s.created_at,
                    s.updated_at
                FROM service s
                LEFT JOIN service_category sc ON sc.category_id = s.category_id
                ORDER BY s.is_active DESC, s.name ASC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────
    //  CONSULTA POR ID
    // ─────────────────────────────────────────────────────────

    public function findById(int $serviceId): ?array
    {
        $sql = "SELECT
                    s.service_id,
                    s.category_id,
                    sc.name AS category_name,
                    s.name,
                    s.description,
                    s.duration_minutes,
                    s.base_price,
                    s.cleanup_margin_minutes,
                    s.is_active,
                    s.created_at,
                    s.updated_at
                FROM service s
                LEFT JOIN service_category sc ON sc.category_id = s.category_id
                WHERE s.service_id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $serviceId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Obtiene todas las categorías del catálogo.
     */
    public function getCategories(): array
    {
        $sql = "SELECT category_id, name FROM service_category ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ─────────────────────────────────────────────────────────
    //  CREACIÓN
    // ─────────────────────────────────────────────────────────
    
    public function create(array $data): int
    {
        $sql = "INSERT INTO service (category_id, name, description, duration_minutes, base_price, cleanup_margin_minutes, is_active)
                VALUES (:category_id, :name, :description, :duration, :price, :cleanup, :is_active)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':duration', $data['duration_minutes'], PDO::PARAM_INT);
        $stmt->bindValue(':price', $data['base_price'], PDO::PARAM_STR);
        $stmt->bindValue(':cleanup', $data['cleanup_margin_minutes'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int) $this->pdo->lastInsertId();
    }
    
    // ─────────────────────────────────────────────────────────
    //  ACTUALIZACIÓN
    // ─────────────────────────────────────────────────────────
    
    /**
     * Actualiza un servicio de forma atómica.
     * Aplica Skill 2: Transacciones y Skill 10: Sentencias preparadas.
     */
    public function update(int $serviceId, array $data): bool
    {
        $fields = [];
        $params = [':id' => $serviceId];
        
        // Construcción dinámica de UPDATE SET con bindValue
        // Se añade is_active para permitir actualizaciones integrales
        $allowedFields = [
            'category_id'          => 'category_id = :category_id',
            'name'                 => 'name = :name',
            'is_active'            => 'is_active = :is_active',
            'description'          => 'description = :description',
            'duration_minutes'     => 'duration_minutes = :duration_minutes',
            'base_price'           => 'base_price = :base_price',
            'cleanup_margin_minutes' => 'cleanup_margin_minutes = :cleanup_margin_minutes',
        ];
        
        foreach ($allowedFields as $key => $sqlFragment) {
            if (array_key_exists($key, $data)) {
                $fields[] = $sqlFragment;
                $params[':' . $key] = $data[$key];
            }
        }
        
        if ($fields === []) {
            return false;
        }
        
        $sql = "UPDATE service SET " . implode(', ', $fields) . " WHERE service_id = :id";
        
        try {
            // Nivel de aislamiento para consistencia total (Skill 2)
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();
            
            // Verificación de existencia con bloqueo pesimista (FOR UPDATE)
            // Esto asegura que el registro no sea eliminado o modificado por otro proceso durante la transacción
            $checkStmt = $this->pdo->prepare("SELECT service_id FROM service WHERE service_id = :id FOR UPDATE");
            $checkStmt->bindValue(':id', $serviceId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->pdo->rollBack();
                return false;
            }
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $paramType);
            }
            
            $stmt->execute();
            $this->pdo->commit();
            
            // Retornamos true siempre que la ejecución sea exitosa, incluso si rowCount es 0 
            // (indicando que no hubo cambios en los valores pero el registro sí existe).
            return true;
            
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            // Skill 2: Captura específica de bloqueos (Deadlock o Lock Wait Timeout)
            if ($e->getCode() === '40001' || $e->getCode() === 'HY000') {
                // En producción esto se loguearía de forma asíncrona
                throw new \RuntimeException("El recurso está bloqueado temporalmente por otra operación administrativa.", 409);
            }
            throw $e;
        }
    }
    
    // ─────────────────────────────────────────────────────────
    //  TOGGLE ACTIVO / INACTIVO
    // ─────────────────────────────────────────────────────────
    
    /**
     * Cambia el estado de activación delegando en el método update
     * para mantener la lógica de bloqueo y transaccionalidad unificada.
     */
    public function toggleActive(int $serviceId, bool $isActive): bool
    {
        return $this->update($serviceId, [
            'is_active' => $isActive ? 1 : 0
        ]);
    }
}
