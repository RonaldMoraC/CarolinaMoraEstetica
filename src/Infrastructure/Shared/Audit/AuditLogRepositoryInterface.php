<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Audit;

interface AuditLogRepositoryInterface
{
    public function insert(
        ?int $actorId,
        string $action,
        string $entityType,
        string|int $entityId,
        array $oldValues = [],
        array $newValues = []
    ): void;
}
