<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors\Exceptions;

/**
 * ForbiddenException — HTTP 403
 *
 * Se lanza cuando el usuario está autenticado pero no tiene permisos
 * suficientes para ejecutar la acción solicitada (control de roles).
 */
final class ForbiddenException extends \RuntimeException
{
    public function __construct(
        string     $message  = 'No tienes permisos suficientes para realizar esta acción.',
        int        $code     = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
