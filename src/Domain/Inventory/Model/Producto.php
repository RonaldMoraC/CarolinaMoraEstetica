<?php
declare(strict_types=1);

namespace App\Domain\Inventory\Model;

use PDO;
use Exception;

class Producto
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerTodos(?string $busqueda = null, ?int $categoriaId = null, ?string $estadoStock = null, bool $incluirInactivos = false): array
    {
        $sql = "SELECT p.*, c.name as categoria_nombre, b.name as marca_nombre, s.name as proveedor_nombre
                FROM product p
                LEFT JOIN product_category c ON p.category_id = c.id
                LEFT JOIN product_brand b ON p.brand_id = b.id
                LEFT JOIN product_supplier s ON p.supplier_id = s.id
                WHERE 1=1";

        if (!$incluirInactivos && $estadoStock !== 'inactivo') {
            $sql .= " AND p.is_active = 1";
        }

        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $term = "%{$busqueda}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($categoriaId)) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoriaId;
        }

        if ($estadoStock === 'bajo') {
            $sql .= " AND p.current_stock <= p.minimum_stock AND p.current_stock > 0";
        } elseif ($estadoStock === 'cero') {
            $sql .= " AND p.current_stock = 0";
        } elseif ($estadoStock === 'ok') {
            $sql .= " AND p.current_stock > p.minimum_stock";
        } elseif ($estadoStock === 'inactivo') {
            $sql .= " AND p.is_active = 0";
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT id, sku, name, description, category_id, brand_id, supplier_id, cost_price, unit_price, current_stock, minimum_stock, is_active, created_at, updated_at
                FROM product
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res !== false ? $res : null;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO product (sku, name, description, category_id, brand_id, supplier_id, cost_price, unit_price, current_stock, minimum_stock, is_active, created_at, updated_at)
                VALUES (:sku, :name, :description, :category_id, :brand_id, :supplier_id, :cost_price, :unit_price, :current_stock, :minimum_stock, :is_active, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sku', $data['sku'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':category_id', !empty($data['category_id']) ? (int)$data['category_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':brand_id', !empty($data['brand_id']) ? (int)$data['brand_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':supplier_id', !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':cost_price', (float)($data['cost_price'] ?? 0.00), PDO::PARAM_STR);
        $stmt->bindValue(':unit_price', (float)($data['unit_price'] ?? 0.00), PDO::PARAM_STR);
        $stmt->bindValue(':current_stock', (int)($data['current_stock'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':minimum_stock', (int)($data['minimum_stock'] ?? 5), PDO::PARAM_INT);
        $stmt->bindValue(':is_active', isset($data['is_active']) ? (int)$data['is_active'] : 1, PDO::PARAM_INT);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE product
                SET sku = :sku, name = :name, description = :description, category_id = :category_id, brand_id = :brand_id, supplier_id = :supplier_id, cost_price = :cost_price, unit_price = :unit_price, minimum_stock = :minimum_stock, is_active = :is_active, updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sku', $data['sku'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':category_id', !empty($data['category_id']) ? (int)$data['category_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':brand_id', !empty($data['brand_id']) ? (int)$data['brand_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':supplier_id', !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':cost_price', (float)($data['cost_price'] ?? 0.00), PDO::PARAM_STR);
        $stmt->bindValue(':unit_price', (float)($data['unit_price'] ?? 0.00), PDO::PARAM_STR);
        $stmt->bindValue(':minimum_stock', (int)($data['minimum_stock'] ?? 5), PDO::PARAM_INT);
        $stmt->bindValue(':is_active', isset($data['is_active']) ? (int)$data['is_active'] : 1, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function actualizarStock(int $id, int $nuevoStock): bool
    {
        $stmt = $this->db->prepare("UPDATE product SET current_stock = :stock, updated_at = NOW() WHERE id = :id");
        $stmt->bindValue(':stock', $nuevoStock, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Elimina un producto protegiendo la integridad referencial:
     * - Si tiene movimientos o registros en el historial, aplica BORRADO LÓGICO (is_active = 0).
     * - Si NO tiene movimientos asociados, realiza un BORRADO FÍSICO (DELETE FROM product).
     *
     * @return array{action: string, message: string}
     */
    public function eliminarConReglaIntegridad(int $id): array
    {
        // 1. Contar movimientos de inventario asociados
        $stmtMov = $this->db->prepare("SELECT COUNT(*) FROM inventory_movement WHERE product_id = :id");
        $stmtMov->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtMov->execute();
        $totalMovimientos = (int)$stmtMov->fetchColumn();

        // 2. Si cuenta con movimientos registrados, aplicar BORRADO LÓGICO
        if ($totalMovimientos > 0) {
            $stmtUpdate = $this->db->prepare("UPDATE product SET is_active = 0, updated_at = NOW() WHERE id = :id");
            $stmtUpdate->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtUpdate->execute();

            return [
                'action' => 'soft_delete',
                'message' => 'El producto tiene movimientos registrados en el historial. Se ha archivado (borrado lógico) para proteger la trazabilidad y la integridad referencial de los datos.'
            ];
        }

        // 3. Si no tiene relaciones, proceder con BORRADO FÍSICO
        $stmtDelete = $this->db->prepare("DELETE FROM product WHERE id = :id");
        $stmtDelete->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtDelete->execute();

        return [
            'action' => 'hard_delete',
            'message' => 'Producto eliminado físicamente del catálogo de inventario.'
        ];
    }

    public function eliminar(int $id): bool
    {
        $this->eliminarConReglaIntegridad($id);
        return true;
    }

    public function obtenerMetricas(): array
    {
        $sqlTotal = $this->db->query("SELECT COUNT(*) as total, COALESCE(SUM(current_stock * cost_price), 0) as valor FROM product WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
        $sqlBajo = $this->db->query("SELECT COUNT(*) as bajo FROM product WHERE is_active = 1 AND current_stock <= minimum_stock")->fetch(PDO::FETCH_ASSOC);
        $sqlMovsHoy = $this->db->query("SELECT COUNT(*) as hoy FROM inventory_movement WHERE DATE(movement_date) = CURDATE()")->fetch(PDO::FETCH_ASSOC);

        return [
            'total_productos' => (int)($sqlTotal['total'] ?? 0),
            'stock_bajo' => (int)($sqlBajo['bajo'] ?? 0),
            'valor_inventario' => (float)($sqlTotal['valor'] ?? 0.00),
            'movimientos_hoy' => (int)($sqlMovsHoy['hoy'] ?? 0)
        ];
    }
}

