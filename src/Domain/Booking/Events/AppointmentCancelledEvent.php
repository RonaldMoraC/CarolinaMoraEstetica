<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Shared\Events\DomainEventInterface;

/**
 * AppointmentCancelledEvent — Evento de Dominio: Cita Cancelada
 *
 * Se despacha cuando una cita existente es cancelada, ya sea por el cliente,
 * el profesional, o el sistema administrativo.
 *
 * Consumidores esperados:
 * - NotificationQueueWorker → Notifica al cliente y al profesional la cancelación.
 * - AuditLogListener        → Registra el old_status y new_status en system_audit_log.
 * - ScheduleListener        → Libera el bloque horario en la malla de disponibilidad.
 * - BillingListener         → Evalúa política de reembolso según timing de cancelación.
 *
 * @see EventDispatcher::dispatch()
 */
final class AppointmentCancelledEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredAt;

    /**
     * @param string $appointmentId  UUID de la cita cancelada.
     * @param string $clientId       UUID del cliente afectado.
     * @param string $staffId        UUID del profesional cuyo turno se libera.
     * @param string $cancelledById  UUID del actor que realizó la cancelación (cliente, staff, admin).
     * @param string $reason         Motivo de cancelación (texto libre o código de razón).
     * @param string $scheduledStart Inicio original de la cita 'Y-m-d H:i:s' (America/Bogota).
     * @param bool   $isClientFault  true si la cancelación tardía es imputable al cliente
     *                               (puede activar política de cargo por cancelación).
     */
    public function __construct(
        private readonly string $appointmentId,
        private readonly string $clientId,
        private readonly string $staffId,
        private readonly string $cancelledById,
        private readonly string $reason,
        private readonly string $scheduledStart,
        private readonly bool   $isClientFault = false
    ) {
        $this->occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
    }

    // -------------------------------------------------------------------------
    // Contrato DomainEventInterface
    // -------------------------------------------------------------------------

    public function getEventName(): string
    {
        return 'appointment.cancelled';
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'appointment_id'  => $this->appointmentId,
            'client_id'       => $this->clientId,
            'staff_id'        => $this->staffId,
            'cancelled_by_id' => $this->cancelledById,
            'reason'          => $this->reason,
            'scheduled_start' => $this->scheduledStart,
            'is_client_fault' => $this->isClientFault,
            'occurred_at'     => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    // -------------------------------------------------------------------------
    // Accesores de Payload (para listeners tipados)
    // -------------------------------------------------------------------------

    public function getAppointmentId(): string
    {
        return $this->appointmentId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getStaffId(): string
    {
        return $this->staffId;
    }

    public function getCancelledById(): string
    {
        return $this->cancelledById;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getScheduledStart(): string
    {
        return $this->scheduledStart;
    }

    public function isClientFault(): bool
    {
        return $this->isClientFault;
    }
}
