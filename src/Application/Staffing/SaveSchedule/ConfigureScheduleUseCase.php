<?php
declare(strict_types=1);

namespace App\Application\Staffing\SaveSchedule;

use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;

/**
 * ConfigureScheduleUseCase
 *
 * Guarda la malla horaria semanal de un profesional:
 * 1. Elimina los horarios existentes para esa combinación profesional+sucursal
 * 2. Inserta los nuevos horarios para los días activos
 */
final class ConfigureScheduleUseCase
{
    public function __construct(
        private readonly StaffingRepositoryInterface $staffingRepository,
        private readonly SystemAuditLogRepository    $auditLog
    ) {}

    /**
     * @return array{ created: int, deleted: int }
     */
    public function execute(ConfigureScheduleDTO $dto, int $actorId): array
    {
        // Separar días activos e inactivos
        $activeDays   = [];
        $inactiveDays = [];

        foreach ($dto->schedules as $day) {
            if (!empty($day['active'])) {
                $activeDays[] = $day;
            } else {
                $inactiveDays[] = $day['day_of_week'];
            }
        }

        $createdCount = 0;

        // Eliminar schedules existentes y reinsertar
        $this->staffingRepository->deleteWorkSchedules($dto->professionalId, $dto->branchId);

        foreach ($activeDays as $day) {
            $schedule = new WorkSchedule(
                professionalId: $dto->professionalId,
                branchId:       $dto->branchId,
                dayOfWeek:      (int) $day['day_of_week'],
                startTime:      $day['start_time'],
                endTime:        $day['end_time']
            );

            $this->staffingRepository->saveWorkSchedule($schedule);
            $createdCount++;
        }

        $this->auditLog->insert(
            actorId:    $actorId,
            action:     'SCHEDULE_SAVE',
            entityType: 'work_schedule',
            entityId:   (string) $dto->professionalId,
            oldValues:  [],
            newValues:  [
                'professional_profile_id' => $dto->professionalId,
                'branch_id'              => $dto->branchId,
                'active_days'            => count($activeDays),
                'inactive_days'          => $inactiveDays,
            ]
        );

        return [
            'created' => $createdCount,
            'deleted' => count($dto->schedules) - count($activeDays),
        ];
    }
}