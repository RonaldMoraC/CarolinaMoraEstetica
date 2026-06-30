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

    /**
     * Crea un nuevo servicio en el catálogo.
     *
     * @param array $serviceData Datos del nuevo servicio.
     * @return int El ID del servicio creado.
     * @throws DomainException Si los datos del servicio son inválidos.
     */
    public function createService(array $serviceData): int
    {
        // Basic validation (more complex validation would be in a DTO or Domain Entity)
        if (empty($serviceData['name']) || empty($serviceData['category_id']) || !isset($serviceData['base_price'])) {
            throw new DomainException('Faltan datos obligatorios para crear el servicio.');
        }

        $serviceId = $this->serviceRepository->create($serviceData);

        // Skill 9: Auditoría de creación
        $this->auditLogRepository->insert(
            actorId: null, // En una fase posterior, esto vendrá del DTO de sesión
            action: 'SERVICE_CREATED',
            entityType: 'service',
            entityId: $serviceId,
            oldValues: [],
            newValues: $serviceData
        );

        return $serviceId;
    }

    /**
     * Actualiza un servicio existente.
     *
     * @param int $serviceId ID del servicio a actualizar.
     * @param array $serviceData Datos a actualizar.
     * @return bool True si la actualización fue exitosa.
     * @throws NotFoundException Si el servicio no existe.
     * @throws ConflictException Si hay un conflicto de concurrencia.
     * @throws DomainException Si los datos de actualización son inválidos.
     */
    public function updateService(int $serviceId, array $serviceData): bool
    {
        $existingService = $this->serviceRepository->findById($serviceId);
        if (!$existingService) {
            throw new NotFoundException("Servicio con ID {$serviceId} no encontrado.");
        }

        // Basic validation (more complex validation would be in a DTO or Domain Entity)
        if (empty($serviceData)) {
            throw new DomainException('No se proporcionaron datos para actualizar el servicio.');
        }

        try {
            $success = $this->serviceRepository->update($serviceId, $serviceData);

            if ($success) {
                // Skill 9: Auditoría de actualización
                $updatedService = $this->serviceRepository->findById($serviceId); // Fetch updated state for new_values
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
            // Catch specific concurrency error from PdoServiceRepository (Skill 2)
            if ((int)$e->getCode() === 409) { 
                throw new ConflictException($e->getMessage(), 409, $e);
            }
            throw $e; // Re-throw other runtime exceptions
        }
    }

    /**
     * Cambia el estado de activación de un servicio.
     *
     * @param int $serviceId ID del servicio.
     * @param bool $isActive Nuevo estado (activo/inactivo).
     * @return bool True si el estado fue cambiado exitosamente.
     * @throws NotFoundException Si el servicio no existe.
     * @throws ConflictException Si hay un conflicto de concurrencia.
     */
    public function toggleServiceStatus(int $serviceId, bool $isActive): bool
    {
        $existingService = $this->serviceRepository->findById($serviceId);
        if (!$existingService) {
            throw new NotFoundException("Servicio con ID {$serviceId} no encontrado.");
        }

        // Only proceed if the status is actually changing
        if ((bool)$existingService['is_active'] === $isActive) {
            return true; // No change needed
        }

        try {
            $success = $this->serviceRepository->toggleActive($serviceId, $isActive);

            if ($success) {
                // Skill 9: Auditoría de cambio de estado
                $updatedService = $this->serviceRepository->findById($serviceId); // Fetch updated state for new_values
                $this->auditLogRepository->insert(
                    actorId: null,
                    action: 'SERVICE_STATUS_CHANGED',
                    entityType: 'service',
                    entityId: $serviceId,
                    oldValues: $existingService,
                    newValues: $updatedService ?? []
                );
            }
            return $success;
        } catch (\RuntimeException $e) {
            // Catch specific concurrency error from PdoServiceRepository (Skill 2)
            if ((int)$e->getCode() === 409) {
                throw new ConflictException($e->getMessage(), 409, $e);
            }
            throw $e; // Re-throw other runtime exceptions
        }
    }
}
