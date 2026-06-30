<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Entities;

use App\Domain\Shared\ValueObjects\TimeRange;

/**
 * ScheduleException — Excepción de Agenda (Anomalías)
 *
 * Modela un bloqueo de disponibilidad temporal o por todo el día para un profesional
 * debido a vacaciones, licencias médicas o emergencias.
 */
class ScheduleException
{
    private const VALID_REASONS = ['VACATION', 'SICK_LEAVE', 'PERSONAL_TIME', 'EMERGENCY'];

    /**
     * @param int       $professionalId Identificador del profesional (bigint).
     * @param TimeRange $timeRange      Rango de tiempo de la excepción.
     * @param string    $blockingReason Razón justificada de la excepción.
     * @param bool      $isFullDayBlock Bandera de bloqueo total de día.
     * @param ?int      $id             ID auto-incremental (null si es nuevo).
     */
    public function __construct(
        private readonly int       $professionalId,
        private readonly TimeRange $timeRange,
        private readonly string    $blockingReason,
        private readonly bool      $isFullDayBlock = false,
        private readonly ?int      $id = null
    ) {
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (!in_array($this->blockingReason, self::VALID_REASONS, true)) {
            throw new \App\Domain\Shared\Exceptions\DomainException(
                'Motivo de bloqueo inválido. Permitidos: ' . implode(', ', self::VALID_REASONS),
                'ScheduleException'
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

    public function getTimeRange(): TimeRange
    {
        return $this->timeRange;
    }

    public function getBlockingReason(): string
    {
        return $this->blockingReason;
    }

    public function isFullDayBlock(): bool
    {
        return $this->isFullDayBlock;
    }
}
