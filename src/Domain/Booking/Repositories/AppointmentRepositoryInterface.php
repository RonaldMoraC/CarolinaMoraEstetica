<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

interface AppointmentRepositoryInterface
{
    public function findById(int $appointmentId): ?array;
    public function create(array $data): int;
    public function updateStatus(int $appointmentId, string $newStatus, int $actorId, string $reason): bool;
    public function checkIn(int $appointmentId, int $actorId): bool;
    public function complete(int $appointmentId, int $actorId): bool;
    public function markNoShow(int $appointmentId, int $actorId, string $reason): bool;
    public function cancel(int $appointmentId, int $actorId, string $reason): bool;
    public function getAppointmentsForDateRange(string $startDate, string $endDate, ?int $professionalId = null): array;
    public function getAppointmentsToday(): array;
}