<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\ManageServices\ManageProfessionalServicesUseCase;
use App\Application\IAM\ManageServices\AssignServicesToProfessionalDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * ProfessionalServiceController
 *
 * Gestiona las asignaciones de servicios a profesionales.
 * Permite listar todos los servicios y los asignados, y actualizar las asignaciones.
 * Cumple Skill 1 (Clean Code), Skill 4 (RFC 7807), Skill 10 (Sanitización Perimetral).
 */
final class ProfessionalServiceController
{
    public function __construct(
        private ManageProfessionalServicesUseCase $useCase
    ) {}

    public function handle(array $params): void
    {
        $professionalId = (int) ($params['id'] ?? 0);
        if ($professionalId <= 0) {
            GlobalExceptionHandler::emitRfc7807Response(
                400,
                'https://carolinamoraestetica.com/errors/invalid-professional-id',
                'ID de Profesional Inválido',
                'El ID del profesional debe ser un entero positivo.',
                $_SERVER['REQUEST_URI']
            );
            return;
        }

        // Obtener el ID del usuario autenticado para auditoría (Skill 10)
        $authUser = $params['auth_user'] ?? null;
        $actorId = (int) ($authUser['user_id'] ?? 0);
        if ($actorId === 0) {
            GlobalExceptionHandler::emitRfc7807Response(
                401,
                'https://carolinamoraestetica.com/errors/unauthorized',
                'Sesión no válida',
                'No se pudo identificar al usuario autenticado para la auditoría.',
                $_SERVER['REQUEST_URI']
            );
            return;
        }

        try {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $this->getServices($professionalId),
                'POST' => $this->assignServices($professionalId, $actorId),
                default => GlobalExceptionHandler::emitRfc7807Response(
                    405,
                    'https://carolinamoraestetica.com/errors/method-not-allowed',
                    'Método no permitido',
                    'Método HTTP no soportado para esta ruta.',
                    $_SERVER['REQUEST_URI']
                )
            };
        } catch (NotFoundException | DomainException | InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                $e instanceof NotFoundException ? 404 : 422,
                'https://carolinamoraestetica.com/errors/' . strtolower((new \ReflectionClass($e))->getShortName()),
                'Error en la gestión de servicios del profesional',
                $e->getMessage(),
                $_SERVER['REQUEST_URI']
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                500,
                'https://carolinamoraestetica.com/errors/internal-server-error',
                'Error interno del servidor',
                'Ha ocurrido un error inesperado al procesar la solicitud.',
                $_SERVER['REQUEST_URI']
            );
        }
    }

    private function getServices(int $professionalId): void
    {
        $result = $this->useCase->getAssignedAndAllServices($professionalId);
        ResponseHelper::json(200, true, 'Servicios del profesional obtenidos exitosamente.', $result);
    }

    private function assignServices(int $professionalId, int $actorId): void
    {
        $rawInput = (string) file_get_contents('php://input');
        $body = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            throw new InvalidArgumentException('Cuerpo de petición JSON inválido.');
        }

        // Skill 10: Sanitización y Validación Perimetral
        $serviceIds = $body['service_ids'] ?? [];
        if (!is_array($serviceIds)) {
            throw new InvalidArgumentException('service_ids debe ser un array.');
        }
        $serviceIds = array_map('intval', $serviceIds); // Asegurar que todos son enteros
        $serviceIds = array_filter($serviceIds, fn($id) => $id > 0); // Filtrar IDs inválidos

        $dto = new AssignServicesToProfessionalDTO($serviceIds);
        $this->useCase->assignServices($professionalId, $dto, $actorId);

        ResponseHelper::json(200, true, 'Servicios asignados correctamente.');
    }
}