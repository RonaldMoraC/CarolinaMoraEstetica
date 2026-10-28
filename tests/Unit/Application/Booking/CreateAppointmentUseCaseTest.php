<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Booking;

use App\Application\Booking\CreateBooking\CreateAppointmentDTO;
use App\Application\Booking\CreateBooking\CreateAppointmentUseCase;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Application\Shared\Audit\AuditService;
use App\Infrastructure\Shared\Audit\AuditLogRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class CreateAppointmentUseCaseTest extends TestCase
{
    public function test_rejects_appointment_when_there_is_a_conflicting_existing_booking(): void
    {
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $serviceRepo = $this->createMock(ServiceRepositoryInterface::class);
        $staffingRepo = $this->createMock(StaffingRepositoryInterface::class);

        $auditLogRepo = $this->createMock(AuditLogRepositoryInterface::class);
        $auditService = new AuditService($auditLogRepo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('exec')->willReturn(1);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);

        $staffingRepo->method('lockProfessionalWorkSchedule')->willReturn([
            'work_schedule_id' => 1,
            'professional_profile_id' => 10,
            'branch_id' => 1,
            'day_of_week' => 2,
        ]);
        $staffingRepo->method('getExceptionsInTimeRange')->willReturn([]);

        $serviceRepo->method('findById')->willReturn([
            'service_id' => 7,
            'name' => 'Corte de cabello',
            'base_price' => 50000,
            'duration_minutes' => 60,
        ]);

        $appointmentRepo->expects($this->once())
            ->method('hasOverlappingAppointment')
            ->willReturn(true);

        $useCase = new CreateAppointmentUseCase(
            $appointmentRepo,
            $serviceRepo,
            $staffingRepo,
            $auditService,
            $pdo
        );

        $dto = new CreateAppointmentDTO(
            serviceId: 7,
            clientProfileId: 1,
            professionalProfileId: 10,
            branchId: 1,
            scheduledDate: '2030-09-02',
            scheduledTime: '09:00'
        );

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('El horario seleccionado ya se encuentra ocupado');

        $useCase->execute($dto);
    }
}
