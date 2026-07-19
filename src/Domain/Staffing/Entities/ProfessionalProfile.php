<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Entities;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * ProfessionalProfile — Perfil Operativo del Profesional
 *
 * Representa al especialista en la capa de agendamiento y staffing.
 * Gestiona el estado operativo (activo/inactivo) y la tasa de comisión
 * aplicable a los servicios que realiza.
 */
class ProfessionalProfile
{
    private const VALID_STATUSES = ['ACTIVE', 'INACTIVE', 'SUSPENDED'];

    /**
     * @param int    $id                     ID auto-incremental (bigint en DB).
     * @param float  $serviceCommissionRate  Porcentaje de comisión [0.00, 100.00].
     * @param string $operationalStatus      Estado actual (ej. 'ACTIVE', 'INACTIVE').
     */
    public function __construct(
        private readonly int    $id,
        private readonly float|string  $serviceCommissionRate,
        private readonly ?string $operationalStatus = null
    ) {
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        $status = $this->operationalStatus ?? 'ACTIVE';
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new DomainException(
                'El estado operativo del profesional es inválido.',
                'ProfessionalProfile'
            );
        }
    }

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
        return $this->operationalStatus ?? 'ACTIVE';
    }

    public function isActive(): bool
    {
        return $this->operationalStatus === 'ACTIVE';
    }
}
