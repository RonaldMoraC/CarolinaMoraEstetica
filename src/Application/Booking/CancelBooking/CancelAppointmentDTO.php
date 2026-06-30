<?php declare(strict_types=1);

namespace App\Application\Booking\CancelBooking;

/**
 * CancelAppointmentDTO — Objeto de Transferencia de Datos
 *
 * Transporta los datos de entrada para la cancelación de una cita.
 */
class CancelAppointmentDTO
{
    public int $appointmentId;
    public int $userId;
    public ?string $reason;

    public function __construct(
        int    $appointmentId,
        int    $userId,
        ?string $reason = null
    ) {
        $this->appointmentId = $appointmentId;
        $this->userId = $userId;
        $this->reason = $reason;
    }
}
