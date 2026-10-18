<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

use App\Domain\Booking\Entities\Appointment;

interface AppointmentRepositoryInterface
{
    public function findById(int $appointmentId): ?Appointment;
    public function save(Appointment $appointment): int;
    public function updateStatus(int $appointmentId, string $newStatus, int $actorId, string $reason): bool;
    public function checkIn(int $appointmentId, int $actorId): bool;
    public function complete(int $appointmentId, int $actorId): bool;
    public function markNoShow(int $appointmentId, int $actorId, string $reason): bool;
    public function cancel(int $appointmentId, int $actorId, string $reason): bool;
    public function getAppointmentsForDateRange(string $startDate, string $endDate, ?int $professionalId = null): array;
    public function getAppointmentsToday(): array;

    /**
     * Verifica si existe alguna cita que se solape con el rango de tiempo dado para un profesional específico.
     *
     * @param int    $professionalId ID del profesional.
     * @param string $startTimestamp Fecha/Hora inicio 'Y-m-d H:i:s' en America/Bogota.
     * @param string $endTimestamp   Fecha/Hora fin 'Y-m-d H:i:s' en America/Bogota.
     * @return bool true si existe solapamiento, false si está libre.
     */
    public function hasOverlappingAppointment(int $professionalId, string $startTimestamp, string $endTimestamp): bool;
}
