<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Domain\Staffing\Entities\WorkSchedule;
use App\Infrastructure\Staffing\Persistence\PdoStaffingRepository;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * GetSlotsController
 *
 * Retorna los horarios semanales guardados de un profesional.
 * Usado por admin-horarios.php para cargar la configuración existente.
 */
final class GetSlotsController
{
    public function __construct(
        private readonly PdoStaffingRepository $staffingRepository
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $professionalId = (int) ($params['id'] ?? 0);
            $branchId       = (int) ($_GET['branch_id'] ?? 1);

            if ($professionalId <= 0) {
                ResponseHelper::json(
                    statusCode: 400,
                    success: false,
                    message: 'ID de profesional inválido.'
                );
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