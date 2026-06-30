<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Shared\ValueObjects\Money
 */
final class MoneyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construcción
    // -------------------------------------------------------------------------

    public function test_from_decimal_creates_correct_cents(): void
    {
        $money = Money::fromDecimal(150000.50);

        $this->assertSame(15000050, $money->getCents());
    }

    public function test_from_cents_stores_exact_value(): void
    {
        $money = Money::fromCents(5000);

        $this->assertSame(5000, $money->getCents());
        $this->assertSame(50.0, $money->toDecimal());
    }

    public function test_zero_creates_money_with_zero_cents(): void
    {
        $money = Money::zero();

        $this->assertTrue($money->isZero());
        $this->assertSame(0, $money->getCents());
    }

    public function test_from_decimal_negative_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/negativo/i');

        Money::fromDecimal(-1.0);
    }

    public function test_from_cents_negative_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        Money::fromCents(-1);
    }

    // -------------------------------------------------------------------------
    // Conversión
    // -------------------------------------------------------------------------

    public function test_to_decimal_returns_correct_float(): void
    {
        $money = Money::fromCents(15000050);

        $this->assertEqualsWithDelta(150000.50, $money->toDecimal(), 0.001);
    }

    public function test_to_formatted_string_returns_cop_format(): void
    {
        $money = Money::fromDecimal(1500.50);

        $this->assertSame('COP 1.500,50', $money->toFormattedString());
    }

    public function test_get_currency_returns_cop(): void
    {
        $this->assertSame('COP', Money::fromCents(100)->getCurrency());
    }

    public function test_to_string_returns_formatted_string(): void
    {
        $money = Money::fromDecimal(50000.0);

        $this->assertSame('COP 50.000,00', (string) $money);
    }

    // -------------------------------------------------------------------------
    // Aritmética
    // -------------------------------------------------------------------------

    public function test_add_returns_new_instance_with_sum(): void
    {
        $a      = Money::fromDecimal(100.00);
        $b      = Money::fromDecimal(50.00);
        $result = $a->add($b);

        $this->assertEqualsWithDelta(150.00, $result->toDecimal(), 0.001);
        // Inmutabilidad: los originales no cambian
        $this->assertEqualsWithDelta(100.00, $a->toDecimal(), 0.001);
    }

    public function test_subtract_returns_new_instance_with_difference(): void
    {
        $a      = Money::fromDecimal(100.00);
        $b      = Money::fromDecimal(30.00);
        $result = $a->subtract($b);

        $this->assertEqualsWithDelta(70.00, $result->toDecimal(), 0.001);
    }

    public function test_subtract_that_produces_negative_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/negativo/i');

        Money::fromDecimal(10.00)->subtract(Money::fromDecimal(50.00));
    }

    public function test_multiply_by_factor(): void
    {
        $money  = Money::fromDecimal(1000.00);
        $result = $money->multiply(3.0);

        $this->assertEqualsWithDelta(3000.00, $result->toDecimal(), 0.001);
    }

    public function test_multiply_by_negative_throws_exception(): void
    {
        $this->expectException(DomainException::class);

        Money::fromDecimal(100.00)->multiply(-1.0);
    }

    // -------------------------------------------------------------------------
    // Descuentos e Impuestos
    // -------------------------------------------------------------------------

    public function test_discount_amount_returns_correct_discount(): void
    {
        $money    = Money::fromDecimal(100.00);
        $discount = $money->discountAmount(15.0);

        $this->assertEqualsWithDelta(15.00, $discount->toDecimal(), 0.001);
    }

    public function test_apply_discount_returns_discounted_price(): void
    {
        $money  = Money::fromDecimal(100.00);
        $result = $money->applyDiscount(10.0);

        $this->assertEqualsWithDelta(90.00, $result->toDecimal(), 0.001);
    }

    public function test_discount_out_of_range_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/porcentaje/i');

        Money::fromDecimal(100.00)->discountAmount(101.0);
    }

    public function test_negative_discount_throws_exception(): void
    {
        $this->expectException(DomainException::class);

        Money::fromDecimal(100.00)->discountAmount(-5.0);
    }

    public function test_apply_tax_adds_correct_amount(): void
    {
        $money  = Money::fromDecimal(1000.00);
        $result = $money->applyTax(16.0); // IVA 16%

        $this->assertEqualsWithDelta(1160.00, $result->toDecimal(), 0.001);
    }

    public function test_negative_tax_throws_exception(): void
    {
        $this->expectException(DomainException::class);

        Money::fromDecimal(100.00)->applyTax(-1.0);
    }

    // -------------------------------------------------------------------------
    // Comparaciones
    // -------------------------------------------------------------------------

    public function test_equals_returns_true_for_same_cents(): void
    {
        $a = Money::fromCents(5000);
        $b = Money::fromCents(5000);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_cents(): void
    {
        $a = Money::fromCents(5000);
        $b = Money::fromCents(5001);

        $this->assertFalse($a->equals($b));
    }

    public function test_is_less_than(): void
    {
        $small = Money::fromCents(100);
        $big   = Money::fromCents(200);

        $this->assertTrue($small->isLessThan($big));
        $this->assertFalse($big->isLessThan($small));
    }

    public function test_is_greater_than(): void
    {
        $small = Money::fromCents(100);
        $big   = Money::fromCents(200);

        $this->assertTrue($big->isGreaterThan($small));
        $this->assertFalse($small->isGreaterThan($big));
    }

    public function test_is_positive(): void
    {
        $this->assertTrue(Money::fromCents(1)->isPositive());
        $this->assertFalse(Money::zero()->isPositive());
    }

    // -------------------------------------------------------------------------
    // Precisión de Punto Flotante
    // -------------------------------------------------------------------------

    public function test_float_precision_is_handled_correctly(): void
    {
        // 0.1 + 0.2 en float puro = 0.30000000000000004
        $a      = Money::fromDecimal(0.10);
        $b      = Money::fromDecimal(0.20);
        $result = $a->add($b);

        // Con representación en centavos: 10 + 20 = 30 → 0.30 exacto
        $this->assertSame(30, $result->getCents());
        $this->assertEqualsWithDelta(0.30, $result->toDecimal(), 0.001);
    }
}
