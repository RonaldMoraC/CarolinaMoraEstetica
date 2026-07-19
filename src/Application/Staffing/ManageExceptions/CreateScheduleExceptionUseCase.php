<?php

declare(strict_types=1);

namespace App\Application\Staffing\ManageExceptions;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\TimeRange;
use App\Domain\Staffing\Entities\ScheduleException;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;

final class CreateScheduleExceptionUseCase
{
    public function __construct(private readonly StaffingRepositoryInterface $staffingRepository) {}

    public function execute(int $professionalId, string $start, string $end, string $reason, bool $fullDay = false): ScheduleException
    {
        $profile = $this->staffingRepository->findProfessionalProfileById($professionalId);
        if ($profile === null || ($profile['operational_status'] ?? 'INACTIVE') !== 'ACTIVE') {
            throw new DomainException('El profesional debe estar activo para crear excepciones.', 'Staffing');
        }

        $timeRange = TimeRange::fromStrings($start, $end);
        $exceptions = $this->staffingRepository->getExceptionsInTimeRange(
            $professionalId,
            $timeRange->getStart()->format('Y-m-d H:i:s'),
            $timeRange->getEnd()->format('Y-m-d H:i:s')
        );

        foreach ($exceptions as $existing) {
            if ($existing->getTimeRange()->overlapsWith($timeRange)) {
                throw new DomainException('La excepción de agenda se solapa con otra excepción ya registrada.', 'Staffing');
            }
        }

        $exception = new ScheduleException($professionalId, $timeRange, $reason, $fullDay);
        $this->staffingRepository->saveScheduleException($exception);

        return $exception;
    }
}
