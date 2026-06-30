<?php
declare(strict_types=1);

namespace App\Application\Staffing\ListProfessionals;

use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;

/**
 * GetAllProfessionalsUseCase
 *
 * Caso de uso para listar todos los profesionales activos del sistema.
 * Retorna datos suficientes para llenar selects de filtro en el calendario y horarios.
 */
final class GetAllProfessionalsUseCase
{
    public function __construct(
        private readonly StaffingRepositoryInterface $staffingRepository
    ) {}

    /**
     * @return list<array{professional_profile_id: int, first_name: string, last_name: string, email: string, operational_status: string}>
     */
    public function execute(): array
    {
        return $this->staffingRepository->findAllProfessionals();
    }
}
