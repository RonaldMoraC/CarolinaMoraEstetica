<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Application\IAM\Authenticate\TokenManagerInterface;

/**
 * JwtTokenManager
 * 
 * Gestiona la creación y validación de tokens JWT con claims mínimos.
 */
final class JwtTokenManager implements TokenManagerInterface
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 3600)
    {
        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    public function generate(array $claims): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        
        // Persistencia y Sesión: Claims estrictamente necesarios
        $payload = json_encode(array_merge([
            'iat' => time(),
            'exp' => time() + $this->ttl,
        ], $claims));

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    public function validate(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException("Formato de token inválido.", 401);
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true);
        if (!hash_equals($this->base64UrlEncode($expectedSignature), $signature)) {
            throw new \RuntimeException("Firma de token inválida.", 401);
        }

        $payloadData = json_decode($this->base64UrlDecode($payload), true);
        
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            throw new \RuntimeException("El token ha expirado.", 401);
        }

        return $payloadData;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}