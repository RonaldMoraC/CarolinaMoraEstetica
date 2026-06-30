<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Audit;

/**
 * AuditLogger
 *
 * Fachada de alto nivel sobre SystemAuditLogRepository.
 * Provee métodos semánticos con nombres de acción estandarizados
 * para que los Casos de Uso no construyan los strings de acción manualmente.
 *
 * Patrón: Fachada (Facade) — no añade lógica de negocio, solo simplifica la API.
 *
 * Cumple:
 *  - Skill 9  → Inyecta el repositorio INSERT-only por constructor
 *  - Skill 1  → strict_types, sin instanciaciones internas
 */
final class AuditLogger
{
    public function __construct(
        private readonly SystemAuditLogRepository $repository
    ) {}

    // ─────────────────────────────────────────────────────────
    //  MÉTODOS SEMÁNTICOS POR CONTEXTO
    // ─────────────────────────────────────────────────────────

    /**
     * Registra cualquier mutación de forma genérica.
     *
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function log(
        string     $actorId,
        string     $action,
        string     $entityType,
        string|int $entityId,
        array      $oldValues = [],
        array      $newValues = []
    ): void {
        $this->repository->insert($actorId, $action, $entityType, $entityId, $oldValues, $newValues);
    }

    // ── IAM ──────────────────────────────────────────────────

    /** @param array<string, mixed> $newUserData */
    public function userRegistered(string $actorId, int $userId, array $newUserData): void
    {
        $this->repository->insert($actorId, 'USER_REGISTERED', 'user', $userId, [], $newUserData);
    }

    /** @param array<string, mixed> $oldRole @param array<string, mixed> $newRole */
    public function roleAssigned(string $actorId, int $userId, array $oldRole, array $newRole): void
    {
        $this->repository->insert($actorId, 'ROLE_ASSIGNED', 'user', $userId, $oldRole, $newRole);
    }

    // ── Booking ──────────────────────────────────────────────

    /** @param array<string, mixed> $appointmentData */
    public function appointmentCreated(string $actorId, int $appointmentId, array $appointmentData): void
    {
        $this->repository->insert($actorId, 'APPOINTMENT_CREATED', 'appointment', $appointmentId, [], $appointmentData);
    }

    /**
     * @param array<string, mixed> $oldState
     * @param array<string, mixed> $newState
     */
    public function appointmentCancelled(string $actorId, int $appointmentId, array $oldState, array $newState): void
    {
        $this->repository->insert($actorId, 'APPOINTMENT_CANCELLED', 'appointment', $appointmentId, $oldState, $newState);
    }

    /**
     * @param array<string, mixed> $oldState
     * @param array<string, mixed> $newState
     */
    public function appointmentStatusChanged(
        string $actorId,
        int    $appointmentId,
        string $newStatus,
        array  $oldState,
        array  $newState
    ): void {
        $action = match ($newStatus) {
            'CHECKED_IN' => 'APPOINTMENT_CHECKIN',
            'COMPLETED'  => 'APPOINTMENT_COMPLETED',
            'NOSHOW'     => 'APPOINTMENT_NOSHOW',
            default      => 'APPOINTMENT_STATUS_CHANGED',
        };
        $this->repository->insert($actorId, $action, 'appointment', $appointmentId, $oldState, $newState);
    }

    // ── Billing ──────────────────────────────────────────────

    /** @param array<string, mixed> $paymentData */
    public function paymentRegistered(string $actorId, int $paymentId, array $paymentData): void
    {
        $this->repository->insert($actorId, 'PAYMENT_REGISTERED', 'payment', $paymentId, [], $paymentData);
    }

    /**
     * @param array<string, mixed> $oldState
     * @param array<string, mixed> $newState
     */
    public function invoiceVoided(string $actorId, int $invoiceId, array $oldState, array $newState): void
    {
        $this->repository->insert($actorId, 'INVOICE_VOIDED', 'invoice', $invoiceId, $oldState, $newState);
    }

    /** @param array<string, mixed> $sessionData */
    public function cashRegisterOpened(string $actorId, int $sessionId, array $sessionData): void
    {
        $this->repository->insert($actorId, 'CASH_REGISTER_OPENED', 'cash_register_session', $sessionId, [], $sessionData);
    }

    /** @param array<string, mixed> $sessionData */
    public function cashRegisterClosed(string $actorId, int $sessionId, array $sessionData): void
    {
        $this->repository->insert($actorId, 'CASH_REGISTER_CLOSED', 'cash_register_session', $sessionId, $sessionData, []);
    }

    // ── Catalog ──────────────────────────────────────────────

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function serviceModified(string $actorId, int $serviceId, array $oldValues, array $newValues): void
    {
        $this->repository->insert($actorId, 'SERVICE_MODIFIED', 'service', $serviceId, $oldValues, $newValues);
    }
}
