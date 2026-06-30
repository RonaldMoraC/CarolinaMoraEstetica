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
 * Cumple Skill 1 (Arquitectura Limpia) y Skill 4 (RFC 7807).
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

        if (!$claims || !isset($claims['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'Sesión no encontrada',
                detail: 'No se pudo recuperar la identidad del usuario desde el contexto de seguridad.',
                instance: '/api/v1/auth/me'
            );
        }

        $user = $this->userRepository->findById((int) $claims['user_id']);

        if (!$user) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/user-not-found',
                title: 'Usuario no encontrado',
                detail: 'El usuario asociado al token ya no existe en el sistema.',
                instance: '/api/v1/auth/me'
            );
        }

        $userId = (int) $claims['user_id'];

        // Obtener nombre completo desde client_profile
        $displayName = '';
        $stmt = $this->pdo->prepare(
            "SELECT first_name, last_name FROM user WHERE user_id = :uid LIMIT 1"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $displayName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        }

        // Obtener teléfono desde user.auth_phone
        $stmt = $this->pdo->prepare(
            "SELECT auth_phone FROM user WHERE user_id = :uid LIMIT 1"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $phone = $userRow ? ($userRow['auth_phone'] ?? '') : '';

        ResponseHelper::json(200, true, 'Perfil recuperado con éxito', [
            'user' => [
                'id'            => $user->getUserId(),
                'first_name'    => $profile ? ($profile['first_name'] ?? '') : '',
                'last_name'     => $profile ? ($profile['last_name'] ?? '') : '',
                'name'          => $displayName,
                'username'      => $user->getEmail()->getValue(),
                'email'         => $user->getEmail()->getValue(),
                'phone'         => $phone,
                'role'          => $user->getRole()->getName(),
                'is_active'     => $user->isActive()
            ]
        ]);
    }
}
