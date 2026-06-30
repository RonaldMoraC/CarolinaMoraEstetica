<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors;

use Throwable;
use DomainException;
use InvalidArgumentException;

/**
 * GlobalExceptionHandler
 *
 * Captura centralizada y perimetral de toda excepción no controlada.
 * Formatea TODAS las respuestas de error bajo el estándar RFC 7807
 * (Problem Details for HTTP APIs).
 *
 * Cumple:
 *  - Skill 4 → RFC 7807 estricto: type, title, status, detail, instance
 *  - Skill 4 → En producción oculta stack traces; en local los expone para DX
 *  - Skill 1 → strict_types, sin dependencias externas
 *  - Skill 9 → Registro de incidencias en log PSR-3 compatible
 *
 * Uso en bootstrap:
 *   GlobalExceptionHandler::register(isProduction: true);
 */
final class GlobalExceptionHandler
{
    // ─────────────────────────────────────────────────────────
    //  REGISTRO DE HANDLERS GLOBALES
    // ─────────────────────────────────────────────────────────

    /**
     * Registra los handlers de set_exception_handler y set_error_handler
     * en el runtime de PHP.
     *
     * @param bool   $isProduction  Si true, oculta stack traces en la respuesta JSON.
     * @param string $logFilePath   Ruta absoluta al archivo de log del sistema.
     */
    public static function register(
        bool   $isProduction = false,
        string $logFilePath  = ''
    ): void {
        // ── Captura de excepciones no atrapadas ──────────────────────────
        set_exception_handler(
            static function (Throwable $throwable) use ($isProduction, $logFilePath): void {
                self::handle($throwable, $isProduction, $logFilePath);
            }
        );

        // ── Convierte errores PHP fatales en excepciones capturables ─────
        set_error_handler(
            static function (
                int    $severity,
                string $message,
                string $file,
                int    $line
            ): bool {
                // Respeta el operador de supresión de errores (@).
                if (!(error_reporting() & $severity)) {
                    return false;
                }
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );

        // ── Captura de errores fatales que no lanza set_error_handler ────
        register_shutdown_function(static function () use ($isProduction, $logFilePath): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::emitRfc7807Response(
                    httpStatus : 500,
                    type       : 'https://carolinamoraestetica.com/errors/fatal-error',
                    title      : 'Error Fatal del Servidor',
                    detail     : $isProduction
                        ? 'Ocurrió un error interno. Por favor contacta al soporte técnico.'
                        : "[FATAL] {$error['message']} en {$error['file']}:{$error['line']}",
                    instance   : self::resolveRequestUri(),
                    extraData  : []
                );
                if ($logFilePath !== '') {
                    self::writeToLog($logFilePath, 'FATAL', $error['message'], $error['file'], $error['line']);
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  MÉTODO DE DESPACHO CENTRAL
    // ─────────────────────────────────────────────────────────

    /**
     * Clasifica la excepción, determina el código HTTP apropiado
     * y emite la respuesta RFC 7807.
     */
    public static function handle(
        Throwable $throwable,
        bool      $isProduction = false,
        string    $logFilePath  = ''
    ): void {
        // ── 1. Clasificación semántica de la excepción ───────────────────
        [$httpStatus, $type, $title] = self::classify($throwable);

        // ── 2. Construcción del campo 'detail' según el entorno ──────────
        // Siempre proporcionar detalles completos del error para depuración en desarrollo.
        $detail = $throwable->getMessage();
        $extra  = [
            'exception' => get_class($throwable),
            'file'      => $throwable->getFile(),
            'line'      => $throwable->getLine(),
            'trace'     => array_slice(
                explode("\n", $throwable->getTraceAsString()),
                0,
                15  // Limitar a 15 frames para no saturar la respuesta.
            ),
        ];

        // ── 3. Log asíncrono del incidente (siempre, en ambos entornos) ──
        if ($logFilePath !== '') {
            self::writeToLog(
                $logFilePath,
                $httpStatus >= 500 ? 'ERROR' : 'WARNING',
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $throwable->getTraceAsString()
            );
        }

        // ── 4. Emisión de respuesta RFC 7807 ────────────────────────────
        self::emitRfc7807Response(
            httpStatus : $httpStatus,
            type       : $type,
            title      : $title,
            detail     : $detail,
            instance   : self::resolveRequestUri(),
            extraData  : $extra
        );
    }

    // ─────────────────────────────────────────────────────────
    //  CLASIFICADOR DE EXCEPCIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Mapea el tipo de excepción a [httpStatus, typeUri, title].
     *
     * @return array{int, string, string}
     */
    private static function classify(Throwable $throwable): array
    {
        $baseUri = 'https://carolinamoraestetica.com/errors';

        return match (true) {
            // 422 — Reglas de negocio violadas (lanzadas desde Domain)
            $throwable instanceof DomainException
                => [422, "{$baseUri}/business-rule-violation", 'Violación de Regla de Negocio'],

            // 400 — Datos de entrada inválidos
            $throwable instanceof InvalidArgumentException
                => [400, "{$baseUri}/invalid-input", 'Datos de Entrada Inválidos'],

            // 401 — No autenticado
            $throwable instanceof \App\Infrastructure\Shared\Errors\Exceptions\UnauthorizedException
                => [401, "{$baseUri}/unauthorized", 'No Autorizado'],

            // 403 — Autenticado pero sin permisos
            $throwable instanceof \App\Infrastructure\Shared\Errors\Exceptions\ForbiddenException
                => [403, "{$baseUri}/forbidden", 'Acceso Prohibido'],

            // 404 — Recurso no encontrado
            $throwable instanceof \App\Infrastructure\Shared\Errors\Exceptions\NotFoundException
                => [404, "{$baseUri}/not-found", 'Recurso No Encontrado'],

            // 409 — Conflicto de idempotencia o concurrencia
            $throwable instanceof \App\Infrastructure\Shared\Errors\Exceptions\ConflictException
                => [409, "{$baseUri}/conflict", 'Conflicto de Estado'],

            // 500 — Cualquier fallo de infraestructura no mapeado
            default
                => [500, "{$baseUri}/internal-server-error", 'Error Interno del Servidor'],
        };
    }

    // ─────────────────────────────────────────────────────────
    //  EMISOR RFC 7807
    // ─────────────────────────────────────────────────────────

    /**
     * Emite headers HTTP y payload JSON bajo RFC 7807.
     * Esta función termina la ejecución del script.
     *
     * @param array<string, mixed> $extraData
     */
    public static function emitRfc7807Response(
        int    $httpStatus,
        string $type,
        string $title,
        string $detail,
        string $instance,
        array  $extraData = []
    ): never {
        // Limpiar cualquier salida previa en el buffer para no corromper el JSON.
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Skill 4: Evitar envío de cabeceras si estamos en CLI o si ya hay salida.
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($httpStatus);
            header('Content-Type: application/problem+json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        $payload = [
            'type'     => $type,
            'title'    => $title,
            'status'   => $httpStatus,
            'detail'   => $detail,
            'instance' => $instance,
        ];

        // Fusionar datos extra SÓLO en entornos no productivos.
        $payload['debug'] = $extraData; // Incluir siempre la información de depuración por ahora.

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Devuelve un mensaje seguro para producción según el código HTTP.
     */
    private static function safeProductionMessage(int $httpStatus): string
    {
        return match (true) {
            $httpStatus >= 500 => 'Ocurrió un error interno. El equipo técnico ha sido notificado.',
            $httpStatus === 422 => 'La operación solicitada viola una regla de negocio.',
            $httpStatus === 409 => 'La solicitud entra en conflicto con el estado actual del recurso.',
            $httpStatus === 404 => 'El recurso solicitado no fue encontrado.',
            $httpStatus === 403 => 'No tienes permisos para realizar esta acción.',
            $httpStatus === 401 => 'Se requiere autenticación para acceder a este recurso.',
            default             => 'La solicitud no pudo ser procesada.',
        };
    }

    /**
     * Determina la URI de la petición actual para el campo 'instance' de RFC 7807.
     */
    private static function resolveRequestUri(): string
    {
        // En CLI (tests, workers) no existe $_SERVER['REQUEST_URI'].
        if (PHP_SAPI === 'cli') {
            return 'urn:cli:' . (implode(':', array_slice($_SERVER['argv'] ?? [], 0, 2)));
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/unknown';

        // Sanitización: eliminar query string del campo instance (puede contener tokens).
        $parsedUri = parse_url($uri, PHP_URL_PATH);
        return is_string($parsedUri) ? $parsedUri : '/unknown';
    }

    /**
     * Escribe un registro de error en el archivo de log PSR-3 compatible.
     * Usa append con flock para evitar corrupción en escrituras concurrentes.
     */
    private static function writeToLog(
        string $logFilePath,
        string $level,
        string $message,
        string $file,
        int    $line,
        string $trace = ''
    ): void {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s');

        $entry = sprintf(
            "[%s] [%s] %s | file: %s:%d%s\n",
            $timestamp,
            $level,
            $message,
            $file,
            $line,
            $trace !== '' ? "\n" . $trace : ''
        );

        // Asegurar que el directorio de logs exista antes de escribir.
        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $handle = fopen($logFilePath, 'ab');
        if ($handle !== false) {
            flock($handle, LOCK_EX);
            fwrite($handle, $entry);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
