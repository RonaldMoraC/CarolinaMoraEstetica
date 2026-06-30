<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\UuidVO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Shared\ValueObjects\UuidVO
 */
final class UuidVOTest extends TestCase
{
    private const VALID_UUID_V4 = '550e8400-e29b-41d4-a716-446655440000';

    // -------------------------------------------------------------------------
    // Generación
    // -------------------------------------------------------------------------

    public function test_generate_creates_valid_uuid_v4(): void
    {
        $uuid = UuidVO::generate();

        // Verificar formato RFC 4122 v4
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString()
        );
    }

    public function test_generate_creates_unique_uuids(): void
    {
        $uuid1 = UuidVO::generate();
        $uuid2 = UuidVO::generate();

        $this->assertFalse($uuid1->equals($uuid2));
    }

    public function test_generate_returns_32_char_hex(): void
    {
        $uuid = UuidVO::generate();

        $this->assertSame(32, strlen($uuid->toHex()));
    }

    // -------------------------------------------------------------------------
    // Validación desde string
    // -------------------------------------------------------------------------

    public function test_from_string_accepts_valid_uuid_v4(): void
    {
        $uuid = UuidVO::fromString(self::VALID_UUID_V4);

        $this->assertSame(self::VALID_UUID_V4, $uuid->toString());
    }

    public function test_from_string_normalizes_to_lowercase(): void
    {
        $upper = strtoupper(self::VALID_UUID_V4);
        $uuid  = UuidVO::fromString($upper);

        $this->assertSame(self::VALID_UUID_V4, $uuid->toString());
    }

    public function test_from_string_trims_whitespace(): void
    {
        $uuid = UuidVO::fromString('  ' . self::VALID_UUID_V4 . '  ');

        $this->assertSame(self::VALID_UUID_V4, $uuid->toString());
    }

    /** @dataProvider provideInvalidUuids */
    public function test_from_string_rejects_invalid_format(string $invalid): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/UUID v4/i');

        UuidVO::fromString($invalid);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideInvalidUuids(): array
    {
        return [
            'empty string'            => [''],
            'random string'           => ['not-a-uuid'],
            'uuid v1 (wrong version)' => ['550e8400-e29b-11d4-a716-446655440000'],
            'uuid v3 (wrong version)' => ['550e8400-e29b-31d4-a716-446655440000'],
            'missing hyphens'         => ['550e8400e29b41d4a716446655440000'],
            'wrong variant byte'      => ['550e8400-e29b-41d4-c716-446655440000'],
            'too short'               => ['550e8400-e29b-41d4-a716'],
            'too long'                => ['550e8400-e29b-41d4-a716-446655440000-extra'],
        ];
    }

    // -------------------------------------------------------------------------
    // Conversión
    // -------------------------------------------------------------------------

    public function test_to_hex_removes_hyphens(): void
    {
        $uuid = UuidVO::fromString(self::VALID_UUID_V4);

        $this->assertSame('550e8400e29b41d4a716446655440000', $uuid->toHex());
    }

    public function test_to_string_magic_method_works(): void
    {
        $uuid = UuidVO::fromString(self::VALID_UUID_V4);

        $this->assertSame(self::VALID_UUID_V4, (string) $uuid);
    }

    // -------------------------------------------------------------------------
    // Comparación
    // -------------------------------------------------------------------------

    public function test_equals_returns_true_for_same_uuid(): void
    {
        $a = UuidVO::fromString(self::VALID_UUID_V4);
        $b = UuidVO::fromString(self::VALID_UUID_V4);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_uuids(): void
    {
        $a = UuidVO::generate();
        $b = UuidVO::generate();

        $this->assertFalse($a->equals($b));
    }

    public function test_equals_is_case_insensitive(): void
    {
        $lower = UuidVO::fromString(self::VALID_UUID_V4);
        $upper = UuidVO::fromString(strtoupper(self::VALID_UUID_V4));

        $this->assertTrue($lower->equals($upper));
    }
}
