<?php
declare(strict_types=1);

namespace App\Application\Catalog\ManagePromotions;

/**
 * CreatePromotionDTO
 */
final readonly class CreatePromotionDTO
{
    public function __construct(
        public string $name,
        public float $discountPercentage,
        public string $startDate,
        public string $endDate,
        public array $associatedServices = []
    ) {}
}