<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Repositories;

use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Entities\ScheduleException;

interface StaffingRepositoryInterface
{
    /**
     * Persiste o actualiza una malla horaria ordinaria.
     */
    public function saveSchedule(WorkSchedule $schedule): void;

    /**
     * Persiste una excepción o bloqueo extraordinario.
     */
    public function saveException(ScheduleException $exception): void;

    /**
     * Recupera todos los horarios activos de un profesional para una sucursal.
     * @return WorkSchedule[]
     */
    public function findSchedulesByProfessional(int $professionalId, int $branchId): array;

    /**
     * Recupera las excepciones dentro de un rango de fechas para un profesional.
     * @return ScheduleException[]
     */
    public function findExceptions(int $professionalId, \DateTimeImmutable $start, \DateTimeImmutable $end): array;
}
