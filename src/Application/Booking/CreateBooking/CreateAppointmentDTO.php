<?php declare(strict_types=1);

namespace App\Application\Booking\CreateBooking;

/**
 * CreateAppointmentDTO — Objeto de Transferencia de Datos
 *
 * Transporta los datos de entrada del endpoint POST /api/v1/booking/appointments
 * desde la periferia HTTP hacia el caso de uso sin exponer la entidad de dominio.
 */
class CreateAppointmentDTO
{
    public int $serviceId;
    public int $clientProfileId;
    public int $professionalProfileId;
    public int $branchId;
    public string $scheduledDate;
    public string $scheduledTime;
    public ?int $promotionId = null;
    public ?string $notes = null;

    public function __construct(
        int    $serviceId,
        int    $clientProfileId,
        int    $professionalProfileId,
        int    $branchId,
        string $scheduledDate,
        string $scheduledTime,
        ?int   $promotionId = null,
        ?string $notes = null
    ) {
        $this->serviceId = $serviceId;
        $this->clientProfileId = $clientProfileId;
        $this->professionalProfileId = $professionalProfileId;
        $this->branchId = $branchId;
        $this->scheduledDate = $scheduledDate;
        $this->scheduledTime = $scheduledTime;
        $this->promotionId = $promotionId;
        $this->notes = $notes;
    }
}
