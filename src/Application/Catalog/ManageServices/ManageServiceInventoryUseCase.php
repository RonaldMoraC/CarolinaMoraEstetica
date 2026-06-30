<?php
declare(strict_types=1);

namespace App\Application\Catalog\ManageServices;

use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;

class ManageServiceInventoryUseCase
{
    private ServiceRepositoryInterface $serviceRepository;
    private SystemAuditLogRepository $auditLogRepository;

    public function __construct(
        ServiceRepositoryInterface $serviceRepository,
        SystemAuditLogRepository $auditLogRepository
    ) {
        $this->serviceRepository = $serviceRepository;
        $this->auditLogRepository = $auditLogRepository;
    }

    public function createService(array $serviceData): int
    {
        if (empty($serviceData['name']) || empty($serviceData['category_id']) || !isset($serviceData['base_price'])) {
            throw new DomainException('Faltan datos obligatorios para crear el servicio.');
        }

        $serviceId = $this->serviceRepository->create($serviceData);

        $this->auditLogRepository->insert(
            actorId: null,
            action: 'SERVICE_CREATED',
            entityType: 'service',
            entityId: $serviceId,
            oldValues: [],
            newValues: $serviceData
        );

        return $serviceId;
    }

    public function updateService(int $serviceId, array $serviceData): bool
    {
        $existingService = $this->serviceRepository->findById($serviceId);
        if (!$existingService) {
            throw new NotFoundException("Servicio con ID {$serviceId} no encontrado.");
        }

        if (empty($serviceData)) {
            throw new DomainException('No se proporcionaron datos para actualizar el servicio.');
        }

        try {
            $success = $this->serviceRepository->update($serviceId, $serviceData);

            if ($success) {
                $updatedService = $this->serviceRepository->findById($serviceId);
                $this->auditLogRepository->insert(
                    actorId: null,
                    action: 'SERVICE_UPDATED',
                    entityType: 'service',
                    entityId: $serviceId,
                    oldValues: $existingService,
                    newValues: $updatedService ?? $serviceData
                );
            }
            return $success;
        } catch (\RuntimeException $e) {
            if ((int)$e->getCode() === 409) { 
                throw new ConflictException($e->getMessage(), 409, $e);
            }
            throw $e;
        }
    }

    public function toggleServiceStatus(int $serviceId, bool $isActive): bool
    {
        $existingService = $this->serviceRepository->findById($serviceId);
        if (!$existingService) {
            throw new NotFoundException("Servicio con ID {$serviceId} no encontrado.");
        }

        if ((bool)$existingService['is_active'] === $isActive) {
            return true;
        }

        try {
            $success = $this->serviceRepository->toggleActive($serviceId, $isActive);

            if ($success) {
                $updatedService = $this->serviceRepository->findById($serviceId);
                $this->auditLogRepository->insert(
                    actorId: null,
                    action: 'SERVICE_STATUS_CHANGED',
                    entityType: 'service',
                    entityId: $serviceId,
                    oldValues: $existingService,
                    newValues: $updatedService ?? ['is_active' => $isActive]
                );
            }
            return $success;
        } catch (\RuntimeException $e) {
            if ((int)$e->getCode() === 409) {
                throw new ConflictException($e->getMessage(), 409, $e);
            }
            throw $e;
        }
    }
}
