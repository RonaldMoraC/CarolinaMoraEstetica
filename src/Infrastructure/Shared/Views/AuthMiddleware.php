<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Infrastructure\Shared\Security\JwtTokenManager;

/**
 * AuthMiddleware — Guardia de Seguridad Perimetral
 * 
 * Valida la identidad del solicitante mediante JWT antes de permitir
 * el acceso a la lógica de negocio.
 */
class AuthMiddleware
{
    public function __construct(
        private JwtTokenManager $jwtTokenManager
    ) {}

    /**
     * Ejecuta la validación del token.
     * Si el token es inválido, detiene la ejecución y devuelve RFC 7807.
     * 
     * @return array Los claims del token (user_id, role, etc.)
     */
    private function resolveAuthorizationHeader(): string
    {
        // Standard: $_SERVER populated by Apache mod_php
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

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

    public function handle(): array
    {
        $header = $this->resolveAuthorizationHeader();

        if (!str_starts_with($header, 'Bearer ')) {
            $this->respondUnauthorized('Token ausente o formato inválido. Se requiere "Bearer {token}".');
        }

        $token = substr($header, 7);

        try {
            // Skill 4: El JwtTokenManager ya debe lanzar excepciones si el token expiró o es corrupto
            return $this->jwtTokenManager->validate($token);
        } catch (\Exception $e) {
            $this->respondUnauthorized('Sesión inválida o expirada: ' . $e->getMessage());
        }
        
        return []; // Nunca llega aquí debido al exit en respondUnauthorized
    }

    /**
     * Skill 4: Respuesta estandarizada RFC 7807 (Problem Details)
     */
    private function respondUnauthorized(string $detail): void
    {
        http_response_code(401);
        header('Content-Type: application/problem+json; charset=utf-8');

        echo json_encode([
            'type'     => 'https://carolinamoraestetica.com/errors/unauthorized',
            'title'    => 'No autorizado',
            'status'   => 401,
            'detail'   => $detail,
            'instance' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        exit;
    }
}