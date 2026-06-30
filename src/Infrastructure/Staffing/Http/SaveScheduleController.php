<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Application\Staffing\SaveSchedule\ConfigureScheduleDTO;
use App\Application\Staffing\SaveSchedule\ConfigureScheduleUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

/**
 * SaveScheduleController
 *
 * Guarda la configuración de horarios semanales de un profesional.
 */
final class SaveScheduleController
{
    public function __construct(
        private readonly ConfigureScheduleUseCase $useCase
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true);

            if (!$body || empty($body['schedules']) || !is_array($body['schedules'])) {
                ResponseHelper::json(
                    statusCode: 400,
                    success: false,
                    message: 'Datos inválidos: se requiere un array de horarios.'
                );
            }

            $professionalProfileId = (int) ($body['professional_profile_id'] ?? 0);
            $branchId              = (int) ($body['branch_id'] ?? 0);

            if ($professionalProfileId <= 0 || $branchId <= 0) {
                ResponseHelper::json(
                    statusCode: 400,
                    success: false,
                    message: 'ID de profesional y sucursal son obligatorios.'
                );
            }

            // Obtener ID del actor desde los claims JWT (inyectado por AuthMiddleware)
            $actorId = (int) ($params['auth_user']['sub'] ?? $params['auth_user']['user_id'] ?? 0);

            $dto = new ConfigureScheduleDTO(
                professionalId: $professionalProfileId,
                branchId:       $branchId,
                schedules:      $body['schedules']
            );

            $result = $this->useCase->execute($dto, $actorId);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Horarios semanales guardados y sincronizados correctamente.',
                data: $result
            );

        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error al guardar los horarios: ' . $e->getMessage()
            );
        }
    }
}