<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

/**
 * DomainException — Excepción Base del Dominio
 *
 * Excepción semántica raíz para todas las violaciones de invariantes
 * de negocio dentro de la capa de Dominio (DDD).
 *
 * Uso:
 *   throw new DomainException('El monto no puede ser negativo.');
 *
 * Las capas de Application e Infrastructure capturan esta excepción
 * y la formatean bajo el estándar RFC 7807 (Problem Details).
 *
 * @see GlobalExceptionHandler — formatea DomainException → HTTP 422
 */
class DomainException extends \DomainException
{
    /**
     * Crea una excepción de dominio con un código de negocio opcional.
     *
     * @param string         $message  Descripción legible del error de negocio.
     * @param string         $domain   Contexto del dominio que lanza el error
     *                                 (ej. 'Money', 'TimeRange', 'Booking').
     * @param int            $code     Código de error interno (0 = no aplica).
     * @param \Throwable|null $previous Excepción causante original (chaining).
     */
    public function __construct(
        string $message,
        private readonly string $domain = 'Domain',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Retorna el contexto del dominio que originó la excepción.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Crea una excepción de dominio con contexto del módulo.
     *
     * @param string $message Mensaje de error.
     * @param string $domain  Nombre del Value Object o Agregado que falla.
     */
    public static function fromDomain(string $message, string $domain): static
    {
        return new static($message, $domain);
    }
}
