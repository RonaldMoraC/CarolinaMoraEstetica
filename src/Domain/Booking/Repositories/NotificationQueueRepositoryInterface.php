<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

use DateTimeImmutable;

/**
 * NotificationQueueRepositoryInterface
 *
 * Contrato de Dominio para el encolamiento y despacho asíncrono de notificaciones
 * omnicanal (WhatsApp Bot, SMS, Email).
 * (Clean Architecture - Inversión de Dependencias - Skill 1 y 5).
 */
interface NotificationQueueRepositoryInterface
{
    /**
     * Encola una notificación saliente en la cola transaccional del sistema.
     *
     * @param int $templateId ID de la plantilla en wa_template.
     * @param string $recipientPhone Número del cliente receptor (formato internacional o nacional).
     * @param array<string, mixed> $dynamicParameters Variables dinámicas de reemplazo para la plantilla.
     * @param DateTimeImmutable|null $scheduledTime Momento estimado para el despacho (null = inmediato).
     * @return int ID único del registro encolado (queue_id).
     */
    public function enqueue(
        int $templateId,
        string $recipientPhone,
        array $dynamicParameters,
        ?DateTimeImmutable $scheduledTime = null
    ): int;

    /**
     * Obtiene el lote de notificaciones en estado 'QUEUED' para despacho por el worker.
     *
     * @param int $limit Máximo de registros a procesar en el lote.
     * @return list<array<string, mixed>> Lista de filas de la cola.
     */
    public function getPendingNotifications(int $limit = 10): array;

    /**
     * Actualiza el estado de la notificación a 'SENT' tras el despacho exitoso a Meta Cloud API.
     *
     * @param int $queueId ID de la cola.
     */
    public function markAsSent(int $queueId): void;

    /**
     * Registra un fallo en el envío, actualizando el contador de reintentos y el estado.
     *
     * @param int $queueId ID de la cola.
     * @param int $retryCount Nuevo número de intentos acumulados.
     * @param bool $isPermanent Si se alcanzó el límite de intentos (pasa a 'FAILED').
     */
    public function markAsFailed(int $queueId, int $retryCount, bool $isPermanent = false): void;
}
