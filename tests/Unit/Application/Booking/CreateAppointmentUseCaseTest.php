<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Booking;

use App\Application\Booking\CreateBooking\CreateAppointmentDTO;
use App\Application\Booking\CreateBooking\CreateAppointmentUseCase;
use App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Infrastructure\Shared\Audit\AuditLogRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateAppointmentUseCaseTest extends TestCase
{
    public function test_rejects_appointment_when_there_is_a_conflicting_existing_booking(): void
    {
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $serviceRepo = $this->createMock(ServiceRepositoryInterface::class);
        $staffingRepo = $this->createMock(StaffingRepositoryInterface::class);
        $staffingPersistenceRepo = $this->createMock(\App\Domain\Staffing\Repositories\StaffingRepositoryInterface::class);
        $auditRepo = new class implements AuditLogRepositoryInterface {
            public function insert(
                ?int $actorId,
                string $action,
                string $entityType,
                string|int $entityId,
                array $oldValues = [],
                array $newValues = []
            ): void {
            }
        };

        $staffingRepo->method('findProfessionalProfileById')->willReturn([
            'professional_profile_id' => 10,
            'operational_status' => 'ACTIVE',
        ]);
        $staffingRepo->method('getWorkSchedules')->willReturn([]);
        $staffingPersistenceRepo->method('getWorkSchedules')->willReturn([]);

        $serviceRepo->method('findById')->willReturn([
            'service_id' => 7,
            'name' => 'Corte de cabello',
            'base_price' => 50000,
            'duration_minutes' => 60,
        ]);

        $appointmentRepo->expects($this->once())
            ->method('hasConflictingAppointment')
            ->with(10, '2025-09-02 09:00:00', '2025-09-02 10:00:00')
            ->willReturn(true);
        $appointmentRepo->expects($this->never())->method('create');

        $useCase = new CreateAppointmentUseCase(
            $appointmentRepo,
            $serviceRepo,
            $staffingPersistenceRepo,
            $auditRepo,
            new GetAvailableSlotsUseCase($staffingRepo)
        );

        $dto = new CreateAppointmentDTO(
            serviceId: 7,
            clientProfileId: 1,
            professionalProfileId: 10,
            branchId: 1,
            scheduledDate: '2030-09-02',
            scheduledTime: '09:00'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ya existe una cita en ese rango para el profesional.');

        $useCase->execute($dto);
    }
}
