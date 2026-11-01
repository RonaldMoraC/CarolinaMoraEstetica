<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase;
use App\Domain\Staffing\Entities\WorkSchedule;
use App\Domain\Staffing\Repositories\StaffingRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use DateTimeImmutable;
use DateTimeZone;

/**
 * GetSlotsController
 *
 * Retorna la disponibilidad y ranuras libres de un profesional para una fecha dada.
 * Endpoint: GET /api/v1/staffing/professionals/{id}/schedules?date=YYYY-MM-DD&duration=30&branch_id=1
 */
final class GetSlotsController
{
    public function __construct(
        private readonly StaffingRepositoryInterface $staffingRepository,
        private readonly ?GetAvailableSlotsUseCase $getAvailableSlotsUseCase = null
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $professionalId = (int) ($params['id'] ?? 0);
            $branchId       = (int) ($_GET['branch_id'] ?? 1);
            $date           = trim((string) ($_GET['date'] ?? ''));
            $duration       = (int) ($_GET['duration'] ?? $_GET['duration_minutes'] ?? 30);

            if ($duration <= 0) {
                $duration = 30;
            }

            if ($professionalId <= 0) {
                ResponseHelper::json(statusCode: 400, success: false, message: 'ID de profesional inválido.');
                return;
            }

            // Consultar malla horaria del profesional
            $schedules = $this->staffingRepository->findSchedulesByProfessional($professionalId, $branchId);

            $schedulesData = array_map(function (WorkSchedule $s): array {
                return [
                    'day_of_week'      => $s->getDayOfWeek(),
                    'start_time'       => $s->getStartTime(),
                    'end_time'         => $s->getEndTime(),
                    'lunch_start_time' => $s->getLunchStartTime(),
                    'lunch_end_time'   => $s->getLunchEndTime(),
                ];
            }, $schedules);

            $slots = [];

            // Si se suministró fecha y se dispone del caso de uso de ranuras
            if ($date !== '' && $this->getAvailableSlotsUseCase !== null) {
                $tz = new DateTimeZone('America/Bogota');
                $startDate = new DateTimeImmutable($date . ' 00:00:00', $tz);
                $endDate   = new DateTimeImmutable($date . ' 23:59:59', $tz);

                $availableTimeRanges = $this->getAvailableSlotsUseCase->execute(
                    $professionalId,
                    $branchId,
                    $startDate,
                    $endDate,
                    $duration
                );

                foreach ($availableTimeRanges as $tr) {
                    $slots[] = $tr->getStart()->format('H:i');
                }
            }

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Horarios y disponibilidad consultados exitosamente.',
                data: [
                    'professional_id' => $professionalId,
                    'branch_id'       => $branchId,
                    'date'            => $date !== '' ? $date : null,
                    'slots'           => $slots,
                    'available_slots' => $slots,
                    'schedules'       => $schedulesData
                ]
            );

        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error del Servidor',
                detail: 'Ocurrió un error al consultar los horarios del profesional: ' . $e->getMessage(),
                instance: $_SERVER['REQUEST_URI'] ?? '/api/v1/staffing/schedules'
            );
        }
    }
}
