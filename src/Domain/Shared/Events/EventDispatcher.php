<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * EventDispatcher — Despachador de Eventos de Dominio
 *
 * Implementación in-process del patrón Observer para desacoplar
 * el núcleo de negocio (Booking, Billing) de los efectos secundarios
 * (Notificaciones WhatsApp, Auditoría, Analíticas).
 *
 * Características:
 * - Registro de múltiples listeners por nombre de evento.
 * - Soporte para listeners globales (comodín '*') que reciben todos los eventos.
 * - Propagación detenible: un listener puede devolver false para interrumpir
 *   la cadena de notificación.
 * - Diseñado para ser inyectado como dependencia (Constructor Injection).
 *
 * Uso en Caso de Uso:
 * ```php
 * final class CreateAppointmentUseCase
 * {
 *     public function __construct(
 *         private readonly AppointmentRepositoryInterface $repo,
 *         private readonly EventDispatcher $dispatcher
 *     ) {}
 *
 *     public function execute(CreateAppointmentCommand $cmd): void
 *     {
 *         $appointment = Appointment::create(...);
 *         $this->repo->save($appointment);
 *
 *         $this->dispatcher->dispatch(new AppointmentCreatedEvent($appointment->getId()));
 *     }
 * }
 * ```
 *
 * Registro de listeners en bootstrap.php:
 * ```php
 * $dispatcher->listen('appointment.created', function(DomainEventInterface $event) {
 *     // Encolar notificación WhatsApp
 * });
 * $dispatcher->listen('*', function(DomainEventInterface $event) {
 *     // Registrar en system_audit_log
 * });
 * ```
 */
final class EventDispatcher
{
    /**
     * Mapa de listeners indexado por nombre de evento.
     * El comodín '*' almacena listeners globales.
     *
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    // -------------------------------------------------------------------------
    // Registro de Listeners
    // -------------------------------------------------------------------------

    /**
     * Registra un callable como listener de un evento específico.
     *
     * @param string   $eventName Nombre del evento en dot-notation (ej. 'appointment.created')
     *                            o '*' para escuchar todos los eventos.
     * @param callable $listener  Callable con firma: function(DomainEventInterface $event): bool|void
     *                            Retornar false detiene la propagación a listeners subsiguientes.
     */
    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Registra múltiples listeners de una vez.
     *
     * @param array<string, callable|callable[]> $listenersMap
     *   Mapa de eventName => listener o eventName => [listener1, listener2].
     */
    public function registerMany(array $listenersMap): void
    {
        foreach ($listenersMap as $eventName => $listenersForEvent) {
            if (is_callable($listenersForEvent)) {
                $this->listen((string) $eventName, $listenersForEvent);
                continue;
            }

            if (is_array($listenersForEvent)) {
                foreach ($listenersForEvent as $listener) {
                    if (is_callable($listener)) {
                        $this->listen((string) $eventName, $listener);
                    }
                }
            }
        }
    }

    /**
     * Elimina todos los listeners de un evento específico.
     * Útil en pruebas unitarias para aislar comportamientos.
     *
     * @param string $eventName Nombre del evento a limpiar.
     */
    public function removeListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    /**
     * Elimina todos los listeners registrados.
     * Útil en entornos de prueba.
     */
    public function clearAll(): void
    {
        $this->listeners = [];
    }

    // -------------------------------------------------------------------------
    // Despacho de Eventos
    // -------------------------------------------------------------------------

    /**
     * Despacha un evento de dominio a todos sus listeners registrados.
     *
     * El orden de notificación es:
     * 1. Listeners del evento específico (por nombre de evento).
     * 2. Listeners globales (comodín '*').
     *
     * Si un listener retorna false, se detiene la propagación en esa cadena.
     *
     * @param DomainEventInterface $event El evento de dominio a despachar.
     */
    public function dispatch(DomainEventInterface $event): void
    {
        $eventName = $event->getEventName();

        // Notificar listeners del evento específico
        $this->notifyListeners($eventName, $event);

        // Notificar listeners globales (comodín), evitando duplicados si el
        // evento en sí fuera '*' (situación que no debería darse en producción)
        if ($eventName !== '*') {
            $this->notifyListeners('*', $event);
        }
    }

    /**
     * Despacha una colección de eventos en el orden en que fueron encolados.
     *
     * Patrón común para entidades que acumulan eventos durante su ciclo
     * de vida antes de ser persistidas (Domain Events collection pattern).
     *
     * @param DomainEventInterface[] $events Lista de eventos a despachar.
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    // -------------------------------------------------------------------------
    // Introspección (útil en pruebas y debugging)
    // -------------------------------------------------------------------------

    /**
     * Verifica si hay listeners registrados para un nombre de evento.
     *
     * @param string $eventName Nombre del evento.
     */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    /**
     * Retorna el número de listeners registrados para un nombre de evento.
     *
     * @param string $eventName Nombre del evento.
     */
    public function countListeners(string $eventName): int
    {
        return count($this->listeners[$eventName] ?? []);
    }

    // -------------------------------------------------------------------------
    // Privado
    // -------------------------------------------------------------------------

    /**
     * Itera y notifica los listeners de una clave específica.
     *
     * @param string               $key   Clave del mapa de listeners.
     * @param DomainEventInterface $event Evento a pasar a cada listener.
     */
    private function notifyListeners(string $key, DomainEventInterface $event): void
    {
        foreach ($this->listeners[$key] ?? [] as $listener) {
            $result = ($listener)($event);
            if ($result === false) {
                break;
            }
        }
    }
}
