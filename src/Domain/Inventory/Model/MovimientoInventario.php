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
        // Validar tipo de movimiento contra el ENUM soportado en DB
        $validTypes = ['ENTRADA_COMPRA', 'SALIDA_VENTA', 'CONSUMO_INTERNO', 'MERMA', 'AJUSTE'];
        if (!in_array($tipo, $validTypes, true)) {
            $tipo = 'AJUSTE'; // Fallback seguro para inicialización u otros ajustes
        }

        $sql = "INSERT INTO inventory_movement (product_id, movement_type, quantity, reason, movement_date)
                VALUES (:product_id, :movement_type, :quantity, :reason, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productoId, PDO::PARAM_INT);
        $stmt->bindValue(':movement_type', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':reason', $razon, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function obtenerHistorial(int $limite = 50): array
    {
        $sql = "SELECT m.id, m.product_id, m.movement_type, m.quantity, m.reason, m.movement_date,
                       m.movement_date as created_at,
                       p.name as producto_nombre
                FROM inventory_movement m
                LEFT JOIN product p ON m.product_id = p.id
                ORDER BY m.movement_date DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarMovimientosPorProducto(int $productoId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM inventory_movement WHERE product_id = :pid");
        $stmt->bindValue(':pid', $productoId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}

