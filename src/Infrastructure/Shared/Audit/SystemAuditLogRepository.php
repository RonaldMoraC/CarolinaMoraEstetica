<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Audit;

use PDO;

/**
 * SystemAuditLogRepository
 *
 * Repositorio de auditoría forense del sistema.
 *
 * DISEÑO INMUTABLE POR ARQUITECTURA (Skill 9):
 *   Este repositorio expone EXCLUSIVAMENTE el método insert().
 *   No existe ningún método update() ni delete() por diseño deliberado.
 *   Cualquier intento de modificar o borrar registros de auditoría
 *   debe fallar en tiempo de compilación (método no existe).
 *
 * Cumple:
 *  - Skill 9  → INSERT-only, captura IP real (Cloudflare), old/new JSON diff
 *  - Skill 7  → DateTimeImmutable + America/Bogota para executed_at
 *  - Skill 10 → Prepared statements con bindValue en cada campo
 *  - Skill 1  → strict_types, inyección PDO por constructor
 */
final class SystemAuditLogRepository
{
    public function __construct(private readonly PDO $pdo) {}

    // ─────────────────────────────────────────────────────────
    //  MÉTODO ÚNICO: INSERT (inmutable por diseño)
    // ─────────────────────────────────────────────────────────

    /**
     * Registra una mutación crítica de datos en el log de auditoría.
     *
     * @param string               $actorId       ID del usuario autenticado o 'system_bot'
     * @param string               $action        Nombre semántico de la acción (ej. 'APPOINTMENT_CREATED')
     * @param string               $entityType    Nombre de la entidad afectada (ej. 'appointment')
     * @param string|int           $entityId      ID de la entidad afectada
     * @param array<string, mixed> $oldValues     Estado anterior de la entidad (vacío si es creación)
     * @param array<string, mixed> $newValues     Nuevo estado de la entidad
     */
    public function insert(
        ?int       $actorId,
        string     $action,
        string     $entityType,
        string|int $entityId,
        array      $oldValues = [],
        array      $newValues = []
    ): void {
        $clientMetadata = $this->encodeJson([
            'ip'         => $this->resolveClientIp(),
            'user_agent' => $this->resolveUserAgent(),
        ]);

        $sql = <<<SQL
            INSERT INTO system_audit_log (
                user_id,
                action_type,
                target_table,
                record_id,
                pre_mutation_state,
                post_mutation_state,
                client_metadata,
                executed_at
            ) VALUES (
                :user_id,
                :action_type,
                :target_table,
                :record_id,
                :pre_mutation_state,
                :post_mutation_state,
                :client_metadata,
                :executed_at
            )
        SQL;

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':user_id',             $actorId,                         $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':action_type',         $action,                          PDO::PARAM_STR);
        $stmt->bindValue(':target_table',        $entityType,                      PDO::PARAM_STR);
        $stmt->bindValue(':record_id',           (string) $entityId,               PDO::PARAM_STR);
        $stmt->bindValue(':pre_mutation_state',  $this->encodeJson($oldValues),    PDO::PARAM_STR);
        $stmt->bindValue(':post_mutation_state', $this->encodeJson($newValues),    PDO::PARAM_STR);
        $stmt->bindValue(':client_metadata',     $clientMetadata,                  PDO::PARAM_STR);
        $stmt->bindValue(':executed_at',         $this->resolveTimestamp(),        PDO::PARAM_STR);

        $stmt->execute();
    }

    // ─────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Detecta la IP real del cliente teniendo en cuenta:
     *   - Cloudflare (CF-Connecting-IP) — máxima prioridad en producción
     *   - Proxies genéricos (X-Forwarded-For)
     *   - Conexión directa (REMOTE_ADDR)
     */
    private function resolveClientIp(): string
    {
        // Prioridad 1: Cloudflare inyecta siempre la IP real en este header.
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return filter_var(
                $_SERVER['HTTP_CF_CONNECTING_IP'],
                FILTER_VALIDATE_IP
            ) ?: 'unknown';
        }

        // Prioridad 2: Proxy genérico (puede ser manipulado, menos confiable).
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $firstIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            return filter_var($firstIp, FILTER_VALIDATE_IP) ?: 'unknown';
        }

        // Prioridad 3: Conexión directa.
        return filter_var(
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            FILTER_VALIDATE_IP
        ) ?: 'unknown';
    }

    /**
     * Extrae el User-Agent sanitizando caracteres no imprimibles.
     * Trunca a 512 caracteres para no exceder el campo VARCHAR de la BD.
     */
    private function resolveUserAgent(): string
    {
        $raw = $_SERVER['HTTP_USER_AGENT'] ?? 'cli/unknown';

        // Eliminar caracteres de control (anti log-injection).
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $raw) ?? 'unknown';

        return mb_substr($sanitized, 0, 512, 'UTF-8');
    }

    /**
     * Retorna el timestamp actual en America/Bogota formateado para MySQL.
     * Skill 7: DateTimeImmutable obligatorio, nunca date() ni time().
     */
    private function resolveTimestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Serializa un array a JSON con soporte UTF-8.
     *
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        if ($data === []) {
            return '{}';
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '{}';
    }
}
