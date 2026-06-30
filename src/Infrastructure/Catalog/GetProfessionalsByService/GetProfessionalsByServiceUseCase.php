<?php
declare(strict_types=1);

namespace App\Application\Catalog\GetProfessionalsByService;

use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;

/**
 * GetProfessionalsByServiceUseCase
 * 
 * Recupera la lista de profesionales habilitados para un servicio específico.
 */
final readonly class GetProfessionalsByServiceUseCase
{
    public function __construct(
        private StaffingRepositoryInterface $staffingRepository
    ) {}

    public function execute(int $serviceId): array
    {
        return $this->staffingRepository->findProfessionalsByService($serviceId);
    }
}