<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * UuidVO — Value Object para Identificadores UUID v4
 *
 * Encapsula y valida identificadores únicos en formato UUID v4.
 * Garantiza que ningún ID de entidad del dominio sea un string arbitrario.
 *
 * Uso básico:
 * ```php
 * $id = UuidVO::generate();             // Genera nuevo UUID v4 aleatorio
 * $id = UuidVO::fromString($rawString); // Valida y encapsula UUID existente
 * echo $id->toString();                 // "550e8400-e29b-41d4-a716-446655440000"
 * $id->equals(UuidVO::fromString($other)); // true/false
 * ```
 *
 * Invariantes del dominio:
 * - El UUID debe cumplir el formato RFC 4122 versión 4 estrictamente.
 * - Un UUID mal formado lanza DomainException.
 */
final class UuidVO
{
    /**
     * Expresión regular para validar UUID v4 (RFC 4122).
     * Formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * donde y ∈ {8, 9, a, b}.
     */
    private const UUID_V4_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @param string $value UUID v4 validado en formato canónico con guiones.
     */
    private function __construct(
        private readonly string $value
    ) {}

    // -------------------------------------------------------------------------
    // Constructores estáticos (Factory Methods)
    // -------------------------------------------------------------------------

    /**
     * Valida y encapsula un UUID v4 existente.
     *
     * @param string $uuid UUID en formato estándar con guiones.
     * @throws DomainException Si el formato no es un UUID v4 válido.
     */
    public static function fromString(string $uuid): self
    {
        $trimmed = strtolower(trim($uuid));

        if (preg_match(self::UUID_V4_PATTERN, $trimmed) !== 1) {
            throw new DomainException(
                'El identificador proporcionado no es un UUID v4 válido: "' . $uuid . '".',
                'UuidVO'
            );
        }

        return new self($trimmed);
    }

    /**
     * Genera un nuevo UUID v4 criptográficamente seguro.
     *
     * Implementación pura sin dependencia de extensiones externas (ramsey/uuid).
     * Usa random_bytes() que internamente llama al CSPRNG del sistema operativo.
     *
     * @throws DomainException Si el sistema no puede generar bytes aleatorios.
     */
    public static function generate(): self
    {
        try {
            $bytes = random_bytes(16);
        } catch (\Exception $e) {
            throw new DomainException(
                'No se pudo generar un UUID: el sistema no tiene suficiente entropía.',
                'UuidVO',
                0,
                $e
            );
        }

        // Establece la versión a 4 (bits 12-15 del byte 6)
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);

        // Establece la variante a RFC 4122 (bits 6-7 del byte 8)
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        return new self($uuid);
    }

    // -------------------------------------------------------------------------
    // Lectores (Getters)
    // -------------------------------------------------------------------------

    /**
     * Retorna el UUID como string canónico en minúsculas con guiones.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Retorna el UUID como string sin guiones (32 caracteres hex).
     * Útil para almacenamiento en columnas BINARY(16) de MySQL.
     */
    public function toHex(): string
    {
        return str_replace('-', '', $this->value);
    }

    // -------------------------------------------------------------------------
    // Comparaciones
    // -------------------------------------------------------------------------

    /**
     * Verifica si dos UUIDs son iguales (comparación insensible a mayúsculas).
     */
    public function equals(UuidVO $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Representación de cadena del Value Object.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
