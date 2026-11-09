<?php
declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

/**
 * InsufficientStockException
 *
 * Se lanza cuando la cantidad solicitada para una venta o salida de inventario
 * supera el stock actual disponible en el sistema.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(string $message = 'Stock insuficiente para completar la operación.', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
