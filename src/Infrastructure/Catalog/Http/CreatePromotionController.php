<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\ManagePromotions\CreatePromotionUseCase;
use App\Application\Catalog\ManagePromotions\CreatePromotionDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;
use InvalidArgumentException;

/**
 * CreatePromotionController
 * Maneja las solicitudes HTTP para crear una nueva promoción.
 * Cumple Skill 4 (RFC 7807) y Skill 10 (Sanitización).
 */
final class CreatePromotionController
{
    public function __construct(
        private readonly CreatePromotionUseCase $useCase
    ) {}

    public function handle(array $params): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/api/v1/catalog/promotions';

        try {
            // 1. Leer input JSON (Skill 10)
            $rawInput = (string) file_get_contents('php://input');
            $body = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
                throw new InvalidArgumentException('El cuerpo de la petición debe ser un objeto JSON válido.');
            }

            // 2. Sanitización y Validación Perimetral (Skill 10)
            $name = trim((string)($body['name'] ?? ''));
            $discount = (float)($body['discount_percentage'] ?? 0);
            $start = trim((string)($body['start_date'] ?? ''));
            $end = trim((string)($body['end_date'] ?? ''));
            $services = (array)($body['associated_services'] ?? []);

            if ($name === '' || $start === '' || $end === '') {
                throw new InvalidArgumentException('Nombre, fecha de inicio y fin son obligatorios.');
            }

            if ($discount <= 0 || $discount > 100) {
                throw new InvalidArgumentException('El descuento debe ser un valor entre 1 y 100.');
            }

            // 3. Ejecución (Skill 1)
            $dto = new CreatePromotionDTO(
                name: $name,
                discountPercentage: $discount,
                startDate: $start,
                endDate: $end,
                associatedServices: array_map('intval', $services)
            );

            $this->useCase->execute($dto);

            ResponseHelper::json(201, true, 'Promoción creada exitosamente.');

        } catch (InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                400,
                'https://carolinamoraestetica.com/errors/invalid-argument',
                'Datos inválidos',
                $e->getMessage(),
                $uri
            );
        } catch (DomainException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                422,
                'https://carolinamoraestetica.com/errors/business-rule-violation',
                'Regla de negocio violada',
                $e->getMessage(),
                $uri
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                500,
                'https://carolinamoraestetica.com/errors/internal-error',
                'Error interno del servidor',
                $e->getMessage(),
                $uri
            );
        }
    }
}
