<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Money — Value Object Monetario Inmutable
 *
 * Encapsula un valor monetario con precisión de céntimos (int en lugar de float)
 * para evitar errores de coma flotante en operaciones financieras críticas.
 *
 * La unidad mínima de representación es el centavo (1 COP = 100 céntimos internamente).
 * La conversión a/desde decimal se realiza dividiendo/multiplicando por 100.
 *
 * Uso básico:
 * ```php
 * $precio = Money::fromDecimal(150000.50);   // 150.000,50 COP
 * $descuento = Money::fromDecimal(15000.00); // 15.000,00 COP
 * $final = $precio->subtract($descuento);    // 135.000,50 COP
 * echo $final->toDecimal();                  // 135000.50
 * echo $final->toFormattedString();          // "COP 135.000,50"
 * ```
 *
 * Invariantes del dominio:
 * - El monto siempre debe ser >= 0. Un monto negativo lanza DomainException.
 * - Los porcentajes de descuento deben estar entre 0.00 y 100.00.
 */
final class Money
{
    /** Código de moneda ISO 4217 del sistema. */
    private const CURRENCY = 'COP';

    /** Factor de conversión: 1 unidad monetaria = 100 céntimos internos. */
    private const FACTOR = 100;

    /**
     * @param int $cents Monto en centavos (unidad mínima interna). Siempre >= 0.
     */
    private function __construct(
        private readonly int $cents
    ) {
        if ($cents < 0) {
            throw new DomainException(
                'El monto monetario no puede ser negativo. Recibido: ' . $cents . ' centavos.',
                'Money'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Constructores estáticos (Factory Methods)
    // -------------------------------------------------------------------------

    /**
     * Crea un Money a partir de un valor decimal (ej. 150000.50).
     * El decimal se convierte a centavos enteros redondeando al más cercano.
     *
     * @param float $amount Monto en unidades monetarias (ej. 150000.50 COP).
     * @throws DomainException Si el monto es negativo.
     */
    public static function fromDecimal(float $amount): self
    {
        if ($amount < 0.0) {
            throw new DomainException(
                'El monto monetario no puede ser negativo. Recibido: ' . $amount,
                'Money'
            );
        }
        return new self((int) round($amount * self::FACTOR));
    }

    /**
     * Crea un Money directamente desde centavos enteros.
     *
     * @param int $cents Monto en centavos. Debe ser >= 0.
     * @throws DomainException Si el monto en centavos es negativo.
     */
    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Crea un Money con valor cero.
     */
    public static function zero(): self
    {
        return new self(0);
    }

    // -------------------------------------------------------------------------
    // Lectores (Getters)
    // -------------------------------------------------------------------------

    /**
     * Retorna el monto en centavos (unidad interna de precisión).
     */
    public function getCents(): int
    {
        return $this->cents;
    }

    /**
     * Retorna el monto como decimal de punto flotante.
     * Útil para almacenarlo como DECIMAL(10,2) en MySQL.
     */
    public function toDecimal(): float
    {
        return $this->cents / self::FACTOR;
    }

    /**
     * Retorna el monto en formato de cadena legible: "COP 150.000,50".
     */
    public function toFormattedString(): string
    {
        return self::CURRENCY . ' ' . number_format($this->toDecimal(), 2, ',', '.');
    }

    /**
     * Retorna el código de moneda del sistema.
     */
    public function getCurrency(): string
    {
        return self::CURRENCY;
    }

    // -------------------------------------------------------------------------
    // Operaciones Aritméticas — Retornan nuevas instancias (Inmutabilidad)
    // -------------------------------------------------------------------------

    /**
     * Suma dos montos. Retorna una nueva instancia.
     */
    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Resta otro monto. Retorna una nueva instancia.
     *
     * @throws DomainException Si el resultado sería negativo.
     */
    public function subtract(Money $other): self
    {
        $result = $this->cents - $other->cents;
        if ($result < 0) {
            throw new DomainException(
                'La resta produciría un monto negativo ('
                . $this->toFormattedString() . ' - ' . $other->toFormattedString() . ').',
                'Money'
            );
        }
        return new self($result);
    }

    /**
     * Multiplica el monto por un factor escalar (ej. cantidad de servicios).
     *
     * @param float $factor Factor multiplicador. Debe ser > 0.
     * @throws DomainException Si el factor es negativo o cero.
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0.0) {
            throw new DomainException(
                'El factor de multiplicación no puede ser negativo. Recibido: ' . $factor,
                'Money'
            );
        }
        return new self((int) round($this->cents * $factor));
    }

    /**
     * Aplica un porcentaje de descuento y retorna el monto de descuento.
     *
     * @param float $percentage Porcentaje entre 0.00 y 100.00 (ej. 15.0 = 15%).
     * @throws DomainException Si el porcentaje está fuera del rango [0, 100].
     */
    public function discountAmount(float $percentage): self
    {
        if ($percentage < 0.0 || $percentage > 100.0) {
            throw new DomainException(
                'El porcentaje de descuento debe estar entre 0 y 100. Recibido: ' . $percentage,
                'Money'
            );
        }
        return new self((int) round($this->cents * ($percentage / 100.0)));
    }

    /**
     * Aplica un porcentaje de descuento y retorna el precio final con descuento.
     *
     * @param float $percentage Porcentaje entre 0.00 y 100.00.
     */
    public function applyDiscount(float $percentage): self
    {
        $discount = $this->discountAmount($percentage);
        return $this->subtract($discount);
    }

    /**
     * Aplica un porcentaje de impuesto (ej. IVA) y retorna el monto con impuesto.
     *
     * @param float $taxPercentage Porcentaje de impuesto (ej. 16.0 = 16% IVA).
     * @throws DomainException Si el porcentaje es negativo.
     */
    public function applyTax(float $taxPercentage): self
    {
        if ($taxPercentage < 0.0) {
            throw new DomainException(
                'El porcentaje de impuesto no puede ser negativo. Recibido: ' . $taxPercentage,
                'Money'
            );
        }
        $tax = (int) round($this->cents * ($taxPercentage / 100.0));
        return new self($this->cents + $tax);
    }

    // -------------------------------------------------------------------------
    // Comparaciones
    // -------------------------------------------------------------------------

    /**
     * Verifica si dos montos son iguales.
     */
    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Verifica si este monto es menor que el otro.
     */
    public function isLessThan(Money $other): bool
    {
        return $this->cents < $other->cents;
    }

    /**
     * Verifica si este monto es mayor que el otro.
     */
    public function isGreaterThan(Money $other): bool
    {
        return $this->cents > $other->cents;
    }

    /**
     * Verifica si el monto es cero.
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Verifica si el monto es mayor que cero.
     */
    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Representación de cadena del Value Object.
     */
    public function __toString(): string
    {
        return $this->toFormattedString();
    }
}
