<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * CreateServiceController
 *
 * Maneja las solicitudes HTTP para crear un nuevo servicio en el catálogo.
 * Cumple Skill 1 (Clean Code), Skill 4 (RFC 7807) y Skill 10 (Sanitización Perimetral).
 */
final class CreateServiceController
{
    /**
     * @param ManageServiceInventoryUseCase $manageServiceInventoryUseCase Caso de uso inyectado (Skill 1)
     */
    public function __construct(
        private readonly ManageServiceInventoryUseCase $manageServiceInventoryUseCase
    ) {}

    /**
     * Procesa la creación de un servicio a partir de un cuerpo JSON.
     *
     * @param array $params Parámetros de ruta (no utilizados en este endpoint).
     */
    public function handle(array $params): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/api/v1/catalog/services';

        try {
            // 1. Lectura del cuerpo de la petición
            $rawBody = file_get_contents('php://input');
            $input = json_decode((string)$rawBody, true);

            if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('El cuerpo de la solicitud no es un JSON válido.');
            }

            // 2. Sanitización y Casteo Perimetral (Skill 10)
            // Aseguramos que los datos tengan el tipo esperado antes de pasarlos a la capa Application
            $data = [
                'name'             => trim((string)($input['name'] ?? '')),
                'category_id'      => (int)($input['category_id'] ?? 0),
                'base_price'       => (float)($input['base_price'] ?? 0.0),
                'duration_minutes' => (int)($input['duration_minutes'] ?? 0),
                'description'      => trim((string)($input['description'] ?? '')),
                'is_active'        => (int)($input['is_active'] ?? 1)
            ];

            // Validación básica perimetral
            if ($data['name'] === '' || $data['category_id'] <= 0 || $data['base_price'] <= 0 || $data['duration_minutes'] <= 0) {
                throw new \InvalidArgumentException('Faltan campos obligatorios (nombre, categoría, precio, duración) o contienen valores inválidos.');
            }

            // 3. Ejecución del Caso de Uso
            $this->manageServiceInventoryUseCase->createService($data);

            // 4. Respuesta Exitosa (Skill 4)
            ResponseHelper::json(
                statusCode: 201,
                success: true,
                message: 'Servicio creado exitosamente.'
            );

        } catch (\InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-argument',
                title: 'Petición Inválida',
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
                detail: 'Ocurrió un error inesperado al procesar el inventario.',
                instance: $uri
            );
        }
    }
}
