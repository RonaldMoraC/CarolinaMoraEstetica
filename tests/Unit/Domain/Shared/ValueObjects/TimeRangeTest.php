<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\TimeRange;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Shared\ValueObjects\TimeRange
 */
final class TimeRangeTest extends TestCase
{
    private \DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new \DateTimeZone('America/Bogota');
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function dt(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, $this->tz);
    }

    // -------------------------------------------------------------------------
    // Construcción
    // -------------------------------------------------------------------------

    public function test_create_valid_range(): void
    {
        $range = TimeRange::create(
            $this->dt('2025-09-01 09:00:00'),
            $this->dt('2025-09-01 10:00:00')
        );

        $this->assertSame(
            '2025-09-01 09:00:00',
            $range->getStart()->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2025-09-01 10:00:00',
            $range->getEnd()->format('Y-m-d H:i:s')
        );
    }

    public function test_from_strings_creates_valid_range(): void
    {
        $range = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 11:30:00');

        $this->assertSame(150, $range->getDurationInMinutes());
    }

    public function test_start_equal_to_end_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/anterior/i');

        TimeRange::create(
            $this->dt('2025-09-01 10:00:00'),
            $this->dt('2025-09-01 10:00:00')
        );
    }

    public function test_start_after_end_throws_exception(): void
    {
        $this->expectException(DomainException::class);

        TimeRange::create(
            $this->dt('2025-09-01 11:00:00'),
            $this->dt('2025-09-01 09:00:00')
        );
    }

    public function test_from_strings_invalid_start_format_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/inicio/i');

        TimeRange::fromStrings('not-a-date', '2025-09-01 10:00:00');
    }

    public function test_from_strings_invalid_end_format_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/fin/i');

        TimeRange::fromStrings('2025-09-01 09:00:00', 'bad-date');
    }

    // -------------------------------------------------------------------------
    // Duración
    // -------------------------------------------------------------------------

    public function test_get_duration_in_minutes(): void
    {
        $range = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:30:00');

        $this->assertSame(90, $range->getDurationInMinutes());
    }

    public function test_get_duration_in_seconds(): void
    {
        $range = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 09:01:00');

        $this->assertSame(60, $range->getDurationInSeconds());
    }

    public function test_duration_spanning_multiple_hours(): void
    {
        $range = TimeRange::fromStrings('2025-09-01 08:00:00', '2025-09-01 17:00:00');

        $this->assertSame(540, $range->getDurationInMinutes());
    }

    // -------------------------------------------------------------------------
    // Solapamiento (overlapsWith) — Casos Críticos de Malla Horaria
    // -------------------------------------------------------------------------

    public function test_overlapping_ranges_return_true(): void
    {
        // [09:00 ─────── 11:00]
        //        [10:00 ─────── 12:00]
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 11:00:00');
        $b = TimeRange::fromStrings('2025-09-01 10:00:00', '2025-09-01 12:00:00');

        $this->assertTrue($a->overlapsWith($b));
        $this->assertTrue($b->overlapsWith($a));
    }

    public function test_contained_range_overlaps(): void
    {
        // [09:00 ─────────────── 12:00]
        //        [10:00 ── 11:00]
        $outer = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 12:00:00');
        $inner = TimeRange::fromStrings('2025-09-01 10:00:00', '2025-09-01 11:00:00');

        $this->assertTrue($outer->overlapsWith($inner));
        $this->assertTrue($inner->overlapsWith($outer));
    }

    public function test_identical_ranges_overlap(): void
    {
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $b = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');

        $this->assertTrue($a->overlapsWith($b));
    }

    public function test_adjacent_ranges_do_not_overlap(): void
    {
        // [09:00 ── 10:00][10:00 ── 11:00]  → Citas consecutivas, sin conflicto
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $b = TimeRange::fromStrings('2025-09-01 10:00:00', '2025-09-01 11:00:00');

        $this->assertFalse($a->overlapsWith($b));
        $this->assertFalse($b->overlapsWith($a));
    }

    public function test_disjoint_ranges_do_not_overlap(): void
    {
        // [09:00 ── 10:00]   [11:00 ── 12:00]
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $b = TimeRange::fromStrings('2025-09-01 11:00:00', '2025-09-01 12:00:00');

        $this->assertFalse($a->overlapsWith($b));
        $this->assertFalse($b->overlapsWith($a));
    }

    public function test_range_starting_exactly_when_other_ends_does_not_overlap(): void
    {
        $a = TimeRange::fromStrings('2025-09-01 08:00:00', '2025-09-01 09:00:00');
        $b = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');

        $this->assertFalse($a->overlapsWith($b));
    }

    // -------------------------------------------------------------------------
    // contains()
    // -------------------------------------------------------------------------

    public function test_contains_inner_range(): void
    {
        $outer = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 17:00:00');
        $inner = TimeRange::fromStrings('2025-09-01 10:00:00', '2025-09-01 12:00:00');

        $this->assertTrue($outer->contains($inner));
        $this->assertFalse($inner->contains($outer));
    }

    public function test_does_not_contain_partially_overlapping_range(): void
    {
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 11:00:00');
        $b = TimeRange::fromStrings('2025-09-01 10:00:00', '2025-09-01 12:00:00');

        $this->assertFalse($a->contains($b));
    }

    // -------------------------------------------------------------------------
    // includesMoment()
    // -------------------------------------------------------------------------

    public function test_includes_moment_within_range(): void
    {
        $range  = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 11:00:00');
        $moment = new \DateTimeImmutable('2025-09-01 10:00:00', $this->tz);

        $this->assertTrue($range->includesMoment($moment));
    }

    public function test_does_not_include_moment_at_end_boundary(): void
    {
        // El endpoint final es exclusivo: [start, end)
        $range  = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $moment = new \DateTimeImmutable('2025-09-01 10:00:00', $this->tz);

        $this->assertFalse($range->includesMoment($moment));
    }

    public function test_includes_moment_at_start_boundary(): void
    {
        $range  = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $moment = new \DateTimeImmutable('2025-09-01 09:00:00', $this->tz);

        $this->assertTrue($range->includesMoment($moment));
    }

    public function test_does_not_include_moment_outside_range(): void
    {
        $range  = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $moment = new \DateTimeImmutable('2025-09-01 11:00:00', $this->tz);

        $this->assertFalse($range->includesMoment($moment));
    }

    // -------------------------------------------------------------------------
    // Igualdad y representación
    // -------------------------------------------------------------------------

    public function test_equals_same_ranges(): void
    {
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $b = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');

        $this->assertTrue($a->equals($b));
    }

    public function test_not_equal_different_ranges(): void
    {
        $a = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:00:00');
        $b = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 11:00:00');

        $this->assertFalse($a->equals($b));
    }

    public function test_to_string_format(): void
    {
        $range = TimeRange::fromStrings('2025-09-01 09:00:00', '2025-09-01 10:30:00');

        $this->assertSame('[2025-09-01 09:00:00 → 2025-09-01 10:30:00]', (string) $range);
    }
}
