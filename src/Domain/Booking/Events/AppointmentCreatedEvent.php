<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Shared\Events\DomainEventInterface;

/**
 * AppointmentCreatedEvent — Evento de Dominio: Cita Creada
 *
 * Se despacha cuando una nueva cita (Appointment) es confirmada en el sistema,
 * independientemente del canal de origen (PWA, WhatsApp Bot, Admin).
 *
 * Consumidores esperados:
 * - NotificationQueueWorker → Encola mensaje de confirmación WhatsApp al cliente.
 * - AuditLogListener        → Registra en system_audit_log con old/new values.
 * - AnalyticsListener       → Actualiza métricas de agendamiento en tiempo real.
 *
 * @see EventDispatcher::dispatch()
 */
final class AppointmentCreatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredAt;

    /**
     * @param string $appointmentId UUID de la cita recién creada.
     * @param string $clientId      UUID del cliente que agendó.
     * @param string $staffId       UUID del estilista/profesional asignado.
     * @param string $serviceId     UUID del servicio agendado.
     * @param string $startAt       Inicio de la cita en formato 'Y-m-d H:i:s' (America/Bogota).
     * @param string $endAt         Fin de la cita en formato 'Y-m-d H:i:s' (America/Bogota).
     * @param string $channel       Canal de origen: 'pwa' | 'whatsapp' | 'admin'.
     */
    public function __construct(
        private readonly string $appointmentId,
        private readonly string $clientId,
        private readonly string $staffId,
        private readonly string $serviceId,
        private readonly string $startAt,
        private readonly string $endAt,
        private readonly string $channel = 'pwa'
    ) {
        $this->occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
    }

    // -------------------------------------------------------------------------
    // Contrato DomainEventInterface
    // -------------------------------------------------------------------------

    public function getEventName(): string
    {
        return 'appointment.created';
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'client_id'      => $this->clientId,
            'staff_id'       => $this->staffId,
            'service_id'     => $this->serviceId,
            'start_at'       => $this->startAt,
            'end_at'         => $this->endAt,
            'channel'        => $this->channel,
            'occurred_at'    => $this->occurredAt->format('Y-m-d H:i:s'),
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

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): string
    {
        return $this->endAt;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }
}
