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
     * Si el usuario no tiene perfil profesional, lo crea automáticamente (UPSERT).
     *
     * @param int $professionalId ID del perfil profesional (= user_id).
     * @return array Un array con 'all_services' y 'assigned_ids'.
     */
    public function getAssignedAndAllServices(int $professionalId): array
    {
        // UPSERT: Garantiza que exista el perfil profesional antes de consultar.
        // Esto permite que admins y otros roles no-PROFESSIONAL puedan configurar
        // sus servicios desde la primera vez sin error 404.
        $this->staffingRepository->upsertProfessionalProfile($professionalId);

        $allServices      = $this->serviceRepository->findAllActive();
        $assignedServices = $this->staffingRepository->getProfessionalServices($professionalId);

        $assignedServiceIds = array_map(fn($s) => (int)$s['service_id'], $assignedServices);

        return [
            'all_services'  => $allServices,
            'assigned_ids'  => $assignedServiceIds,
        ];
    }

    /**
     * Asigna un conjunto de servicios a un profesional, reemplazando los existentes.
     * Si el usuario no tiene perfil profesional, lo crea automáticamente (UPSERT).
     *
     * @param int $professionalId ID del perfil profesional (= user_id).
     * @param AssignServicesToProfessionalDTO $dto DTO con los IDs de los servicios a asignar.
     * @param int $actorId ID del usuario que realiza la acción (para auditoría).
     * @return bool True si la asignación fue exitosa.
     * @throws DomainException Si algún serviceId no es válido o no existe.
     */
    public function assignServices(int $professionalId, AssignServicesToProfessionalDTO $dto, int $actorId): bool
    {
        // UPSERT: Garantiza que el perfil exista antes de asignar servicios
        $this->staffingRepository->upsertProfessionalProfile($professionalId);

        // Obtener servicios actuales para auditoría
        $oldAssignedServices   = $this->staffingRepository->getProfessionalServices($professionalId);
        $oldAssignedServiceIds = array_map(fn($s) => (int)$s['service_id'], $oldAssignedServices);

        // Sincronizar los servicios asignados (eliminar viejos, añadir nuevos)
        $success = $this->staffingRepository->syncProfessionalServices($professionalId, $dto->serviceIds);

        $this->auditLogRepository->insert(
            actorId:    $actorId,
            action:     'PROFESSIONAL_SERVICES_UPDATED',
            entityType: 'professional_profile',
            entityId:   $professionalId,
            oldValues:  ['assigned_service_ids' => $oldAssignedServiceIds],
            newValues:  ['assigned_service_ids' => $dto->serviceIds]
        );

        return $success;
    }
}