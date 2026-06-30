<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\Logout\LogoutUserUseCase;
use App\Infrastructure\Shared\Security\JwtTokenManager;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use Exception;

/**
 * LogoutController
 * 
 * Maneja el cierre de sesión invalidando los tokens en el servidor.
 * Cumple Skill 1 (Clean Code) y Skill 4 (RFC 7807).
 */
class LogoutController
{
    public function __construct(
        private readonly LogoutUserUseCase $logoutUserUseCase,
        private readonly JwtTokenManager $jwtTokenManager
    ) {}

    public function handle(array $params): void
    {
        // 1. Obtener contexto de usuario desde el middleware (Skill 10)
        $authUser = $params['auth_user'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? '/api/v1/auth/logout';

        if (!$authUser || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                401,
                'https://carolinamoraestetica.com/errors/unauthorized',
                'Sesión no encontrada',
                'No se pudo recuperar la identidad del usuario desde el contexto de seguridad.',
                $uri
            );
            return;
        }

        try {
            $userId = (int) $authUser['user_id'];
            
            // 2. Ejecutar la invalidación persistente (Skill 6)
            $this->logoutUserUseCase->execute($userId);

            // 3. Responder con éxito (Skill 1)
            ResponseHelper::json(200, true, 'Sesión cerrada exitosamente.');

        } catch (Exception $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                500,
                'https://carolinamoraestetica.com/errors/internal-server-error',
                'Error al cerrar sesión',
                $e->getMessage(),
                $uri
            );
        }
    }
}
