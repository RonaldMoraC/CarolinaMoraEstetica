<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * TimeRange — Value Object para Rangos de Tiempo Inmutables
 *
 * Encapsula un intervalo de tiempo con hora de inicio y hora de fin.
 * Garantiza que los rangos horarios sean coherentes (start < end) y
 * proporciona lógica de detección de solapamientos para la malla horaria.
 *
 * IMPORTANTE: Usa exclusivamente DateTimeImmutable con zona horaria
 * explícita 'America/Bogota' conforme al Skill 7 (Time-Zone Guard).
 *
 * Uso básico:
 * ```php
 * $tz    = new DateTimeZone('America/Bogota');
 * $turno = TimeRange::create(
 *     new DateTimeImmutable('2025-09-01 09:00:00', $tz),
 *     new DateTimeImmutable('2025-09-01 11:30:00', $tz)
 * );
 * $cita  = TimeRange::create(
 *     new DateTimeImmutable('2025-09-01 10:00:00', $tz),
 *     new DateTimeImmutable('2025-09-01 11:00:00', $tz)
 * );
 * $turno->overlapsWith($cita); // true  → conflicto de agenda
 * $turno->getDurationInMinutes(); // 150
 * ```
 *
 * Invariantes del dominio:
 * - start debe ser estrictamente anterior a end (start < end).
 * - Un rango donde start >= end lanza DomainException.
 * - La zona horaria debe ser America/Bogota para todos los rangos del dominio.
 */
final class TimeRange
{
    /** Zona horaria canónica del sistema. */
    private const TIMEZONE = 'America/Bogota';

    private function __construct(
        private readonly \DateTimeImmutable $start,
        private readonly \DateTimeImmutable $end
    ) {}

    // -------------------------------------------------------------------------
    // Constructores estáticos (Factory Methods)
    // -------------------------------------------------------------------------

    /**
     * Crea un TimeRange validado a partir de dos instancias DateTimeImmutable.
     *
     * @param \DateTimeImmutable $start Inicio del rango.
     * @param \DateTimeImmutable $end   Fin del rango.
     * @throws DomainException Si start >= end.
     */
    public static function create(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        // Normalizar zona horaria a America/Bogota
        $tz    = new \DateTimeZone(self::TIMEZONE);
        $start = $start->setTimezone($tz);
        $end   = $end->setTimezone($tz);

        if ($start >= $end) {
            throw new DomainException(
                sprintf(
                    'La hora de inicio (%s) debe ser anterior a la hora de fin (%s).',
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s')
                ),
                'TimeRange'
            );
        }

        return new self($start, $end);
    }

    /**
     * Crea un TimeRange a partir de strings de fecha/hora.
     *
     * @param string $startStr Formato: 'Y-m-d H:i:s' (ej. '2025-09-01 09:00:00').
     * @param string $endStr   Formato: 'Y-m-d H:i:s'.
     * @throws DomainException Si los strings son inválidos o start >= end.
     */
    public static function fromStrings(string $startStr, string $endStr): self
    {
        $tz = new \DateTimeZone(self::TIMEZONE);

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startStr, $tz);
        $end   = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endStr, $tz);

        if ($start === false) {
            throw new DomainException(
                'El formato de la hora de inicio es inválido: "' . $startStr . '". Use Y-m-d H:i:s.',
                'TimeRange'
            );
        }

        if ($end === false) {
            throw new DomainException(
                'El formato de la hora de fin es inválido: "' . $endStr . '". Use Y-m-d H:i:s.',
                'TimeRange'
            );
        }

        return self::create($start, $end);
    }

    // -------------------------------------------------------------------------
    // Lectores (Getters)
    // -------------------------------------------------------------------------

    /**
     * Retorna la hora de inicio del rango.
     */
    public function getStart(): \DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Retorna la hora de fin del rango.
     */
    public function getEnd(): \DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Retorna la duración del rango en minutos enteros.
     */
    public function getDurationInMinutes(): int
    {
        $diff = $this->start->diff($this->end);
        return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    }

    /**
     * Retorna la duración del rango en segundos.
     */
    public function getDurationInSeconds(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    // -------------------------------------------------------------------------
    // Lógica de Negocio — Solapamiento (Crítica para la Malla Horaria)
    // -------------------------------------------------------------------------

    /**
     * Determina si este rango horario se solapa con otro.
     *
     * Dos rangos [A_start, A_end) y [B_start, B_end) se solapan si y solo si:
     *   A_start < B_end  Y  B_start < A_end
     *
     * Los rangos que solo comparten el instante exacto de inicio/fin
     * (contacto puntual) NO se consideran solapados, permitiendo citas
     * consecutivas sin conflicto. (ej. [09:00,10:00) y [10:00,11:00))
     *
     * @param TimeRange $other El otro rango horario a comparar.
     * @return bool true si existe solapamiento real, false si son contiguos o disjuntos.
     */
    public function overlapsWith(TimeRange $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * Determina si este rango contiene completamente al otro.
     *
     * @param TimeRange $other El rango que se comprueba si está contenido.
     * @return bool true si [other.start, other.end] ⊆ [this.start, this.end].
     */
    public function contains(TimeRange $other): bool
    {
        return $this->start <= $other->start && $this->end >= $other->end;
    }

    /**
     * Determina si un instante de tiempo específico está dentro del rango.
     *
     * @param \DateTimeImmutable $moment Instante a verificar.
     * @return bool true si start <= moment < end.
     */
    public function includesMoment(\DateTimeImmutable $moment): bool
    {
        $tz     = new \DateTimeZone(self::TIMEZONE);
        $moment = $moment->setTimezone($tz);
        return $moment >= $this->start && $moment < $this->end;
    }

    // -------------------------------------------------------------------------
    // Comparaciones e Igualdad
    // -------------------------------------------------------------------------

    /**
     * Compara si dos rangos son idénticos (mismo start y end).
     */
    public function equals(TimeRange $other): bool
    {
        return $this->start->getTimestamp() === $other->start->getTimestamp()
            && $this->end->getTimestamp() === $other->end->getTimestamp();
    }

    /**
     * Representación de cadena del Value Object.
     */
    public function __toString(): string
    {
        return sprintf(
            '[%s → %s]',
            $this->start->format('Y-m-d H:i:s'),
            $this->end->format('Y-m-d H:i:s')
        );
    }
}
