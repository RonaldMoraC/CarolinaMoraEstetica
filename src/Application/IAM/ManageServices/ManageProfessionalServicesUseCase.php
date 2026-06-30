<?php
declare(strict_types=1);

namespace App\Application\IAM\ManageServices;

use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * ManageProfessionalServicesUseCase
 *
 * Orquesta la gestión de servicios asignados a un profesional.
 * Cumple Skill 1 (Clean Code), Skill 9 (Auditoría).
 */
final class ManageProfessionalServicesUseCase
{
    public function __construct(
        private StaffingRepositoryInterface $staffingRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private SystemAuditLogRepository $auditLogRepository
    ) {}

    /**
     * Obtiene todos los servicios disponibles y los servicios asignados a un profesional.
     *
     * @param int $professionalId ID del perfil profesional.
     * @return array Un array con 'all_services' y 'assigned_service_ids'.
     * @throws NotFoundException Si el profesional no existe.
     */
    public function getAssignedAndAllServices(int $professionalId): array
    {
        // Verificar si el profesional existe (asumiendo que StaffingRepositoryInterface tiene un método para esto)
        if (!$this->staffingRepository->findProfessionalProfileById($professionalId)) {
            throw new NotFoundException("Perfil profesional con ID {$professionalId} no encontrado.");
        }

        $allServices = $this->serviceRepository->findAllActive(); // Asumiendo que existe este método
        $assignedServices = $this->staffingRepository->getProfessionalServices($professionalId);

        $assignedServiceIds = array_map(fn($s) => $s['service_id'], $assignedServices);

        return [
            'all_services' => $allServices,
            'assigned_service_ids' => $assignedServiceIds
        ];
    }

    /**
     * Asigna un conjunto de servicios a un profesional, reemplazando los existentes.
     *
     * @param int $professionalId ID del perfil profesional.
     * @param AssignServicesToProfessionalDTO $dto DTO con los IDs de los servicios a asignar.
     * @param int $actorId ID del usuario que realiza la acción (para auditoría).
     * @return bool True si la asignación fue exitosa.
     * @throws NotFoundException Si el profesional no existe.
     * @throws DomainException Si algún serviceId no es válido o no existe.
     */
    public function assignServices(int $professionalId, AssignServicesToProfessionalDTO $dto, int $actorId): bool
    {
        // Verificar si el profesional existe
        $professionalProfile = $this->staffingRepository->findProfessionalProfileById($professionalId);
        if (!$professionalProfile) {
            throw new NotFoundException("Perfil profesional con ID {$professionalId} no encontrado.");
        }

        // Obtener servicios actuales para auditoría
        $oldAssignedServices = $this->staffingRepository->getProfessionalServices($professionalId);
        $oldAssignedServiceIds = array_map(fn($s) => $s['service_id'], $oldAssignedServices);

        // Sincronizar los servicios asignados (eliminar viejos, añadir nuevos)
        $success = $this->staffingRepository->syncProfessionalServices($professionalId, $dto->serviceIds);

        $this->auditLogRepository->insert(
            actorId: $actorId,
            action: 'PROFESSIONAL_SERVICES_UPDATED',
            entityType: 'professional_profile',
            entityId: $professionalId,
            oldValues: ['assigned_service_ids' => $oldAssignedServiceIds],
            newValues: ['assigned_service_ids' => $dto->serviceIds]
        );

        return $success;
    }
}