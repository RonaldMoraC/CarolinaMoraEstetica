<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Booking;

use App\Application\Booking\CreateBooking\CreateAppointmentDTO;
use App\Application\Booking\CreateBooking\CreateAppointmentUseCase;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\NotificationQueueRepositoryInterface;
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

    public function test_creates_appointment_successfully_and_enqueues_notification(): void
    {
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $serviceRepo = $this->createMock(ServiceRepositoryInterface::class);
        $staffingRepo = $this->createMock(StaffingRepositoryInterface::class);
        $notificationRepo = $this->createMock(NotificationQueueRepositoryInterface::class);

        $auditLogRepo = $this->createMock(AuditLogRepositoryInterface::class);
        $auditService = new AuditService($auditLogRepo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('exec')->willReturn(1);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);

        $staffingRepo->method('lockProfessionalWorkSchedule')->willReturn([
            'work_schedule_id' => 1,
            'professional_profile_id' => 10,
            'branch_id' => 1,
            'day_of_week' => 2,
        ]);
        $staffingRepo->method('getExceptionsInTimeRange')->willReturn([]);

        $serviceRepo->method('findById')->willReturn([
            'service_id' => 7,
            'name' => 'Limpieza Facial Profunda',
            'base_price' => 35.0,
            'duration_minutes' => 60,
        ]);

        $appointmentRepo->method('hasOverlappingAppointment')->willReturn(false);
        $appointmentRepo->expects($this->once())
            ->method('save')
            ->willReturn(101);

        // Mock statement for client user query
        $stmtClient = $this->createMock(PDOStatement::class);
        $stmtClient->method('execute')->willReturn(true);
        $stmtClient->method('fetch')->willReturn([
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'auth_phone' => '+573009876543',
            'email' => 'ana@example.com'
        ]);

        // Mock statement for professional user query
        $stmtProf = $this->createMock(PDOStatement::class);
        $stmtProf->method('execute')->willReturn(true);
        $stmtProf->method('fetch')->willReturn([
            'first_name' => 'Carolina',
            'last_name' => 'Mora'
        ]);

        // Mock statement for branch query
        $stmtBranch = $this->createMock(PDOStatement::class);
        $stmtBranch->method('execute')->willReturn(true);
        $stmtBranch->method('fetch')->willReturn([
            'branch_name' => 'Carolina Mora Estética y SPA'
        ]);

        $pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $stmtClient,
            $stmtProf,
            $stmtBranch
        );

        $notificationRepo->expects($this->once())
            ->method('enqueue')
            ->with(
                $this->equalTo(1),
                $this->equalTo('+573009876543'),
                $this->callback(function (array $params): bool {
                    return $params['client_name'] === 'Ana Gomez'
                        && $params['service_name'] === 'Limpieza Facial Profunda'
                        && $params['professional_name'] === 'Carolina Mora'
                        && $params['appointment_id'] === 101;
                })
            )
            ->willReturn(1);

        $useCase = new CreateAppointmentUseCase(
            $appointmentRepo,
            $serviceRepo,
            $staffingRepo,
            $auditService,
            $pdo,
            $notificationRepo
        );

        $dto = new CreateAppointmentDTO(
            serviceId: 7,
            clientProfileId: 1,
            professionalProfileId: 10,
            branchId: 1,
            scheduledDate: '2030-09-02',
            scheduledTime: '09:00'
        );

        $result = $useCase->execute($dto);

        $this->assertSame(101, $result['appointment_id']);
        $this->assertSame('Limpieza Facial Profunda', $result['service_name']);
        $this->assertSame('Carolina Mora', $result['professional_name']);
        $this->assertSame('Ana Gomez', $result['client_name']);
        $this->assertTrue($result['notification_enqueued']);
        $this->assertStringContainsString('https://wa.me/', $result['whatsapp_direct_url']);
    }
}
