<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Entities;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * WorkSchedule — Bloque de Malla Horaria Ordinaria
 *
 * Representa la jornada laboral repetitiva de un profesional en una
 * sucursal para un día de la semana específico.
 */
class WorkSchedule
{
    /**
     * @param int     $professionalId Identificador del profesional (bigint).
     * @param int     $branchId       ID numérico de la sucursal.
     * @param int     $dayOfWeek      Día de la semana (0 = Domingo, 6 = Sábado).
     * @param string  $startTime      Hora de inicio (H:i:s).
     * @param string  $endTime        Hora de fin (H:i:s).
     * @param ?string $lunchStartTime Hora de inicio de comida o null.
     * @param ?string $lunchEndTime   Hora de fin de comida o null.
     * @param ?int    $id             ID auto-incremental (null si es nuevo).
     */
    public function __construct(
        private readonly int     $professionalId,
        private readonly int     $branchId,
        private readonly int     $dayOfWeek,
        private readonly string  $startTime,
        private readonly string  $endTime,
        private readonly ?string $lunchStartTime = null,
        private readonly ?string $lunchEndTime = null,
        private readonly ?int    $id = null
    ) {
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if ($this->dayOfWeek < 0 || $this->dayOfWeek > 6) {
            throw new DomainException(
                'El día de la semana debe estar entre 0 (Domingo) y 6 (Sábado).',
                'WorkSchedule'
            );
        }

        if ($this->startTime >= $this->endTime) {
            throw new DomainException(
                'La hora de fin (' . $this->endTime . ') debe ser posterior a la de inicio (' . $this->startTime . ').',
                'WorkSchedule'
            );
        }

        if ($this->lunchStartTime !== null && $this->lunchEndTime !== null) {
            if ($this->lunchStartTime >= $this->lunchEndTime) {
                throw new DomainException(
                    'La hora de fin de almuerzo debe ser posterior al inicio.',
                    'WorkSchedule'
                );
            }
            if ($this->lunchStartTime < $this->startTime || $this->lunchEndTime > $this->endTime) {
                throw new DomainException(
                    'El bloque de almuerzo debe estar contenido dentro de la jornada laboral.',
                    'WorkSchedule'
                );
            }
        } elseif ($this->lunchStartTime !== null || $this->lunchEndTime !== null) {
            throw new DomainException(
                'Se deben especificar ambas horas de comida o ninguna.',
                'WorkSchedule'
            );
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfessionalId(): int
    {
        return $this->professionalId;
    }

    public function getBranchId(): int
    {
        return $this->branchId;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function getLunchStartTime(): ?string
    {
        return $this->lunchStartTime;
    }

    public function getLunchEndTime(): ?string
    {
        return $this->lunchEndTime;
    }
}
