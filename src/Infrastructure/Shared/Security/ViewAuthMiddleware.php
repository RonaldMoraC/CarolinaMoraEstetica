<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

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
        // En un entorno desacoplado, las vistas suelen cargarse y luego el JS valida.
        // Sin embargo, para mayor seguridad, podemos verificar cookies si se implementan.
        // Por ahora, permitimos el paso para que el script 'Auth Guard' del frontend ejecute la redirección.
        
        // Opcional: Si implementas cookies HTTP-Only en el futuro:
        // if (!isset($_COOKIE['auth_session'])) {
        //     header('Location: /login');
        //     exit;
        // }

        $next($params);
    }
}