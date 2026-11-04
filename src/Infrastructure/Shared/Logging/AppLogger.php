<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Logging;

/**
 * AppLogger — PSR-3 Inspired Logger
 *
 * Servicio de logging centralizado con conciencia de entorno (Skill 4).
 * Registra eventos con marcas de tiempo en zona horaria de la estética
 * (America/Bogota) y contexto JSON estructurado.
 *
 * Niveles soportados (orden de severidad ascendente):
 *   DEBUG   → Solo en entornos no-producción. Nunca debe llegar a Hostinger.
 *   INFO    → Eventos normales del flujo (login exitoso, sesiones cerradas).
 *   WARNING → Situaciones anómalas sin impacto crítico (intentos fallidos, roles desconocidos).
 *   ERROR   → Fallas que impiden completar una operación.
 *
 * Cumple:
 *   - Skill 4  → Separa mensajes internos del output HTTP al cliente.
 *   - Skill 7  → Timestamps inmutables con DateTimeImmutable + America/Bogota.
 *   - Skill 3  → Escritura en disco con flock() para concurrencia segura.
 */
final class AppLogger
{
    public function __construct(
        private readonly string $logFilePath,
        private readonly bool   $isProduction = true
    ) {}

    /**
     * Eventos de depuración SOLO para entornos de desarrollo local.
     * En producción esta llamada es un no-op seguro y no escribe nada.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->isProduction) {
            return; // Guard de entorno: nunca escribe datos sensibles en producción.
        }
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Eventos informativos del flujo normal (ej. login exitoso, logout).
     *
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Situaciones anómalas que no interrumpen el servicio pero merecen atención.
     * (Ej. intento de login fallido, rol desconocido en claims del JWT.)
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Errores que impiden completar una operación del sistema.
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Escritura atómica al archivo de log con flock() para concurrencia segura (Skill 3).
     *
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s');

        $contextJson = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $entry = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $contextJson);

        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $handle = fopen($this->logFilePath, 'ab');
        if ($handle !== false) {
            flock($handle, LOCK_EX);
            fwrite($handle, $entry);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
