<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase;
use App\Domain\Staffing\Entities\WorkSchedule;
use App\Infrastructure\Staffing\Persistence\PdoStaffingRepository;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * GetSlotsController
 *
 * Retorna la malla horaria de un profesional y, si se solicita, sus ranuras disponibles.
 */
final class GetSlotsController
{
    public function __construct(
        private readonly PdoStaffingRepository $staffingRepository,
        private readonly ?GetAvailableSlotsUseCase $getAvailableSlotsUseCase = null
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $professionalId = (int) ($params['id'] ?? 0);
            $branchId       = (int) ($_GET['branch_id'] ?? 1);
            $date           = (string) ($_GET['date'] ?? '');
            $includeSlots   = (bool) ($_GET['include_slots'] ?? false);

            if ($professionalId <= 0) {
                ResponseHelper::json(statusCode: 400, success: false, message: 'ID de profesional inválido.');
            }

            /** @var WorkSchedule[] $schedules */
            $schedules = $this->staffingRepository->getWorkSchedules($professionalId, $branchId);

            $data = array_map(function (WorkSchedule $s): array {
                return [
                    'day_of_week' => $s->getDayOfWeek(),
                    'start_time'  => $s->getStartTime(),
                    'end_time'    => $s->getEndTime(),
                ];
            }, $schedules);

            if ($includeSlots && $date !== '' && $this->getAvailableSlotsUseCase !== null) {
                $data['available_slots'] = $this->getAvailableSlotsUseCase->execute($professionalId, $branchId, $date);
            }

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Horarios obtenidos exitosamente.',
                data: array_values($data)
            );

        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error del Servidor',
                detail: 'Ocurrió un error al consultar los horarios del profesional.',
                instance: $_SERVER['REQUEST_URI'] ?? '/'
            );
        }
    }
}