<?php

declare(strict_types=1);

namespace App\Application\Staffing\GetAvailableSlots;

final readonly class GetSlotsDTO
{
    public function __construct(
        public int $professionalId,
        public int $branchId,
        public string $date,
        public int $slotDurationMinutes = 60
    ) {}
}
