<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\GetProfessionalsByService\GetProfessionalsByServiceUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use InvalidArgumentException;

/**
 * GetProfessionalsByServiceController
 * 
 * Retorna los profesionales que pueden realizar un servicio dado.
 * Cumple Skill 1 (Clean Code), Skill 4 (RFC 7807), Skill 10 (Sanitización).
 */
final class GetProfessionalsByServiceController
{
    public function __construct(
        private readonly GetProfessionalsByServiceUseCase $useCase
    ) {}

    public function handle(array $params): void
    {
        // Skill 10: Sanitización Perimetral (acepta ID en ruta o query string)
        $serviceId = (int) ($params['id'] ?? $_GET['service_id'] ?? 0);

        try {
            if ($serviceId <= 0) {
                throw new InvalidArgumentException('Se requiere un ID de servicio válido.');
            }

            $professionals = $this->useCase->execute($serviceId);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Profesionales obtenidos exitosamente.',
                data: $professionals
            );

        } catch (InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-argument',
                title: 'Parámetro Inválido',
                detail: $e->getMessage(),
                instance: $_SERVER['REQUEST_URI']
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error del Servidor',
                detail: 'Ocurrió un error al consultar los profesionales habilitados.',
                instance: $_SERVER['REQUEST_URI']
            );
        }
    }
}