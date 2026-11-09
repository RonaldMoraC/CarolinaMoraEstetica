<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * DeletePermissionMiddleware
 *
 * Middleware de seguridad para restringir operaciones de borrado (DELETE)
 * a usuarios con roles específicos.
 *
 * Roles restringidos: 'ESPECIALIST', 'RECEPTIONIST', 'CLIENT'
 */
final class DeletePermissionMiddleware
{
    private const RESTRICTED_ROLES = ['ESPECIALIST', 'RECEPTIONIST', 'CLIENT'];

    /**
     * @param array $params Parámetros de la ruta (debe contener 'auth_user').
     * @param callable $next Siguiente middleware en la cadena.
     */
    public function __invoke(array $params, callable $next): void
    {
        $user = $params['auth_user'] ?? null;
        $role = $user['role'] ?? $user['role_code'] ?? '';

        if (in_array($role, self::RESTRICTED_ROLES, true)) {
            $this->respondForbidden($_SERVER['REQUEST_URI'] ?? '/unknown');
            return;
        }

        $next($params);
    }

    private function respondForbidden(string $uri): void
    {
        GlobalExceptionHandler::emitRfc7807Response(
            httpStatus: 403,
            type: 'https://carolinamoraestetica.com/errors/forbidden',
            title: 'Acceso Prohibido',
            detail: 'Su rol no permite eliminar recursos.',
            instance: $uri
        );
    }
}
