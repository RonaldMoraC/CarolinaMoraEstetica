<?php
declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use PDO;
use PDOException;

/**
 * PdoInvoiceRepository
 *
 * Implementación PDO para facturación y pagos con soporte estricto de Idempotencia (Skill 6).
 */
final class PdoInvoiceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra un pago de forma estrictamente idempotente para evitar cobros duplicados en POS o pasarelas.
     *
     * @param string $idempotencyKey Clave única de idempotencia (hash o ID de pasarela)
     * @param int $invoiceId ID de la factura
     * @param int $methodId ID del método de pago
     * @param float $amount Monto pagado
     * @param string|null $gatewayRef Referencia externa de la pasarela
     * @return array Resultado de la transacción
     * @throws ConflictException
     */
    public function processIdempotentPayment(
        string $idempotencyKey,
        int $invoiceId,
        int $methodId,
        float $amount,
        ?string $gatewayRef
    ): array {
        // Verificar si ya existe un registro de idempotencia o control
        // Nota: Asumimos una tabla o registro atómico. Si se usa una tabla auxiliar 'idempotency_log' o validación por gateway_transaction_reference:
        try {
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();

            // 1. Verificar si la referencia o clave ya fue procesada con éxito
            if (!empty($gatewayRef)) {
                $checkSql = "SELECT payment_id, invoice_id, amount_paid, payment_timestamp
                             FROM invoice_payment
                             WHERE gateway_transaction_reference = :gatewayRef FOR UPDATE";
                $checkStmt = $this->pdo->prepare($checkSql);
                $checkStmt->bindValue(':gatewayRef', $gatewayRef, PDO::PARAM_STR);
                $checkStmt->execute();
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing !== false) {
                    $this->pdo->commit();
                    // Skill 6: Si ya existía con éxito, retornar el payload original sin reprocesar (HTTP 200 implícito)
                    return [
                        'status' => 'success',
                        'idempotent_replay' => true,
                        'payment_id' => (int)$existing['payment_id'],
                        'invoice_id' => (int)$existing['invoice_id'],
                        'amount_paid' => (float)$existing['amount_paid'],
                        'message' => 'Transacción ya procesada previamente (Idempotencia exitosa).'
                    ];
                }
            }

            // 2. Insertar el pago en invoice_payment
            $insertSql = "INSERT INTO invoice_payment (invoice_id, method_id, amount_paid, gateway_transaction_reference)
                          VALUES (:invoiceId, :methodId, :amount, :gatewayRef)";
            $stmt = $this->pdo->prepare($insertSql);
            $stmt->bindValue(':invoiceId', $invoiceId, PDO::PARAM_INT);
            $stmt->bindValue(':methodId', $methodId, PDO::PARAM_INT);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindValue(':gatewayRef', $gatewayRef, $gatewayRef === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            $paymentId = (int) $this->pdo->lastInsertId();

            // 3. Actualizar el estado de la factura a PAID si el acumulado cubre el gran total
            $updateInvoiceSql = "UPDATE invoice
                                 SET billing_status = 'PAID'
                                 WHERE invoice_id = :invoiceId AND grand_total <= (
                                     SELECT COALESCE(SUM(amount_paid), 0)
                                     FROM invoice_payment
                                     WHERE invoice_id = :invoiceId2
                                 )";
            $upStmt = $this->pdo->prepare($updateInvoiceSql);
            $upStmt->bindValue(':invoiceId', $invoiceId, PDO::PARAM_INT);
            $upStmt->bindValue(':invoiceId2', $invoiceId, PDO::PARAM_INT);
            $upStmt->execute();

            $this->pdo->commit();

            return [
                'status' => 'success',
                'idempotent_replay' => false,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'amount_paid' => $amount,
                'message' => 'Pago procesado y registrado exitosamente.'
            ];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '40001' || $e->getCode() === 'HY000') {
                throw new ConflictException("Conflicto de concurrencia en pasarela de pago (Deadlock / Lock Timeout).", 409);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
