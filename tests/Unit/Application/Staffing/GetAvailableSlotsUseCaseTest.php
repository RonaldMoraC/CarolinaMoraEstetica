<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Staffing;

use PHPUnit\Framework\TestCase;
use App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Shared\ValueObjects\TimeRange;
use DateTimeImmutable;

class GetAvailableSlotsUseCaseTest extends TestCase
{
    public function testShouldReturnAvailableSlotsExcludingBlockedTimes(): void
    {
        $staffingRepo = $this->createMock(StaffingRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);

        // Mock: Profesional trabaja de 09:00 a 12:00
        $schedule = new \App\Domain\Staffing\Entities\WorkSchedule(1, 1, 1, '09:00:00', '12:00:00');
        $staffingRepo->method('findSchedulesByProfessional')->willReturn([$schedule]);
        $staffingRepo->method('findExceptions')->willReturn([]);

        // Mock: Una cita de 10:00 a 11:00
        $appointment = $this->createMock(\App\Domain\Booking\Entities\Appointment::class);
        $appointment->method('getScheduledTimestamp')->willReturn(
            new DateTimeImmutable('2026-10-26 10:00:00', new \DateTimeZone('America/Bogota'))
        );
        $appointment->method('getEstimatedEndTimestamp')->willReturn(
            new DateTimeImmutable('2026-10-26 11:00:00', new \DateTimeZone('America/Bogota'))
        );
        $appointmentRepo->method('getAppointmentsForDateRange')->willReturn([$appointment]);

        $useCase = new GetAvailableSlotsUseCase($staffingRepo, $appointmentRepo);

        $slots = $useCase->execute(
            1, 1,
            new DateTimeImmutable('2026-10-26 09:00:00', new \DateTimeZone('America/Bogota')),
            new DateTimeImmutable('2026-10-26 09:00:00', new \DateTimeZone('America/Bogota')),
            60
        );

        // Debería haber ranuras disponibles: 09:00-10:00 y 11:00-12:00
        $this->assertGreaterThan(0, count($slots));
        foreach ($slots as $slot) {
            $this->assertFalse($slot->overlapsWith(
                TimeRange::fromStrings('2026-10-26 10:00:00', '2026-10-26 11:00:00')
            ));
        }
    }
}
