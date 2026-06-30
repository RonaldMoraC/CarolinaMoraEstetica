<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors\Exceptions;

/**
 * ConflictException — HTTP 409
 *
 * Se lanza en dos escenarios arquitectónicos:
 *   1. Idempotencia (Skill 6): Una clave de idempotencia llega en estado PROCESSING.
 *   2. Concurrencia (Skill 2): Colisión de horario detectada con SELECT...FOR UPDATE.
 */
final class ConflictException extends \RuntimeException
{
    public function __construct(
        string     $message  = 'La solicitud entra en conflicto con el estado actual del recurso.',
        int        $code     = 409,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
