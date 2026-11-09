<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use PDO;
use Throwable;

/**
 * ProductSaleService
 *
 * Servicio de aplicación encargado de procesar la venta o salida de productos
 * validando de manera transaccional el stock disponible con bloqueo pesimista (FOR UPDATE).
 */
final class ProductSaleService
{
    public function __construct(
        private PDO $pdo,
        private SystemAuditLogRepository $auditLogRepository
    ) {}

    /**
     * Registra la venta de un producto después de validar el stock.
     *
     * @param int $productId        Identificador del producto.
     * @param int $cantidadSolicitada Cantidad que el usuario desea comprar o retirar.
     * @param int $userId           Identificador del usuario que realiza la operación.
     *
     * @throws InsufficientStockException Si la cantidad solicitada supera el stock disponible.
     * @throws Throwable                  Cualquier otro error que provoque rollback.
     *
     * @return void
     */
    public function sellProduct(int $productId, int $cantidadSolicitada, int $userId): void
    {
        if ($cantidadSolicitada <= 0) {
            throw new \InvalidArgumentException('La cantidad solicitada debe ser mayor a cero.');
        }

        // Iniciar transacción con aislamiento REPEATABLE READ / SERIALIZABLE recomendado o BEGIN transaccional
        $this->pdo->beginTransaction();

        try {
            // 1. Obtener stock actual con bloqueo pesimista (SELECT ... FOR UPDATE)
            $stmt = $this->pdo->prepare("SELECT product_id, current_stock, name FROM product WHERE product_id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \RuntimeException("El producto con ID {$productId} no fue encontrado.");
            }

            $stockActual = (int) $product['current_stock'];

            // 2. Comparar cantidades según la regla de negocio
            if ($cantidadSolicitada > $stockActual) {
                $this->pdo->rollBack();
                throw new InsufficientStockException(
                    sprintf(
                        'Cantidad solicitada (%d) supera el stock disponible (%d).',
                        $cantidadSolicitada,
                        $stockActual
                    )
                );
            }

            $nuevoStock = $stockActual - $cantidadSolicitada;

            // 3. Actualizar el stock del producto
            $updateStmt = $this->pdo->prepare("UPDATE product SET current_stock = ?, updated_at = NOW() WHERE product_id = ?");
            $updateStmt->execute([$nuevoStock, $productId]);

            // 4. Registrar movimiento de inventario (salida por venta)
            $movStmt = $this->pdo->prepare(
                "INSERT INTO inventory_movement (product_id, user_id, movement_type, quantity, reason, created_at)
                 VALUES (?, ?, 'SALIDA_VENTA', ?, 'Venta de producto validada por servicio', NOW())"
            );
            $movStmt->execute([$productId, $userId, $cantidadSolicitada]);

            // 5. Registrar en auditoría forense (system_audit_log)
            $this->auditLogRepository->log(
                userId: $userId,
                action: 'SELL_PRODUCT',
                entity: 'product',
                entityId: (string) $productId,
                oldValues: ['current_stock' => $stockActual],
                newValues: ['current_stock' => $nuevoStock, 'sold_quantity' => $cantidadSolicitada]
            );

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
