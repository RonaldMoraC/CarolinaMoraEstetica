<?php

declare(strict_types=1);

namespace App\Application\Staffing\GetAvailableSlots;

use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Shared\ValueObjects\TimeRange;
use DateTimeImmutable;
use DateTimeZone;
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
     * @param int $serviceDurationMinutes Duración del servicio solicitado (mínimo 15 min)
     * @return TimeRange[]
     */
    public function execute(
        int $professionalId,
        int $branchId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $serviceDurationMinutes = 30
    ): array {
        if ($serviceDurationMinutes <= 0) {
            $serviceDurationMinutes = 30;
        }

        $schedules = $this->staffingRepo->findSchedulesByProfessional($professionalId, $branchId);

        // Si el profesional no tiene horarios personalizados registrados, aplicar horario estándar de la estética:
        // Lunes a Sábado de 08:00 a 18:00 con receso de almuerzo de 13:00 a 14:00 (Domingos cerrado)
        if (empty($schedules)) {
            $schedules = [];
            for ($d = 1; $d <= 6; $d++) { // Lunes (1) a Sábado (6)
                $schedules[] = new \App\Domain\Staffing\Entities\WorkSchedule(
                    $professionalId,
                    $branchId,
                    $d,
                    '08:00:00',
                    '18:00:00',
                    '13:00:00',
                    '14:00:00'
                );
            }
        }

        $exceptions = $this->staffingRepo->findExceptions($professionalId, $startDate, $endDate);
        $appointments = $this->appointmentRepo->getAppointmentsForDateRange(
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s'),
            $professionalId
        );

        $availableSlots = [];
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('now', $tz);

        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int)$currentDate->format('w'); // 0 (Domingo) a 6 (Sábado)

            foreach ($schedules as $schedule) {
                if ($schedule->getDayOfWeek() === $dayOfWeek) {
                    $dayStart = $currentDate->format('Y-m-d') . ' ' . $schedule->getStartTime();
                    $dayEnd = $currentDate->format('Y-m-d') . ' ' . $schedule->getEndTime();

                    $fullRange = TimeRange::fromStrings($dayStart, $dayEnd);

                    // Generar posibles ranuras
                    $slots = $this->generatePotentialSlots($fullRange, $serviceDurationMinutes);

                    // Verificar receso de almuerzo
                    $lunchRange = null;
                    if ($schedule->getLunchStartTime() !== null && $schedule->getLunchEndTime() !== null) {
                        $lunchRange = TimeRange::fromStrings(
                            $currentDate->format('Y-m-d') . ' ' . $schedule->getLunchStartTime(),
                            $currentDate->format('Y-m-d') . ' ' . $schedule->getLunchEndTime()
                        );
                    }

                    foreach ($slots as $slot) {
                        // Descartar si coincide con el horario de almuerzo
                        if ($lunchRange !== null && $slot->overlapsWith($lunchRange)) {
                            continue;
                        }

                        // Descartar horarios pasados si la cita se está consultando para el día de hoy
                        if ($currentDate->format('Y-m-d') === $now->format('Y-m-d') && $slot->getStart() <= $now) {
                            continue;
                        }

                        // Descartar si colisiona con excepciones o citas existentes
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

        // Incremento de 30 minutos entre opciones de ranura
        $stepMinutes = 30;

        while ($current->add(new DateInterval("PT{$duration}M")) <= $end) {
            $slotEnd = $current->add(new DateInterval("PT{$duration}M"));
            $slots[] = TimeRange::create($current, $slotEnd);
            $current = $current->add(new DateInterval("PT{$stepMinutes}M"));
        }
        return $slots;
    }

    private function isBlocked(TimeRange $slot, array $exceptions, array $appointments): bool
    {
        foreach ($exceptions as $ex) {
            $exRange = is_array($ex)
                ? TimeRange::fromStrings((string)$ex['start_datetime'], (string)$ex['end_datetime'])
                : $ex->getTimeRange();
            if ($slot->overlapsWith($exRange)) return true;
        }

        foreach ($appointments as $app) {
            if (is_array($app)) {
                $status = strtoupper((string)($app['appointment_status'] ?? ''));
                if (in_array($status, ['CANCELLED', 'NOSHOW'], true)) {
                    continue;
                }
                $appRange = TimeRange::fromStrings((string)$app['scheduled_timestamp'], (string)$app['estimated_end_timestamp']);
            } elseif ($app instanceof \App\Domain\Booking\Entities\Appointment) {
                if (in_array($app->getAppointmentStatus(), ['CANCELLED', 'NOSHOW'], true)) {
                    continue;
                }
                $appRange = TimeRange::create($app->getScheduledTimestamp(), $app->getEstimatedEndTimestamp());
            } else {
                $appRange = TimeRange::create($app->getScheduledTimestamp(), $app->getEstimatedEndTimestamp());
            }

            if ($slot->overlapsWith($appRange)) return true;
        }

        return false;
    }
}
