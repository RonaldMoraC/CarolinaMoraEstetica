<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors\Exceptions;

/**
 * NotFoundException — HTTP 404
 *
 * Se lanza desde repositorios o casos de uso cuando una entidad buscada
 * por su ID u otro criterio no existe en la base de datos.
 */
final class NotFoundException extends \RuntimeException
{
    public function __construct(
        string     $message  = 'El recurso solicitado no fue encontrado.',
        int        $code     = 404,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
