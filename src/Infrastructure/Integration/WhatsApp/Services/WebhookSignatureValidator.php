<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\WhatsApp\Services;

/**
 * Validador de firma para Webhooks de Meta (WhatsApp).
 * Aplica SHA-256 HMAC para verificar que el payload proviene de Meta.
 */
final class WebhookSignatureValidator
{
    private string $appSecret;

    public function __construct(string $appSecret)
    {
        $this->appSecret = $appSecret;
    }

    public function isValid(string $payload, string $signature): bool
    {
        $hash = hash_hmac('sha256', $payload, $this->appSecret);
        return hash_equals('sha256=' . $hash, $signature);
    }
}
