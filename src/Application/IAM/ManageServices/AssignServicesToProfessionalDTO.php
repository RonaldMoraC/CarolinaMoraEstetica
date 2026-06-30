<?php
declare(strict_types=1);

namespace App\Application\IAM\ManageServices;

/**
 * AssignServicesToProfessionalDTO
 * Objeto de transferencia de datos inmutable para asignar servicios a un profesional.
 */
final readonly class AssignServicesToProfessionalDTO
{
    public function __construct(
        public array $serviceIds
    ) {}
}