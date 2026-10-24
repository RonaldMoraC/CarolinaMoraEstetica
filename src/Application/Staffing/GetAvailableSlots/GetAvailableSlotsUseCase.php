<?php

declare(strict_types=1);

namespace App\Application\Staffing\GetAvailableSlots;

use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Shared\ValueObjects\TimeRange;
use DateTimeImmutable;
use DateInterval;

final class GetAvailableSlotsUseCase
{
    public function __construct(
        private readonly StaffingRepositoryInterface $staffingRepo,
        private readonly AppointmentRepositoryInterface $appointmentRepo
    ) {}

    /**
     * Calcula las ranuras disponibles para un profesional en un rango de fechas.
     *
     * @param int $professionalId
     * @param int $branchId
     * @param DateTimeImmutable $startDate
     * @param DateTimeImmutable $endDate
     * @param int $serviceDurationMinutes Duración del servicio solicitado
     * @return TimeRange[]
     */
    public function execute(
        int $professionalId,
        int $branchId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $serviceDurationMinutes
    ): array {
        $schedules = $this->staffingRepo->findSchedulesByProfessional($professionalId, $branchId);
        $exceptions = $this->staffingRepo->findExceptions($professionalId, $startDate, $endDate);
        $appointments = $this->appointmentRepo->getAppointmentsForDateRange(
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s'),
            $professionalId
        );

        $availableSlots = [];
        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int)$currentDate->format('w');

            foreach ($schedules as $schedule) {
                if ($schedule->getDayOfWeek() === $dayOfWeek) {
                    $dayStart = $currentDate->format('Y-m-d') . ' ' . $schedule->getStartTime();
                    $dayEnd = $currentDate->format('Y-m-d') . ' ' . $schedule->getEndTime();

                    $fullRange = TimeRange::fromStrings($dayStart, $dayEnd);

                    // Lógica: Generar ranuras de duración $serviceDurationMinutes
                    // dentro de $fullRange, descontando excepciones y citas.
                    $slots = $this->generatePotentialSlots($fullRange, $serviceDurationMinutes);

                    foreach ($slots as $slot) {
                        if (!$this->isBlocked($slot, $exceptions, $appointments)) {
                            $availableSlots[] = $slot;
                        }
                    }
                }
            }
            $currentDate = $currentDate->add(new DateInterval('P1D'));
        }

        return $availableSlots;
    }

    private function generatePotentialSlots(TimeRange $range, int $duration): array
    {
        $slots = [];
        $current = $range->getStart();
        $end = $range->getEnd();

        while ($current->add(new DateInterval("PT{$duration}M")) <= $end) {
            $slotEnd = $current->add(new DateInterval("PT{$duration}M"));
            $slots[] = TimeRange::create($current, $slotEnd);
            $current = $current->add(new DateInterval('PT15M')); // Paso de 15 minutos
        }
        return $slots;
    }

    private function isBlocked(TimeRange $slot, array $exceptions, array $appointments): bool
    {
        foreach ($exceptions as $ex) {
            if ($slot->overlapsWith($ex->getTimeRange())) return true;
        }
        foreach ($appointments as $app) {
            $appRange = TimeRange::create($app->getScheduledTimestamp(), $app->getEstimatedEndTimestamp());
            if ($slot->overlapsWith($appRange)) return true;
        }
        return false;
    }
}
