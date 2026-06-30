<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use InvalidArgumentException;

/**
 * UpdateServiceController
 *
 * Maneja las solicitudes HTTP para actualizar un servicio existente en el catálogo.
 * Cumple Skill 1 (Clean Code), Skill 4 (RFC 7807) y Skill 10 (Sanitización Perimetral).
 */
final class UpdateServiceController
{
    /**
     * @param ManageServiceInventoryUseCase $manageServiceInventoryUseCase Caso de uso inyectado (Skill 1)
     */
    public function __construct(
        private readonly ManageServiceInventoryUseCase $manageServiceInventoryUseCase
    ) {}

    /**
     * Procesa la actualización de un servicio a partir de un cuerpo JSON.
     *
     * @param array $params Parámetros de ruta inyectados por el Router (contiene {id}).
     */
    public function handle(array $params): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/api/v1/catalog/services';
        $serviceId = (int) ($params['id'] ?? 0);

        try {
            // 1. Validación de identidad del recurso (Skill 10)
            if ($serviceId <= 0) {
                throw new InvalidArgumentException('Se requiere un ID de servicio válido para la actualización.');
            }

            // 2. Lectura y decodificación del cuerpo de la petición
            $rawBody = file_get_contents('php://input');
            $input = json_decode((string)$rawBody, true);

            if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('El cuerpo de la solicitud no es un JSON válido.');
            }

            // 3. Sanitización y Casteo Perimetral (Skill 10)
            // Construimos el array dinámicamente según lo enviado para soportar PATCH/PUT parcial.
            $data = [];
            if (isset($input['name']))             $data['name']             = trim((string)$input['name']);
            if (isset($input['category_id']))      $data['category_id']      = (int)$input['category_id'];
            if (isset($input['base_price']))       $data['base_price']       = (float)$input['base_price'];
            if (isset($input['duration_minutes'])) $data['duration_minutes'] = (int)$input['duration_minutes'];
            if (isset($input['description']))      $data['description']      = trim((string)$input['description']);
            if (isset($input['is_active']))        $data['is_active']        = (int)$input['is_active'];

            if (empty($data)) {
                throw new InvalidArgumentException('No se proporcionaron datos para actualizar el servicio.');
            }

            // 4. Ejecución del Caso de Uso (Skill 1)
            $this->manageServiceInventoryUseCase->updateService($serviceId, $data);

            // 5. Respuesta Exitosa (Skill 4)
            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Servicio actualizado correctamente.'
            );

        } catch (InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-argument',
                title: 'Petición Inválida',
                detail: $e->getMessage(),
                instance: $uri
            );
        } catch (NotFoundException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/not-found',
                title: 'Recurso no encontrado',
                detail: $e->getMessage(),
                instance: $uri
            );
        } catch (ConflictException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 409,
                type: 'https://carolinamoraestetica.com/errors/conflict',
                title: 'Conflicto de Concurrencia',
                detail: $e->getMessage(),
                instance: $uri
            );
        } catch (DomainException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 422,
                type: 'https://carolinamoraestetica.com/errors/domain-violation',
                title: 'Error de Regla de Negocio',
                detail: $e->getMessage(),
                instance: $uri
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error Interno del Servidor',
                detail: 'Ocurrió un error inesperado al procesar la actualización del inventario.',
                instance: $uri
            );
        }
    }
}
