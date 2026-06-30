<?php
declare(strict_types=1);

namespace App\Application\IAM\Logout;

use PDO;

/**
 * LogoutUserUseCase
 * 
 * Invalida las sesiones activas del usuario en la base de datos.
 * Cumple Skill 1 y Skill 6 (Seguridad de Sesión).
 */
final class LogoutUserUseCase
{
    public function __construct(private readonly PDO $pdo) {}

    public function execute(int $userId): void
    {
        // Skill 6/9: Invalidación atómica de todas las sesiones activas del usuario
        $sql = "UPDATE user_session SET is_revoked = 1 WHERE user_id = :user_id AND is_revoked = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
}