<?php
declare(strict_types=1);

namespace App\Domain\Inventory\Model;

use PDO;

class MovimientoInventario
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function registrar(int $productoId, ?int $usuarioId, string $tipo, int $cantidad, ?string $razon): bool
    {
        $sql = "INSERT INTO movimientos_inventario (product_id, user_id, movement_type, quantity, reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $productoId,
            $usuarioId,
            $tipo,
            $cantidad,
            $razon
        ]);
    }

    public function obtenerHistorial(int $limite = 50): array
    {
        $sql = "SELECT m.*, p.name as producto_nombre, u.name as usuario_nombre
                FROM movimientos_inventario m
                LEFT JOIN productos p ON m.product_id = p.id
                LEFT JOIN users u ON m.user_id = u.id
                ORDER BY m.created_at DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
