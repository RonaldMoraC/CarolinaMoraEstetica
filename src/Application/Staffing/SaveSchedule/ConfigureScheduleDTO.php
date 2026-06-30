<?php
declare(strict_types=1);

namespace App\Application\Staffing\SaveSchedule;

/**
 * ConfigureScheduleDTO
 *
 * Datos de entrada para configurar los horarios semanales de un profesional.
 */
final readonly class ConfigureScheduleDTO
{
    /**
     * @param int   $professionalId
     * @param int   $branchId
     * @param array $schedules Array de { day_of_week: int, active: bool, start_time: ?string, end_time: ?string }
     */
    public function __construct(
        public int   $professionalId,
        public int   $branchId,
        public array $schedules
    ) {}
}