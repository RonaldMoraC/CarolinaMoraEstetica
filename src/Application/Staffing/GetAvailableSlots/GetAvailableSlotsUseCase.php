<?php

declare(strict_types=1);

namespace App\Application\Staffing\GetAvailableSlots;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\TimeRange;
use App\Domain\Staffing\Entities\ScheduleException;
use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;

final class GetAvailableSlotsUseCase
{
    public function __construct(private readonly StaffingRepositoryInterface $staffingRepository) {}

    /**
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function execute(int $professionalId, int $branchId, string $date, int $slotDurationMinutes = 60): array
    {
        $profile = $this->staffingRepository->findProfessionalProfileById($professionalId);
        if ($profile === null || ($profile['operational_status'] ?? 'INACTIVE') !== 'ACTIVE') {
            throw new DomainException('El profesional no está activo para recibir citas.', 'Staffing');
        }

        $schedules = $this->staffingRepository->getWorkSchedules($professionalId, $branchId);
        if ($schedules === []) {
            return [];
        }

        $targetDate = new \DateTimeImmutable($date, new \DateTimeZone('America/Bogota'));
        $dayOfWeek = (int) $targetDate->format('w');

        $matchingSchedule = null;
        foreach ($schedules as $schedule) {
            if ((int) $schedule->getDayOfWeek() === $dayOfWeek) {
                $matchingSchedule = $schedule;
                break;
            }
        }

        if ($matchingSchedule === null) {
            return [];
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $matchingSchedule->getStartTime(), new \DateTimeZone('America/Bogota'));
        $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $matchingSchedule->getEndTime(), new \DateTimeZone('America/Bogota'));

        if ($start === false || $end === false) {
            throw new DomainException('No fue posible construir el rango horario del profesional.', 'Staffing');
        }

        $slots = [];
        $cursor = $start;
        while ($cursor < $end) {
            $slotEnd = $cursor->modify('+' . $slotDurationMinutes . ' minutes');
            if ($slotEnd > $end) {
                break;
            }

            $slotRange = TimeRange::create($cursor, $slotEnd);
            $exceptions = $this->staffingRepository->getExceptionsInTimeRange(
                $professionalId,
                $slotRange->getStart()->format('Y-m-d H:i:s'),
                $slotRange->getEnd()->format('Y-m-d H:i:s')
            );

            $isBlocked = false;
            foreach ($exceptions as $exception) {
                if ($exception->getTimeRange()->overlapsWith($slotRange)) {
                    $isBlocked = true;
                    break;
                }
            }

            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'available' => !$isBlocked,
            ];

            $cursor = $slotEnd;
        }

        return $slots;
    }
}
