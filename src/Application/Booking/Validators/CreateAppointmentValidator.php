<?php declare(strict_types=1);

namespace App\Application\Booking\Validators;

use InvalidArgumentException;

/**
 * CreateAppointmentValidator — Validación perimetral
 *
 * Verifica que los datos del DTO cumplan las reglas de formato
 * antes de pasar al caso de uso.
 */
class CreateAppointmentValidator
{
    public static function validate(
        int    $serviceId,
        int    $professionalProfileId,
        int    $branchId,
        string $scheduledDate,
        string $scheduledTime
    ): void {
        if ($serviceId <= 0) {
            throw new InvalidArgumentException('service_id debe ser un entero positivo.');
        }

        if ($professionalProfileId <= 0) {
            throw new InvalidArgumentException('professional_profile_id debe ser un entero positivo.');
        }

        if ($branchId <= 0) {
            throw new InvalidArgumentException('branch_id debe ser un entero positivo.');
        }

        $parsedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $scheduledDate, new \DateTimeZone('America/Bogota'));
        if ($parsedDate === false) {
            throw new InvalidArgumentException('scheduled_date debe ser una fecha válida en formato YYYY-MM-DD.');
        }

        $parsedTime = \DateTimeImmutable::createFromFormat('H:i', $scheduledTime, new \DateTimeZone('America/Bogota'));
        if ($parsedTime === false) {
            throw new InvalidArgumentException('scheduled_time debe ser una hora válida en formato HH:MM.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $scheduledTs = $parsedDate->setTime((int) $parsedTime->format('H'), (int) $parsedTime->format('i'));

        if ($scheduledTs <= $now) {
            throw new InvalidArgumentException('La fecha y hora de la cita debe ser futura.');
        }
    }
}
