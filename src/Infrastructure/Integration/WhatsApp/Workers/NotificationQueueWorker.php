<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\WhatsApp\Workers;

/**
 * Worker CLI por lotes para el envío de notificaciones de WhatsApp.
 * Implementa semáforo físico (flock) y límite de ejecución para entornos Hostinger.
 */
final class NotificationQueueWorker
{
    private string $lockFile;

    public function __construct(string $lockFile = __DIR__ . '/../../../../../logs/worker.lock')
    {
        $this->lockFile = $lockFile;
    }

    public function run(): void
    {
        $fp = fopen($this->lockFile, 'c+');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            echo "El worker ya se encuentra en ejecución (Semáforo ocupado).\n";
            return;
        }

        try {
            $startTime = time();
            $maxExecutionTime = 45; // Límite de 45 segundos para Hostinger Premium

            echo "Iniciando procesamiento de cola de notificaciones...\n";

            // Simulación de procesamiento por lotes con límite de tiempo
            while ((time() - $startTime) < $maxExecutionTime) {
                // Aquí se procesarán los mensajes pendientes de wa_notification_queue
                usleep(500000); // 0.5 segundos por ciclo simulado
                break; // Romper por ahora ya que no hay registros pendientes
            }

            echo "Procesamiento de lotes finalizado de forma limpia.\n";

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
