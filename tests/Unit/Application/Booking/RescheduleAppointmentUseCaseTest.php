<?php
declare(strict_types=1);

namespace Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use App\Application\Booking\RescheduleBooking\RescheduleAppointmentUseCase;
use App\Application\Booking\RescheduleBooking\RescheduleAppointmentDTO;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Entities\Appointment;
use App\Application\Shared\Audit\AuditService;
use App\Infrastructure\Shared\Audit\AuditLogRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;
use DateTimeZone;

class RescheduleAppointmentUseCaseTest extends TestCase
{
    /** Construye un AuditService real sobre un repositorio en memoria (AuditService es final). */
    private function makeAuditService(): AuditService
    {
        $auditRepo = $this->createMock(AuditLogRepositoryInterface::class);
        return new AuditService($auditRepo);
    }
    private function makeMockAppointment(
        int $id,
        int $clientId,
        int $professionalId,
        string $status = 'CONFIRMED',
        string $start = '2026-12-01 10:00:00',
        string $end   = '2026-12-01 11:00:00'
    ): Appointment {
        $tz = new DateTimeZone('America/Bogota');
        $appt = $this->createMock(Appointment::class);
        $appt->method('getAppointmentId')->willReturn($id);
        $appt->method('getClientProfileId')->willReturn($clientId);
        $appt->method('getProfessionalProfileId')->willReturn($professionalId);
        $appt->method('getAppointmentStatus')->willReturn($status);
        $appt->method('getScheduledTimestamp')
             ->willReturn(new DateTimeImmutable($start, $tz));
        $appt->method('getEstimatedEndTimestamp')
             ->willReturn(new DateTimeImmutable($end, $tz));
        $appt->method('belongsToClient')
             ->willReturnCallback(fn(int $cid) => $cid === $clientId);
        $appt->method('canBeRescheduled')
             ->willReturn(in_array($status, ['PENDING', 'CONFIRMED'], true));
        return $appt;
    }

    public function testShouldRescheduleSuccessfully(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        $appt = $this->makeMockAppointment(42, 7, 3);

        $repo->method('findById')->with(42)->willReturn($appt);
        $repo->method('hasOverlappingAppointment')->willReturn(false);
        $repo->method('reschedule')->willReturn(true);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(
            appointmentId: 42,
            userId: 7,
            userRole: 'CLIENT',
            newScheduledDate: '2026-12-20',
            newScheduledTime: '14:00',
            reason: 'Cambio de agenda'
        );

        $result = $useCase->execute($dto);

        $this->assertSame(42, $result['appointment_id']);
        $this->assertStringContainsString('2026-12-20', $result['new_scheduled_timestamp']);
        $this->assertStringContainsString('14:00:00', $result['new_scheduled_timestamp']);
    }

    public function testShouldThrowNotFoundWhenAppointmentMissing(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        $repo->method('findById')->willReturn(null);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(999, 7, 'CLIENT', '2026-12-20', '14:00');

        $this->expectException(NotFoundException::class);
        $useCase->execute($dto);
    }

    public function testShouldThrowDomainExceptionWhenClientOwnershipViolated(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        // Cita pertenece al cliente 7, pero el actor es cliente 99
        $appt = $this->makeMockAppointment(10, 7, 3);
        $repo->method('findById')->willReturn($appt);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(10, 99, 'CLIENT', '2026-12-20', '14:00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/permisos/');
        $useCase->execute($dto);
    }

    public function testShouldThrowDomainExceptionWhenStatusNotReschedulable(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        $appt = $this->makeMockAppointment(5, 7, 3, 'COMPLETED');
        $repo->method('findById')->willReturn($appt);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(5, 7, 'CLIENT', '2026-12-20', '14:00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/reprogramada/');
        $useCase->execute($dto);
    }

    public function testShouldThrowDomainExceptionForPastDateTime(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        $appt = $this->makeMockAppointment(20, 7, 3);
        $repo->method('findById')->willReturn($appt);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(20, 7, 'CLIENT', '2020-01-01', '08:00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/pasada/');
        $useCase->execute($dto);
    }

    public function testShouldThrowConflictWhenSlotIsOccupied(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        $appt = $this->makeMockAppointment(30, 7, 3);
        $repo->method('findById')->willReturn($appt);
        $repo->method('hasOverlappingAppointment')->willReturn(true); // slot ocupado

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(30, 7, 'CLIENT', '2026-12-20', '14:00');

        $this->expectException(ConflictException::class);
        $useCase->execute($dto);
    }

    public function testStaffCanRescheduleOtherClientAppointment(): void
    {
        $repo  = $this->createMock(AppointmentRepositoryInterface::class);
        $audit = $this->makeAuditService();

        // Cita pertenece al cliente 7, actor es MANAGER (id 1)
        $appt = $this->makeMockAppointment(50, 7, 3);
        $repo->method('findById')->willReturn($appt);
        $repo->method('hasOverlappingAppointment')->willReturn(false);
        $repo->method('reschedule')->willReturn(true);

        $useCase = new RescheduleAppointmentUseCase($repo, $audit);

        $dto = new RescheduleAppointmentDTO(50, 1, 'MANAGER', '2026-12-20', '14:00');
        $result = $useCase->execute($dto);

        $this->assertSame(50, $result['appointment_id']);
    }
}
