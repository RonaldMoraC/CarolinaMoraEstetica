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

    public function obtenerTodos(?string $busqueda = null, ?int $categoriaId = null, ?string $estadoStock = null): array
    {
        $sql = "SELECT p.*, c.name as categoria_nombre, b.name as marca_nombre, s.name as proveedor_nombre
                FROM productos p
                LEFT JOIN product_category c ON p.category_id = c.id
                LEFT JOIN product_brand b ON p.brand_id = b.id
                LEFT JOIN product_supplier s ON p.supplier_id = s.id
                WHERE 1=1";

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
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO productos (sku, name, description, category_id, brand_id, supplier_id, cost_price, unit_price, current_stock, minimum_stock, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['sku'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            $data['brand_id'] ?? null,
            $data['supplier_id'] ?? null,
            $data['cost_price'] ?? 0.00,
            $data['unit_price'] ?? 0.00,
            $data['current_stock'] ?? 0,
            $data['minimum_stock'] ?? 5
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE productos SET sku = ?, name = ?, description = ?, category_id = ?, brand_id = ?, supplier_id = ?, cost_price = ?, unit_price = ?, minimum_stock = ?, updated_at = NOW() WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['sku'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            $data['brand_id'] ?? null,
            $data['supplier_id'] ?? null,
            $data['cost_price'] ?? 0.00,
            $data['unit_price'] ?? 0.00,
            $data['minimum_stock'] ?? 5,
            $id
        ]);
    }

    public function actualizarStock(int $id, int $nuevoStock): bool
    {
        $stmt = $this->db->prepare("UPDATE productos SET current_stock = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$nuevoStock, $id]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM productos WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function obtenerMetricas(): array
    {
        $sqlTotal = $this->db->query("SELECT COUNT(*) as total, SUM(current_stock * cost_price) as valor FROM productos")->fetch(PDO::FETCH_ASSOC);
        $sqlBajo = $this->db->query("SELECT COUNT(*) as bajo FROM productos WHERE current_stock <= minimum_stock")->fetch(PDO::FETCH_ASSOC);
        $sqlMovsHoy = $this->db->query("SELECT COUNT(*) as hoy FROM movimientos_inventario WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC);

        return [
            'total_productos' => (int)($sqlTotal['total'] ?? 0),
            'stock_bajo' => (int)($sqlBajo['bajo'] ?? 0),
            'valor_inventario' => (float)($sqlTotal['valor'] ?? 0.00),
            'movimientos_hoy' => (int)($sqlMovsHoy['hoy'] ?? 0)
        ];
    }
}
