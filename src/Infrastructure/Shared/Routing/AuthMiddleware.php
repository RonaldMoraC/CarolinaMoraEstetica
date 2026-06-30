<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Infrastructure\Shared\Security\JwtTokenManager;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * AuthMiddleware — Guardia de Seguridad JWT
 * 
 * Valida el token Bearer y detiene la petición si es inválido o ausente.
 * Cumple Skill 4 (RFC 7807) y Skill 10 (Sanitización Perimetral).
 */
final class AuthMiddleware
{
    public function __construct(
        private JwtTokenManager $jwtTokenManager
    ) {}

    /**
     * Ejecución del middleware en el pipeline del Router.
     *
     * @param array $params Parámetros de la ruta.
     * @param callable $next Siguiente eslabón en la cadena.
     */
    private function resolveAuthorizationHeader(): string
    {
        // Standard: $_SERVER populated by Apache mod_php
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        // Fallback: X-Authorization para evadir filtros de Apache (Skill 10: Robustez)
        if (empty($header)) {
            $header = $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
        }

        if (!empty($header)) {
            return $header;
        }

        // Fallback: apache_request_headers() (available when PHP runs as Apache module)
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
            if (isset($headers['authorization'])) {
                return $headers['authorization'];
            }
        }

        // Fallback: getallheaders() (available in PHP 5.4+ via stream_context or FastCGI)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
            if (isset($headers['authorization'])) {
                return $headers['authorization'];
            }
        }

        return '';
    }

    public function __invoke(array $params, callable $next): void
    {
        $header = $this->resolveAuthorizationHeader();
        $uri = $_SERVER['REQUEST_URI'] ?? '/unknown';

        if (!str_starts_with($header, 'Bearer ')) {
            $this->respondUnauthorized('Se requiere un token de autenticación válido.', $uri);
            return;
        }

        $token = substr($header, 7);

        try {
            // Validar token y obtener claims (user_id, role, etc.)
            $claims = $this->jwtTokenManager->validate($token);
            
            // Inyectar contexto de usuario en los parámetros para el controlador
            $params['auth_user'] = $claims;

            // Continuar al siguiente middleware o controlador
            $next($params);
            
        } catch (\Exception $e) {
            $this->respondUnauthorized('La sesión ha expirado o el token es inválido: ' . $e->getMessage(), $uri);
        }
    }

    private function respondUnauthorized(string $detail, string $uri): void
    {
        GlobalExceptionHandler::emitRfc7807Response(
            httpStatus: 401,
            type: 'https://carolinamoraestetica.com/errors/unauthorized',
            title: 'No autorizado',
            detail: $detail,
            instance: $uri
        );
    }
}