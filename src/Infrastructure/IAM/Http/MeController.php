<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use PDO;

/**
 * MeController
 *
 * Recupera el perfil del usuario actualmente autenticado basándose en los datos
 * inyectados por el AuthMiddleware en el pipeline de la ruta.
 *
 * Optimizado contra N+1 (Skill 12): ejecuta una única consulta con JOINs indexados
 * entre user, user_role, role y client_profile en O(1).
 *
 * Cumple Skill 1 (Clean Code), Skill 4 (RFC 7807) y Skill 10 (Sentencias Preparadas).
 */
final class MeController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PDO $pdo
    ) {}

    /**
     * Maneja la petición GET /api/v1/auth/me
     *
     * @param array $params Contiene 'auth_user' inyectado por AuthMiddleware
     */
    public function handle(array $params): void
    {
        $claims = $params['auth_user'] ?? null;
        $instance = $_SERVER['REQUEST_URI'] ?? '/api/v1/auth/me';

        if (!$claims || !isset($claims['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'Sesión no encontrada',
                detail: 'No se pudo recuperar la identidad del usuario desde el contexto de seguridad.',
                instance: $instance
            );
            return;
        }

        $userId = (int) $claims['user_id'];
        if ($userId <= 0) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-user-id',
                title: 'ID de usuario inválido',
                detail: 'El identificador de usuario en el token no es válido.',
                instance: $instance
            );
            return;
        }

        // Skill 12: Consulta única con JOIN indexado evitando problemas de N+1
        $sql = "SELECT 
                    u.user_id,
                    u.email,
                    u.auth_phone,
                    u.first_name,
                    u.last_name,
                    u.account_status,
                    r.role_code,
                    cp.*
                FROM `user` u
                LEFT JOIN `user_role` ur ON u.user_id = ur.user_id
                LEFT JOIN `role` r ON ur.role_id = r.role_id
                LEFT JOIN `client_profile` cp ON cp.client_profile_id = u.user_id
                WHERE u.user_id = :uid
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/user-not-found',
                title: 'Usuario no encontrado',
                detail: 'El usuario asociado al token ya no existe en el sistema.',
                instance: $instance
            );
            return;
        }

        $firstName = (string) ($row['first_name'] ?? '');
        $lastName  = (string) ($row['last_name'] ?? '');
        $displayName = trim($firstName . ' ' . $lastName);
        if ($displayName === '') {
            $displayName = (string) ($row['email'] ?? 'Cliente');
        }

        $roleCode = (string) ($row['role_code'] ?? 'CLIENT');
        $isActive = ($row['account_status'] ?? '') === 'ACTIVE';

        ResponseHelper::json(200, true, 'Perfil recuperado con éxito', [
            'user' => [
                'id'                      => (int) $row['user_id'],
                'first_name'              => $firstName,
                'last_name'               => $lastName,
                'name'                    => $displayName,
                'username'                => (string) ($row['email'] ?? ''),
                'email'                   => (string) ($row['email'] ?? ''),
                'phone'                   => (string) ($row['auth_phone'] ?? ''),
                'role'                    => $roleCode,
                'is_active'               => $isActive,
                'birth_date'              => $row['birth_date'] ?? null,
                'medical_notes_allergies' => $row['medical_notes_allergies'] ?? null,
                'preferences'             => $row['consumption_preferences'] ?? $row['preferences'] ?? null,
                'consumption_preferences' => $row['consumption_preferences'] ?? null,
            ]
        ]);
    }
}
