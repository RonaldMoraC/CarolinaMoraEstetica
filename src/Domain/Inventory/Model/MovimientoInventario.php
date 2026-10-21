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
                VALUES (:product_id, :user_id, :movement_type, :quantity, :reason, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productoId, PDO::PARAM_INT);
        if ($usuarioId !== null) {
            $stmt->bindValue(':user_id', $usuarioId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':movement_type', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':reason', $razon, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function obtenerHistorial(int $limite = 50): array
    {
        $sql = "SELECT m.movement_id, m.product_id, m.user_id, m.movement_type, m.quantity, m.reason, m.created_at,
                       p.name as producto_nombre, u.first_name as usuario_nombre
                FROM movimientos_inventario m
                LEFT JOIN productos p ON m.product_id = p.id
                LEFT JOIN user u ON m.user_id = u.user_id
                ORDER BY m.created_at DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
