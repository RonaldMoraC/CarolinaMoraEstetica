<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Helpers;

/**
 * ResponseHelper
 *
 * Utilidades para formatear y emitir respuestas HTTP estandarizadas en formato JSON.
 * (Clean Architecture - Capa de Infraestructura - Skill 1 y 4).
 */
final class ResponseHelper
{
    /** No se permite instanciar esta clase helper. */
    private function __construct() {}

    /**
     * Emite una respuesta HTTP estructurada en formato JSON y detiene la ejecución.
     *
     * @param int                  $statusCode Código de estado HTTP (200, 400, etc.)
     * @param bool                 $success    Bandera indicando si la operación fue exitosa.
     * @param string               $message    Explicación o mensaje para el cliente.
     * @param array<string, mixed> $data       Payload opcional de datos.
     * @param array<string, mixed> $meta       Metadatos opcionales (paginación, etc.).
     * @return never
     */
    public static function json(int $statusCode, bool $success, string $message, array $data = [], array $meta = []): never
    {
        // Limpiar buffers de salida previos
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        http_response_code($statusCode);

        $payload = [
            'success'   => $success,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

        exit;
    }
}
