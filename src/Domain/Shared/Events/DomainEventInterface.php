<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * DomainEventInterface — Contrato de Evento de Dominio
 *
 * Define el contrato mínimo que todo evento de dominio debe implementar
 * para ser despachado por el EventDispatcher y consumido por los listeners.
 *
 * Principios DDD aplicados:
 * - Los eventos son inmutables: representan algo que ya ocurrió.
 * - El nombre del evento debe expresar un hecho de negocio en pasado
 *   (ej. AppointmentCreatedEvent, AppointmentCancelledEvent).
 * - Los listeners no deben modificar el evento; solo reaccionar a él.
 *
 * Implementación mínima esperada:
 * ```php
 * final class AppointmentCreatedEvent implements DomainEventInterface
 * {
 *     public function __construct(
 *         private readonly string $appointmentId,
 *         private readonly \DateTimeImmutable $occurredAt
 *     ) {}
 *
 *     public function getEventName(): string
 *     {
 *         return 'appointment.created';
 *     }
 *
 *     public function getOccurredAt(): \DateTimeImmutable
 *     {
 *         return $this->occurredAt;
 *     }
 *
 *     public function toArray(): array
 *     {
 *         return ['appointment_id' => $this->appointmentId];
 *     }
 * }
 * ```
 */
interface DomainEventInterface
{
    /**
     * Retorna el nombre canónico del evento en formato dot-notation.
     *
     * Convención: '{módulo}.{hecho_en_pasado}'
     * Ejemplos: 'appointment.created', 'payment.confirmed', 'user.registered'
     *
     * @return string Nombre único e inmutable del evento.
     */
    public function getEventName(): string;

    /**
     * Retorna el instante exacto en que el evento ocurrió.
     *
     * IMPORTANTE: Usar DateTimeImmutable con zona horaria America/Bogota
     * conforme al Skill 7 (Time-Zone Guard) del sistema.
     *
     * @return \DateTimeImmutable Timestamp del evento.
     */
    public function getOccurredAt(): \DateTimeImmutable;

    /**
     * Serializa el payload del evento a un array primitivo.
     *
     * Utilizado por:
     * - El sistema de logs de auditoría (system_audit_log).
     * - La cola de notificaciones WhatsApp (wa_notification_queue).
     * - El sistema de análisis y métricas.
     *
     * @return array<string, mixed> Payload primitivo del evento.
     */
    public function toArray(): array;
}
