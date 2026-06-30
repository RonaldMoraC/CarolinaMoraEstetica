<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Entities;

/**
 * ProfessionalProfile — Perfil Operativo del Profesional
 *
 * Representa al especialista en la capa de agendamiento y staffing.
 * Gestiona el estado operativo (activo/inactivo) y la tasa de comisión
 * aplicable a los servicios que realiza.
 */
class ProfessionalProfile
{
    /**
     * @param int    $id                     ID auto-incremental (bigint en DB).
     * @param float  $serviceCommissionRate  Porcentaje de comisión [0.00, 100.00].
     * @param string $operationalStatus      Estado actual (ej. 'ACTIVE', 'INACTIVE').
     */
    public function __construct(
        private readonly int    $id,
        private readonly float  $serviceCommissionRate,
        private readonly string $operationalStatus
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getServiceCommissionRate(): float
    {
        return $this->serviceCommissionRate;
    }

    public function getOperationalStatus(): string
    {
        return $this->operationalStatus;
    }

    public function isActive(): bool
    {
        return $this->operationalStatus === 'ACTIVE';
    }
}
