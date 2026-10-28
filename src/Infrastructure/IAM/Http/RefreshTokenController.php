<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Infrastructure\Shared\Security\JwtTokenManager;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use PDO;

/**
 * RefreshTokenController
 *
 * Endpoint POST /api/v1/auth/refresh
 * Renueva el token de autenticación para sesiones activas.
 *
 * Cumple:
 *  - Skill 1: Clean Code y strict_types=1.
 *  - Skill 4: Respuestas de error RFC 7807 en fallos de autenticación.
 *  - Skill 10: Validación y sanitización perimetral de tokens.
 */
final class RefreshTokenController
{
    public function __construct(
        private JwtTokenManager $jwtTokenManager,
        private PDO $pdo
    ) {}

    public function handle(array $params): void
    {
        // 1. Extraer token existente desde cabecera Authorization o cookie
        $token = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif (!empty($_COOKIE['auth_token'])) {
            $token = (string) $_COOKIE['auth_token'];
        }

        if (!$token) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'Token no provisto',
                detail: 'No se encontró un token válido para refrescar la sesión.',
                instance: '/api/v1/auth/refresh'
            );
            return;
        }

        try {
            // 2. Validar firma y vigencia del token actual
            $claims = $this->jwtTokenManager->validate($token);
            $userId = (int) ($claims['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new \RuntimeException('Identificador de usuario ausente en el token.', 401);
            }

            // 3. Verificar en la base de datos que el usuario siga activo
            $stmt = $this->pdo->prepare('SELECT user_id, is_active FROM user WHERE user_id = :uid LIMIT 1');
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow || (int) $userRow['is_active'] !== 1) {
                throw new \RuntimeException('El usuario no existe o se encuentra desactivado.', 401);
            }

            // 4. Generar nuevo token con período extendido
            $newClaims = [
                'user_id' => $userId,
                'email'   => (string) ($claims['email'] ?? ''),
                'role'    => (string) ($claims['role'] ?? 'CLIENT'),
            ];
            $newToken = $this->jwtTokenManager->generate($newClaims);

            // Actualizar cookie segura
            $cookieOptions = [
                'expires'  => time() + (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600),
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            setcookie('auth_token', $newToken, $cookieOptions);

            ResponseHelper::json(200, true, 'Token renovado exitosamente.', [
                'access_token' => $newToken,
                'token'        => $newToken,
                'expires_in'   => (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600),
                'role_code'    => $newClaims['role'],
            ]);

        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/invalid-token',
                title: 'Token inválido o expirado',
                detail: 'La sesión ha expirado. Por favor, inicia sesión nuevamente.',
                instance: '/api/v1/auth/refresh'
            );
        }
    }
}
