<?php
declare(strict_types=1);

namespace App\Application\Booking\RescheduleBooking;

/**
 * RescheduleAppointmentDTO — Parámetros para la reprogramación de una cita
 */
final readonly class RescheduleAppointmentDTO
{
    public function __construct(
        public int $appointmentId,
        public int $userId,
        public string $userRole,
        public string $newScheduledDate,
        public string $newScheduledTime,
        public ?string $reason = null
    ) {}
}
