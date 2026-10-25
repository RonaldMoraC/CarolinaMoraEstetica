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
     * Verifica la sesión. Dado que el JWT está en localStorage (cliente) y en cookies seguras,
     * este middleware actúa como un guardia de "primera capa" en el backend.
     */
    public function __invoke(array $params, callable $next): void
    {
        // 1. Permitir paso si existe header Bearer (ej. API o cliente fetch)
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['X-Authorization'] ?? '';

        if (!empty($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            $next($params);
            return;
        }

        // 2. Validación por cookies seguras
        $token = $_COOKIE['auth_token'] ?? '';
        $role = strtoupper(trim((string)($_COOKIE['user_role'] ?? '')));

        if (empty($token) || empty($role)) {
            header('Location: ' . AppPath::url('/login'));
            exit;
        }

        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $isAdminRoute = str_contains($currentUri, '/admin');
        $isAppRoute = str_contains($currentUri, '/app');

        $staffRoles = ['SUPER_ADMIN', 'MANAGER', 'ESPECIALIST', 'RECEPCIONIST', '1', 'ADMIN'];

        if ($isAdminRoute) {
            // Rutas de administración: Solo personal de staff
            if (!in_array($role, $staffRoles, true)) {
                // Si es un cliente intentando entrar a /admin, redirigir a su panel PWA
                if ($role === 'CLIENT') {
                    header('Location: ' . AppPath::url('/app/dashboard'));
                    exit;
                }
                header('Location: ' . AppPath::url('/login'));
                exit;
            }
        } elseif ($isAppRoute) {
            // Rutas de la PWA de clientes: Permitir CLIENT y también staff para supervisión
            $allowedAppRoles = ['CLIENT', 'SUPER_ADMIN', 'MANAGER', 'ESPECIALIST', 'RECEPCIONIST', '1', 'ADMIN'];
            if (!in_array($role, $allowedAppRoles, true)) {
                header('Location: ' . AppPath::url('/login'));
                exit;
            }
        }

        $next($params);
    }
}
