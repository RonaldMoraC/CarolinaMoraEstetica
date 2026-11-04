<?php
declare(strict_types=1);

namespace App\Infrastructure\Integration\WhatsApp\Persistence;

use App\Domain\Booking\Repositories\NotificationQueueRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * PdoWaNotificationQueueRepository
 *
 * Implementación de infraestructura PDO para la tabla wa_notification_queue.
 * Garantiza persistencia asíncrona desacoplada de notificaciones para WhatsApp.
 * (Cumple Skills 1, 2, 3, 5 y 11).
 */
final class PdoWaNotificationQueueRepository implements NotificationQueueRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Asegura la existencia de la plantilla de confirmación de cita en wa_template
     * para no violar la clave foránea fk_wa_queue_template.
     */
    public function ensureConfirmationTemplate(): int
    {
        $sql = "INSERT INTO wa_template (
                    template_id, template_code, language_code, template_body, meta_status
                ) VALUES (
                    1, 'appointment_confirmation', 'es',
                    'Hola {{1}}, tu cita para {{2}} el {{3}} a las {{4}} con {{5}} ha sido agendada con éxito en Carolina Mora Estética.',
                    'APPROVED'
                ) ON DUPLICATE KEY UPDATE template_code = VALUES(template_code)";

        $this->pdo->exec($sql);
        return 1;
    }

    /**
     * @inheritDoc
     */
    public function enqueue(
        int $templateId,
        string $recipientPhone,
        array $dynamicParameters,
        ?DateTimeImmutable $scheduledTime = null
    ): int {
        // Asegurar que la plantilla existe en la tabla wa_template
        if ($templateId === 1) {
            $this->ensureConfirmationTemplate();
        }

        $tz = new DateTimeZone('America/Bogota');
        $sendTime = $scheduledTime ?? new DateTimeImmutable('now', $tz);

        $sql = "INSERT INTO wa_notification_queue (
                    template_id, recipient_phone, dynamic_parameters, scheduled_send_time, processing_status, retry_count
                ) VALUES (
                    :template_id, :recipient_phone, :dynamic_parameters, :scheduled_send_time, 'QUEUED', 0
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':template_id', $templateId, PDO::PARAM_INT);
        $stmt->bindValue(':recipient_phone', trim($recipientPhone), PDO::PARAM_STR);
        $stmt->bindValue(':dynamic_parameters', json_encode($dynamicParameters, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->bindValue(':scheduled_send_time', $sendTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function getPendingNotifications(int $limit = 10): array
    {
        $tz = new DateTimeZone('America/Bogota');
        $now = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        $sql = "SELECT q.*, t.template_code, t.template_body
                FROM wa_notification_queue q
                JOIN wa_template t ON t.template_id = q.template_id
                WHERE q.processing_status = 'QUEUED'
                  AND q.scheduled_send_time <= :now
                ORDER BY q.queue_id ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':now', $now, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function markAsSent(int $queueId): void
    {
        $sql = "UPDATE wa_notification_queue
                SET processing_status = 'SENT'
                WHERE queue_id = :queue_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':queue_id', $queueId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @inheritDoc
     */
    public function markAsFailed(int $queueId, int $retryCount, bool $isPermanent = false): void
    {
        $status = $isPermanent ? 'FAILED' : 'QUEUED';

        $sql = "UPDATE wa_notification_queue
                SET processing_status = :status,
                    retry_count = :retry_count
                WHERE queue_id = :queue_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':retry_count', $retryCount, PDO::PARAM_INT);
        $stmt->bindValue(':queue_id', $queueId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
