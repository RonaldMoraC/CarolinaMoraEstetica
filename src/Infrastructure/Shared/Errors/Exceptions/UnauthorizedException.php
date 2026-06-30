<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors\Exceptions;

/**
 * UnauthorizedException — HTTP 401
 *
 * Se lanza cuando la petición carece de credenciales válidas de autenticación.
 * El GlobalExceptionHandler la mapea automáticamente a HTTP 401 + RFC 7807.
 */
final class UnauthorizedException extends \RuntimeException
{
    public function __construct(
        string     $message  = 'Se requiere autenticación para acceder a este recurso.',
        int        $code     = 401,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
