<?php
declare(strict_types=1);

namespace App\Infrastructure\Staffing\Http;

use App\Application\Staffing\ListProfessionals\GetAllProfessionalsUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * GetProfessionalsController
 *
 * Retorna todos los profesionales activos del sistema.
 * Usado por el calendario maestro y la vista de horarios para llenar selects de filtro.
 */
final class GetProfessionalsController
{
    public function __construct(
        private readonly GetAllProfessionalsUseCase $useCase
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $professionals = $this->useCase->execute();

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Profesionales obtenidos exitosamente.',
                data: $professionals
            );

        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error del Servidor',
                detail: 'Ocurrió un error al consultar los profesionales.',
                instance: $_SERVER['REQUEST_URI']
            );
        }
    }
}
