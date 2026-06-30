<?php
declare(strict_types=1);

namespace App\Application\IAM\ManageRoles;

use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use PDO;
use RuntimeException;

/**
 * AssignRoleUseCase
 * 
 * Caso de uso encargado de la lógica de asignación y cambio de roles de usuario.
 * Garantiza la integridad referencial y la atomicidad en la tabla user_role.
 */
final class AssignRoleUseCase
{
    public function __construct(
        private PDO $pdo,
        private SystemAuditLogRepository $auditLogRepository
    ) {}

    /**
     * Asigna un nuevo rol a un usuario eliminando asignaciones previas.
     *
     * @param int $userId
     * @param string $roleCode
     * @throws RuntimeException
     */
    public function execute(int $userId, string $roleCode): void
    {
        // 1. Resolver el ID del rol a partir del código (Skill 12)
        $stmt = $this->pdo->prepare("SELECT role_id FROM role WHERE role_code = ? LIMIT 1");
        $stmt->execute([$roleCode]);
        $roleId = $stmt->fetchColumn();

        // Fallback preventivo a rol CLIENT (ID 5) si el código es inválido
        if (!$roleId) {
            $roleId = 5; 
        }

        // 2. Operación Atómica (Skill 2: Concurrencia y Transaccionalidad)
        $isInTransaction = $this->pdo->inTransaction();
        if (!$isInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            // Limpiar roles actuales para mantener el principio de rol único
            $stmt = $this->pdo->prepare("DELETE FROM user_role WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Insertar nueva asignación
            $stmt = $this->pdo->prepare("INSERT INTO user_role (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$userId, (int)$roleId]);

            if (!$isInTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$isInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}