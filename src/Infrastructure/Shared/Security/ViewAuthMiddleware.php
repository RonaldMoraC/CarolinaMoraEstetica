<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Infrastructure\Shared\Utils\AppPath;

/**
 * ViewAuthMiddleware
 * 
 * Protege las vistas HTML redirigiendo al login si no hay sesión activa.
 */
final class ViewAuthMiddleware
{
    /**
     * Verifica la sesión. Dado que el JWT está en localStorage (cliente),
     * este middleware actúa como un guardia de "primera capa".
     * La validación real del token la hace el frontend al cargar, 
     * pero aquí bloqueamos el acceso si no hay indicios de sesión.
     */
    public function __invoke(array $params, callable $next): void
    {
        // Validar autenticación por cookies (auth_token o user_role) o por autorización Bearer si aplica
        $token = $_COOKIE['auth_token'] ?? '';
        $role = $_COOKIE['user_role'] ?? '';
        $staffRoles = ['SUPER_ADMIN', 'MANAGER', 'ESPECIALIST', 'RECEPCIONIST'];

        // Permitimos el paso si existe el header de autorización,
        // asumiendo que el cliente validará el token en el frontend
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['X-Authorization'] ?? '';

        if (!empty($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            $next($params);
            return;
        }

        // Fallback a validación de cookies existente
        $token = $_COOKIE['auth_token'] ?? '';
        $role = $_COOKIE['user_role'] ?? '';
        $staffRoles = ['SUPER_ADMIN', 'MANAGER', 'ESPECIALIST', 'RECEPCIONIST'];

        if ((empty($token) || !in_array($role, $staffRoles, true))) {
            header('Location: ' . AppPath::url('/login'));
            exit;
        }

        $next($params);
    }
}