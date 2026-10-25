<?php declare(strict_types=1);

namespace App\Application\Shared\Audit;

use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;

/**
 * AuditService â¬ Servicio de Aplicación para Auditoría
 *
 * Centraliza la lógica de registro de auditoría forense para ser usado
 * por los Casos de Uso. Provee una abstracción sobre el repositorio
 * de infraestructura.
 *
 * Cumple Skill 9 â  Trazabilidad y Auditoría.
 */
final class AuditService
{
    private SystemAuditLogRepository $auditRepo;

    public function __construct(SystemAuditLogRepository $auditRepo)
    {
        $this->auditRepo = $auditRepo;
    }

    /**
     * Registra una acción de auditoría.
     */
    public function record(
        ?int       $actorId,
        string     $action,
        string     $entityType,
        string|int $entityId,
        array      $oldValues = [],
        array      $newValues = []
    ): void {
        $this->auditRepo->insert(
            $actorId,
            $action,
            $entityType,
            $entityId,
            $oldValues,
            $newValues
        );
    }

    /**
     * Helper para registrar la creación de una entidad.
     */
    public function recordCreation(
        ?int       $actorId,
        string     $action,
        string     $entityType,
        string|int $entityId,
        array      $newValues
    ): void {
        $this->record($actorId, $action, $entityType, $entityId, [], $newValues);
    }

    /**
     * Helper para registrar la modificación de una entidad.
     */
    public function recordMutation(
        ?int       $actorId,
        string     $action,
        string     $entityType,
        string|int $entityId,
        array      $oldValues,
        array      $newValues
    ): void {
        $this->record($actorId, $action, $entityType, $entityId, $oldValues, $newValues);
    }
}
