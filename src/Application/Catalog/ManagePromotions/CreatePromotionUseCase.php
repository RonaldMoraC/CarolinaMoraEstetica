<?php
declare(strict_types=1);

namespace App\Application\Catalog\ManagePromotions;

use App\Domain\Catalog\Repositories\PromotionRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * CreatePromotionUseCase
 * Orquesta la creación de una promoción y su auditoría forense.
 */
final class CreatePromotionUseCase
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotionRepository,
        private readonly SystemAuditLogRepository $auditLogRepository
    ) {}

    public function execute(CreatePromotionDTO $dto): int
    {
        // Validación de negocio (Skill 1/7)
        if (strtotime($dto->endDate) < strtotime($dto->startDate)) {
            throw new DomainException("La fecha de fin no puede ser anterior a la fecha de inicio.");
        }

        $data = [
            'name' => $dto->name,
            'discount_percentage' => $dto->discountPercentage,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'associated_services' => $dto->associatedServices,
            'is_active' => 1
        ];

        $promotionId = $this->promotionRepository->create($data);

        // Auditoría Forense (Skill 9)
        $this->auditLogRepository->insert(
            actorId: null,
            action: 'PROMOTION_CREATED',
            entityType: 'promotion',
            entityId: $promotionId,
            oldValues: [],
            newValues: $data
        );

        return $promotionId;
    }
}