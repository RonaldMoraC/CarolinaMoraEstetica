<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Application\Staffing\ManageExceptions\CreateScheduleExceptionUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

final class ScheduleExceptionController
{
    public function __construct(private readonly CreateScheduleExceptionUseCase $createScheduleExceptionUseCase) {}

    public function handle(array $params = []): void
    {
        try {
            $professionalId = (int) ($params['professional_id'] ?? ($_POST['professional_id'] ?? 0));
            $start = (string) ($params['start'] ?? ($_POST['start'] ?? ''));
            $end = (string) ($params['end'] ?? ($_POST['end'] ?? ''));
            $reason = (string) ($params['reason'] ?? ($_POST['reason'] ?? 'PERSONAL_TIME'));
            $fullDay = (bool) ($params['full_day'] ?? ($_POST['full_day'] ?? false));

            if ($professionalId <= 0 || $start === '' || $end === '') {
                ResponseHelper::json(statusCode: 400, success: false, message: 'Datos incompletos para registrar la excepción.');
            }

            $exception = $this->createScheduleExceptionUseCase->execute($professionalId, $start, $end, $reason, $fullDay);

            ResponseHelper::json(
                statusCode: 201,
                success: true,
                message: 'Excepción registrada correctamente.',
                data: [
                    'professional_id' => $exception->getProfessionalId(),
                    'reason' => $exception->getBlockingReason(),
                    'start' => $exception->getTimeRange()->getStart()->format('Y-m-d H:i:s'),
                    'end' => $exception->getTimeRange()->getEnd()->format('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error del Servidor',
                detail: 'Ocurrió un error al registrar la excepción.',
                instance: $_SERVER['REQUEST_URI'] ?? '/'
            );
        }
    }
}
