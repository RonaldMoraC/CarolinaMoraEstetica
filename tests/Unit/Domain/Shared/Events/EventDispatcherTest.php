<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Events;

use App\Domain\Shared\Events\DomainEventInterface;
use App\Domain\Shared\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Shared\Events\EventDispatcher
 */
final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    // -------------------------------------------------------------------------
    // Helper: crea un evento de dominio anónimo para los tests
    // -------------------------------------------------------------------------

    private function makeEvent(string $name, array $payload = []): DomainEventInterface
    {
        return new class($name, $payload) implements DomainEventInterface {
            public function __construct(
                private readonly string $name,
                private readonly array  $payload
            ) {}

            public function getEventName(): string
            {
                return $this->name;
            }

            public function getOccurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
            }

            public function toArray(): array
            {
                return $this->payload;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Registro y despacho básico
    // -------------------------------------------------------------------------

    public function test_listener_is_called_when_event_dispatched(): void
    {
        $called = false;

        $this->dispatcher->listen('user.registered', function (DomainEventInterface $event) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch($this->makeEvent('user.registered'));

        $this->assertTrue($called);
    }

    public function test_listener_receives_correct_event(): void
    {
        $received = null;

        $this->dispatcher->listen('appointment.created', function (DomainEventInterface $event) use (&$received): void {
            $received = $event;
        });

        $event = $this->makeEvent('appointment.created', ['id' => 'abc123']);
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function test_no_listener_means_dispatch_is_silent(): void
    {
        // No debe lanzar excepción si no hay listeners registrados
        $this->dispatcher->dispatch($this->makeEvent('orphan.event'));

        $this->assertTrue(true); // llegamos aquí sin excepción
    }

    // -------------------------------------------------------------------------
    // Múltiples listeners por evento
    // -------------------------------------------------------------------------

    public function test_multiple_listeners_are_all_called(): void
    {
        $calls = [];

        $this->dispatcher->listen('payment.confirmed', function () use (&$calls): void {
            $calls[] = 'listener_1';
        });
        $this->dispatcher->listen('payment.confirmed', function () use (&$calls): void {
            $calls[] = 'listener_2';
        });
        $this->dispatcher->listen('payment.confirmed', function () use (&$calls): void {
            $calls[] = 'listener_3';
        });

        $this->dispatcher->dispatch($this->makeEvent('payment.confirmed'));

        $this->assertSame(['listener_1', 'listener_2', 'listener_3'], $calls);
    }

    // -------------------------------------------------------------------------
    // Listener global (comodín '*')
    // -------------------------------------------------------------------------

    public function test_wildcard_listener_receives_any_event(): void
    {
        $receivedNames = [];

        $this->dispatcher->listen('*', function (DomainEventInterface $event) use (&$receivedNames): void {
            $receivedNames[] = $event->getEventName();
        });

        $this->dispatcher->dispatch($this->makeEvent('appointment.created'));
        $this->dispatcher->dispatch($this->makeEvent('payment.confirmed'));

        $this->assertSame(['appointment.created', 'payment.confirmed'], $receivedNames);
    }

    public function test_wildcard_and_specific_listener_both_called(): void
    {
        $log = [];

        $this->dispatcher->listen('appointment.created', function () use (&$log): void {
            $log[] = 'specific';
        });
        $this->dispatcher->listen('*', function () use (&$log): void {
            $log[] = 'wildcard';
        });

        $this->dispatcher->dispatch($this->makeEvent('appointment.created'));

        // Orden: específico primero, luego comodín
        $this->assertSame(['specific', 'wildcard'], $log);
    }

    // -------------------------------------------------------------------------
    // Detención de propagación (return false)
    // -------------------------------------------------------------------------

    public function test_returning_false_stops_propagation(): void
    {
        $calls = [];

        $this->dispatcher->listen('order.placed', function () use (&$calls): false {
            $calls[] = 'listener_1';
            return false; // Detiene propagación
        });
        $this->dispatcher->listen('order.placed', function () use (&$calls): void {
            $calls[] = 'listener_2'; // No debe ejecutarse
        });

        $this->dispatcher->dispatch($this->makeEvent('order.placed'));

        $this->assertSame(['listener_1'], $calls);
    }

    // -------------------------------------------------------------------------
    // dispatchAll
    // -------------------------------------------------------------------------

    public function test_dispatch_all_calls_listeners_for_each_event(): void
    {
        $received = [];

        $this->dispatcher->listen('*', function (DomainEventInterface $e) use (&$received): void {
            $received[] = $e->getEventName();
        });

        $events = [
            $this->makeEvent('appointment.created'),
            $this->makeEvent('appointment.cancelled'),
            $this->makeEvent('payment.confirmed'),
        ];

        $this->dispatcher->dispatchAll($events);

        $this->assertSame(
            ['appointment.created', 'appointment.cancelled', 'payment.confirmed'],
            $received
        );
    }

    // -------------------------------------------------------------------------
    // registerMany
    // -------------------------------------------------------------------------

    public function test_register_many_registers_multiple_listeners(): void
    {
        $log = [];

        $this->dispatcher->registerMany([
            'event.a' => function () use (&$log): void { $log[] = 'a'; },
            'event.b' => function () use (&$log): void { $log[] = 'b'; },
        ]);

        $this->dispatcher->dispatch($this->makeEvent('event.a'));
        $this->dispatcher->dispatch($this->makeEvent('event.b'));

        $this->assertSame(['a', 'b'], $log);
    }

    public function test_register_many_accepts_array_of_listeners_per_event(): void
    {
        $log = [];

        $this->dispatcher->registerMany([
            'event.x' => [
                function () use (&$log): void { $log[] = 'x1'; },
                function () use (&$log): void { $log[] = 'x2'; },
            ],
        ]);

        $this->dispatcher->dispatch($this->makeEvent('event.x'));

        $this->assertSame(['x1', 'x2'], $log);
    }

    // -------------------------------------------------------------------------
    // Introspección
    // -------------------------------------------------------------------------

    public function test_has_listeners_returns_true_when_registered(): void
    {
        $this->dispatcher->listen('foo.bar', fn() => null);

        $this->assertTrue($this->dispatcher->hasListeners('foo.bar'));
    }

    public function test_has_listeners_returns_false_when_none_registered(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('nonexistent.event'));
    }

    public function test_count_listeners_returns_correct_count(): void
    {
        $this->dispatcher->listen('counted.event', fn() => null);
        $this->dispatcher->listen('counted.event', fn() => null);

        $this->assertSame(2, $this->dispatcher->countListeners('counted.event'));
    }

    // -------------------------------------------------------------------------
    // Limpieza (útil en entornos de prueba)
    // -------------------------------------------------------------------------

    public function test_remove_listeners_clears_specific_event(): void
    {
        $called = false;

        $this->dispatcher->listen('temp.event', function () use (&$called): void {
            $called = true;
        });

        $this->dispatcher->removeListeners('temp.event');
        $this->dispatcher->dispatch($this->makeEvent('temp.event'));

        $this->assertFalse($called);
        $this->assertFalse($this->dispatcher->hasListeners('temp.event'));
    }

    public function test_clear_all_removes_all_listeners(): void
    {
        $this->dispatcher->listen('event.a', fn() => null);
        $this->dispatcher->listen('event.b', fn() => null);
        $this->dispatcher->listen('*', fn() => null);

        $this->dispatcher->clearAll();

        $this->assertFalse($this->dispatcher->hasListeners('event.a'));
        $this->assertFalse($this->dispatcher->hasListeners('event.b'));
        $this->assertFalse($this->dispatcher->hasListeners('*'));
    }

    // -------------------------------------------------------------------------
    // Listener específico no recibe eventos de otros nombres
    // -------------------------------------------------------------------------

    public function test_specific_listener_not_called_for_different_event(): void
    {
        $called = false;

        $this->dispatcher->listen('appointment.created', function () use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch($this->makeEvent('appointment.cancelled'));

        $this->assertFalse($called);
    }
}
