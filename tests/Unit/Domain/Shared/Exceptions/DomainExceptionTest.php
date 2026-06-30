<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Shared\Exceptions\DomainException
 */
final class DomainExceptionTest extends TestCase
{
    public function test_it_can_be_created_with_message_only(): void
    {
        $exception = new DomainException('Error de negocio.');

        $this->assertSame('Error de negocio.', $exception->getMessage());
        $this->assertSame('Domain', $exception->getDomain());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_it_stores_the_domain_context(): void
    {
        $exception = new DomainException('Monto inválido.', 'Money');

        $this->assertSame('Money', $exception->getDomain());
    }

    public function test_it_stores_the_error_code(): void
    {
        $exception = new DomainException('Error con código.', 'TimeRange', 422);

        $this->assertSame(422, $exception->getCode());
    }

    public function test_it_chains_a_previous_exception(): void
    {
        $previous  = new \RuntimeException('Causa raíz.');
        $exception = new DomainException('Error encadenado.', 'UuidVO', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_from_domain_factory_creates_exception_with_context(): void
    {
        $exception = DomainException::fromDomain('UUID inválido.', 'UuidVO');

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertSame('UUID inválido.', $exception->getMessage());
        $this->assertSame('UuidVO', $exception->getDomain());
    }

    public function test_it_extends_native_domain_exception(): void
    {
        $exception = new DomainException('Error.');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    public function test_it_can_be_caught_as_native_domain_exception(): void
    {
        $this->expectException(\DomainException::class);

        throw new DomainException('Error capturado como nativo.');
    }
}
