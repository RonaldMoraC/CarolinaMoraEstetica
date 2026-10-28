<?php

declare(strict_types=1);

namespace App\Infrastructure\Staffing\Persistence;

use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Entities\ScheduleException;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use PDO;

final class PdoStaffingRepository implements StaffingRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {}

    public function saveSchedule(WorkSchedule $schedule): void
    {
        $sql = "INSERT INTO work_schedule
                (branch_id, professional_profile_id, day_of_week, start_time, end_time, lunch_start_time, lunch_end_time)
                VALUES (:branch_id, :prof_id, :day, :start, :end, :lunch_start, :lunch_end)
                ON DUPLICATE KEY UPDATE
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                lunch_start_time = VALUES(lunch_start_time),
                lunch_end_time = VALUES(lunch_end_time)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':branch_id', $schedule->getBranchId(), PDO::PARAM_INT);
        $stmt->bindValue(':prof_id', $schedule->getProfessionalId(), PDO::PARAM_INT);
        $stmt->bindValue(':day', $schedule->getDayOfWeek(), PDO::PARAM_INT);
        $stmt->bindValue(':start', $schedule->getStartTime());
        $stmt->bindValue(':end', $schedule->getEndTime());
        $stmt->bindValue(':lunch_start', $schedule->getLunchStartTime());
        $stmt->bindValue(':lunch_end', $schedule->getLunchEndTime());
        $stmt->execute();
    }

    public function saveException(ScheduleException $exception): void
    {
        // Nota: Asumimos que ScheduleException ya existe con sus getters correspondientes
        $sql = "INSERT INTO schedule_exception
                (professional_profile_id, start_datetime, end_datetime, reason)
                VALUES (:prof_id, :start, :end, :reason)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':prof_id', $exception->getProfessionalId(), PDO::PARAM_INT);
        $stmt->bindValue(':start', $exception->getTimeRange()->getStart()->format('Y-m-d H:i:s'));
        $stmt->bindValue(':end', $exception->getTimeRange()->getEnd()->format('Y-m-d H:i:s'));
        $stmt->bindValue(':reason', $exception->getBlockingReason());
        $stmt->execute();
    }

    public function findSchedulesByProfessional(int $professionalId, int $branchId): array
    {
        $sql = "SELECT * FROM work_schedule
                WHERE professional_profile_id = :prof_id AND branch_id = :branch_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new WorkSchedule(
                (int)$row['professional_profile_id'],
                (int)$row['branch_id'],
                (int)$row['day_of_week'],
                $row['start_time'],
                $row['end_time'],
                $row['lunch_start_time'],
                $row['lunch_end_time'],
                (int)$row['work_schedule_id']
            );
        }
        return $results;
    }

    public function findExceptions(int $professionalId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sql = "SELECT * FROM schedule_exception
                WHERE professional_profile_id = :prof_id
                AND start_datetime < :end_date
                AND end_datetime > :start_date";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $start->format('Y-m-d H:i:s'));
        $stmt->bindValue(':end_date', $end->format('Y-m-d H:i:s'));
        $stmt->execute();

        // Mapeo similar a findSchedules...
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Simplificado para continuar el flujo
    }

    public function lockProfessionalWorkSchedule(int $professionalId, int $branchId, int $dayOfWeek): ?array
    {
        $sql = "SELECT * FROM work_schedule 
                WHERE professional_profile_id = :prof_id 
                  AND branch_id = :branch_id 
                  AND day_of_week = :day
                FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
        $stmt->bindValue(':day', $dayOfWeek, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getExceptionsInTimeRange(int $professionalId, string $startTimestamp, string $endTimestamp): array
    {
        $sql = "SELECT * FROM schedule_exception
                WHERE professional_profile_id = :prof_id
                  AND start_datetime < :end_ts
                  AND end_datetime > :start_ts";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':start_ts', $startTimestamp, PDO::PARAM_STR);
        $stmt->bindValue(':end_ts', $endTimestamp, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
