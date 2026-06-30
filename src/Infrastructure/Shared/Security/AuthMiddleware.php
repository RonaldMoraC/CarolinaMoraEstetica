<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * AuthMiddleware
 * 
 * Protege rutas verificando la validez del JWT en el encabezado Authorization.
 */
final class AuthMiddleware
{
    private JwtTokenManager $jwtTokenManager;

    public function __construct(JwtTokenManager $jwtTokenManager)
    {
        $this->jwtTokenManager = $jwtTokenManager;
    }

    private function resolveAuthorizationHeader(): string
    {
        // Standard: $_SERVER populated by Apache mod_php
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!empty($authHeader)) {
            return $authHeader;
        }

        // Fallback: X-Authorization header — enviado por el ApiClient como alternativa
        // cuando Apache/XAMPP en CGI mode elimina el header Authorization estándar.
        $xAuthHeader = $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
        if (!empty($xAuthHeader)) {
            return $xAuthHeader;
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
            // X-Authorization via apache_request_headers
            if (isset($headers['X-Authorization'])) {
                return $headers['X-Authorization'];
            }
            if (isset($headers['x-authorization'])) {
                return $headers['x-authorization'];
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
            // X-Authorization via getallheaders
            if (isset($headers['X-Authorization'])) {
                return $headers['X-Authorization'];
            }
            if (isset($headers['x-authorization'])) {
                return $headers['x-authorization'];
            }
        }

        return '';
    }

    public function __invoke(array $params, callable $next): void
    {
        $authHeader = $this->resolveAuthorizationHeader();

        if (!str_starts_with($authHeader, 'Bearer ')) {
            $this->abort();
        }

        $token = substr($authHeader, 7);

        try {
            $claims = $this->jwtTokenManager->validate($token);
            // Inyectar claims en los parámetros para que el controlador los use si es necesario
            $params['auth_user'] = $claims;
            $next($params);
        } catch (\Exception $e) {
            $this->abort();
        }
    }

    private function abort(): void
    {
        GlobalExceptionHandler::emitRfc7807Response(
            httpStatus: 401,
            type: 'https://carolinamoraestetica.com/errors/unauthorized',
            title: 'No Autorizado',
            detail: 'Se requiere un token de acceso válido para acceder a este recurso.',
            instance: $_SERVER['REQUEST_URI'] ?? '/'
        );
    }
}