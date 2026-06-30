<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Logging;

/**
 * AppLogger
 * 
 * Servicio de logging centralizado para el sistema.
 * Implementa registro de eventos con marcas de tiempo y soporte para contextos JSON.
 */
final class AppLogger
{
    public function __construct(
        private readonly string $logFilePath
    ) {}

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

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