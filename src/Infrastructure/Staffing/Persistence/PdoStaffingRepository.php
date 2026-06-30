<?php

declare(strict_types=1);

namespace App\Infrastructure\Staffing\Persistence;

use App\Domain\Shared\ValueObjects\TimeRange;
use App\Domain\Staffing\Entities\ScheduleException;
use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use PDO;
use PDOException;

/**
 * PdoStaffingRepository
 *
 * Implementación PDO de la capa de persistencia para el Staffing.
 * Cumple con el requerimiento de usar bindValue estrictamente y 
 * prevenir inyección SQL (Zero-Trust).
 */
final class PdoStaffingRepository implements StaffingRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function saveWorkSchedule(WorkSchedule $schedule): void
    {
        // En caso de que se envíe el ID auto incremental, podríamos intentar hacer un UPDATE directo,
        // pero la lógica es que por sucursal, día de la semana y profesional solo hay un bloque.
        // Dado que la llave única/primaria no contempla day_of_week, si hay constraint idx_schedule_matrix unique,
        // esto inserta. Si no, actualiza por el PK work_schedule_id (si lo hubiere).
        // En MySQL, lo más seguro para guardar bloques es borrar y reinsertar o actualizar según el ID.
        // El UseCase usará deleteWorkSchedules() antes de insertar masivamente, por lo que aquí podemos hacer un INSERT limpio.
        
        $sql = "INSERT INTO work_schedule 
                (professional_profile_id, branch_id, day_of_week, start_time, end_time, lunch_start_time, lunch_end_time)
                VALUES 
                (:prof_id, :branch_id, :day_of_week, :start_time, :end_time, :lunch_start, :lunch_end)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prof_id', $schedule->getProfessionalId(), PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $schedule->getBranchId(), PDO::PARAM_INT);
        $stmt->bindValue(':day_of_week', $schedule->getDayOfWeek(), PDO::PARAM_INT);
        $stmt->bindValue(':start_time', $schedule->getStartTime(), PDO::PARAM_STR);
        $stmt->bindValue(':end_time', $schedule->getEndTime(), PDO::PARAM_STR);
        
        $lunchStart = $schedule->getLunchStartTime();
        $lunchEnd = $schedule->getLunchEndTime();
        
        if ($lunchStart !== null) {
            $stmt->bindValue(':lunch_start', $lunchStart, PDO::PARAM_STR);
            $stmt->bindValue(':lunch_end', $lunchEnd, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':lunch_start', null, PDO::PARAM_NULL);
            $stmt->bindValue(':lunch_end', null, PDO::PARAM_NULL);
        }

        $stmt->execute();
    }

    public function deleteWorkSchedules(int $professionalId, int $branchId): void
    {
        $sql = "DELETE FROM work_schedule 
                WHERE professional_profile_id = :prof_id 
                AND branch_id = :branch_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getWorkSchedules(int $professionalId, int $branchId): array
    {
        $sql = "SELECT work_schedule_id, professional_profile_id, branch_id, day_of_week, 
                       start_time, end_time, lunch_start_time, lunch_end_time
                FROM work_schedule
                WHERE professional_profile_id = :prof_id AND branch_id = :branch_id
                ORDER BY day_of_week ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $schedules = [];

        foreach ($rows as $row) {
            $schedules[] = new WorkSchedule(
                (int) $row['professional_profile_id'],
                (int) $row['branch_id'],
                (int) $row['day_of_week'],
                $row['start_time'],
                $row['end_time'],
                $row['lunch_start_time'] ?? null,
                $row['lunch_end_time'] ?? null,
                (int) $row['work_schedule_id']
            );
        }

        return $schedules;
    }

    public function saveScheduleException(ScheduleException $exception): void
    {
        $sql = "INSERT INTO schedule_exception 
                (professional_profile_id, start_timestamp, end_timestamp, blocking_reason, is_full_day_block)
                VALUES 
                (:prof_id, :start_ts, :end_ts, :reason, :is_full_day)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prof_id', $exception->getProfessionalId(), PDO::PARAM_INT);
        $stmt->bindValue(':start_ts', $exception->getTimeRange()->getStart()->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':end_ts', $exception->getTimeRange()->getEnd()->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':reason', $exception->getBlockingReason(), PDO::PARAM_STR);
        $stmt->bindValue(':is_full_day', $exception->isFullDayBlock() ? 1 : 0, PDO::PARAM_INT);

        $stmt->execute();
    }

    public function getExceptionsInTimeRange(int $professionalId, string $startTimestamp, string $endTimestamp): array
    {
        // Dos rangos [A_start, A_end] y [B_start, B_end] se solapan si A_start < B_end y B_start < A_end
        // En SQL: start_timestamp < :end_ts AND end_timestamp > :start_ts
        $sql = "SELECT exception_id, professional_profile_id, start_timestamp, end_timestamp, 
                       blocking_reason, is_full_day_block
                FROM schedule_exception
                WHERE professional_profile_id = :prof_id
                AND start_timestamp < :end_ts
                AND end_timestamp > :start_ts";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
        $stmt->bindValue(':start_ts', $startTimestamp, PDO::PARAM_STR);
        $stmt->bindValue(':end_ts', $endTimestamp, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exceptions = [];

        foreach ($rows as $row) {
            $timeRange = TimeRange::fromStrings($row['start_timestamp'], $row['end_timestamp']);
            
            $exceptions[] = new ScheduleException(
                (int) $row['professional_profile_id'],
                $timeRange,
                $row['blocking_reason'],
                (bool) $row['is_full_day_block'],
                (int) $row['exception_id']
            );
        }

        return $exceptions;
    }

    /**
     * @inheritDoc
     */
    public function findProfessionalProfileById(int $professionalId): ?array
    {
        $sql = "SELECT pp.professional_profile_id, u.first_name, u.last_name, u.email,
                       pp.operational_status, pp.public_biography
                FROM professional_profile pp
                JOIN user u ON pp.professional_profile_id = u.user_id
                WHERE pp.professional_profile_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $professionalId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @inheritDoc
     */
    public function getProfessionalServices(int $professionalId): array
    {
        $sql = "SELECT ps.service_id, s.name
                FROM professional_service ps
                JOIN service s ON ps.service_id = s.service_id
                WHERE ps.professional_profile_id = :professionalId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':professionalId', $professionalId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function syncProfessionalServices(int $professionalId, array $serviceIds): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Skill 10: Sentencias preparadas para la eliminación
            $deleteSql = "DELETE FROM professional_service WHERE professional_profile_id = :professionalId";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->bindValue(':professionalId', $professionalId, PDO::PARAM_INT);
            $deleteStmt->execute();

            // Skill 10: Inserción de nuevos servicios usando bindValue en bucle
            if (!empty($serviceIds)) {
                $insertSql = "INSERT INTO professional_service (professional_profile_id, service_id) VALUES (:prof_id, :serv_id)";
                $insertStmt = $this->pdo->prepare($insertSql);
                
                foreach ($serviceIds as $serviceId) {
                    $insertStmt->bindValue(':prof_id', $professionalId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':serv_id', $serviceId, PDO::PARAM_INT);
                    $insertStmt->execute();
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Re-lanzamos para que sea capturada por el Caso de Uso o Handler Global
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function findProfessionalsByService(int $serviceId): array
    {
        $sql = "SELECT pp.professional_profile_id, u.first_name, u.last_name, u.email,
                       pp.operational_status, pp.public_biography
                FROM professional_profile pp
                JOIN user u ON pp.professional_profile_id = u.user_id
                JOIN professional_service ps ON pp.professional_profile_id = ps.professional_profile_id
                WHERE ps.service_id = :service_id
                AND u.account_status = 'ACTIVE'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function findAllProfessionals(): array
    {
        $sql = "SELECT pp.professional_profile_id, u.first_name, u.last_name, u.email,
                       pp.operational_status, pp.public_biography
                FROM professional_profile pp
                JOIN user u ON pp.professional_profile_id = u.user_id
                WHERE u.account_status = 'ACTIVE'
                AND pp.operational_status = 'ACTIVE'
                ORDER BY u.first_name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'professional_profile_id' => (int) $row['professional_profile_id'],
                'first_name'              => $row['first_name'],
                'last_name'               => $row['last_name'],
                'email'                   => $row['email'],
                'operational_status'      => $row['operational_status'],
                'public_biography'        => $row['public_biography'] ?? '',
            ];
        }, $rows);
    }
}
