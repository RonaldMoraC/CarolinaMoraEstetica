<?php
declare(strict_types=1);

namespace App\Infrastructure\Billing\Http;

use App\Infrastructure\Billing\Persistence\PdoInvoiceRepository;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use PDO;
use Exception;

/**
 * CashRegisterController — Controlador POS y Caja Chica
 *
 * Administra aperturas de caja, cierres de turno y pagos idempotentes en el POS.
 */
class CashRegisterController
{
    private PDO $db;
    private PdoInvoiceRepository $invoiceRepository;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->invoiceRepository = new PdoInvoiceRepository($this->db);
    }

    public function handle(): void
    {
        $action = $_GET['action'] ?? 'status';
        $userRole = $_COOKIE['user_role'] ?? 'CLIENT';

        // Solo personal autorizado (Recepcionista, Manager, Admin)
        if (!in_array($userRole, ['SUPER_ADMIN', 'MANAGER', 'RECEPTIONIST'], true)) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 403,
                type: 'https://carolinamoraestetica.com/errors/forbidden',
                title: 'Acceso Denegado',
                detail: 'No tienes privilegios de caja o POS para realizar esta operación.',
                instance: $_SERVER['REQUEST_URI']
            );
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            switch ($action) {
                case 'process_payment':
                    $this->processPayment();
                    break;
                case 'status':
                default:
                    echo json_encode([
                        'status' => 'success',
                        'module' => 'Cash Register & POS',
                        'server_time' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s')
                    ]);
                    break;
            }
        } catch (Exception $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/pos-error',
                title: 'Error en el Módulo POS / Caja',
                detail: $e->getMessage(),
                instance: $_SERVER['REQUEST_URI']
            );
        }
    }

    private function processPayment(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $idempotencyKey = trim($input['idempotency_key'] ?? '');
        $invoiceId = (int)($input['invoice_id'] ?? 0);
        $methodId = (int)($input['method_id'] ?? 0);
        $amount = (float)($input['amount_paid'] ?? 0);
        $gatewayRef = trim($input['gateway_transaction_reference'] ?? '') ?: null;

        if (empty($idempotencyKey) || $invoiceId <= 0 || $methodId <= 0 || $amount <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parámetros inválidos. Se requiere idempotency_key, invoice_id, method_id y amount_paid.'
            ]);
            return;
        }

        $result = $this->invoiceRepository->processIdempotentPayment(
            $idempotencyKey,
            $invoiceId,
            $methodId,
            $amount,
            $gatewayRef
        );

        echo json_encode($result);
    }
}
