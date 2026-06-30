<?php declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\CreateBooking\CreateAppointmentDTO;
use App\Application\Booking\CreateBooking\CreateAppointmentUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

/**
 * CreateAppointmentController — Controlador HTTP
 *
 * POST /api/v1/booking/appointments
 *
 * Permite a un cliente crear una nueva cita seleccionando servicio,
 * profesional, sucursal, fecha y hora.
 *
 * Body JSON esperado:
 *   {
 *     "service_id": 1,
 *     "professional_profile_id": 2,
 *     "branch_id": 1,
 *     "scheduled_date": "2026-07-15",
 *     "scheduled_time": "10:00",
 *     "promotion_id": null,
 *     "notes": null
 *   }
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor
 *  - Skill 4  → Errores via ResponseHelper (RFC 7807 compatible)
 *  - Skill 10 → Casteo explícito defensivo en la periferia
 */
final class CreateAppointmentController
{
    private CreateAppointmentUseCase $createUseCase;

    public function __construct(CreateAppointmentUseCase $createUseCase)
    {
        $this->createUseCase = $createUseCase;
    }

    /**
     * POST /api/v1/booking/appointments
     *
     * @param array<string, mixed> $params Contiene 'auth_user' inyectado por AuthMiddleware
     */
    public function handle(array $params = []): void
    {
        $authUser = $params['auth_user'] ?? null;

        if ($authUser === null || !isset($authUser['user_id'])) {
            ResponseHelper::json(
                statusCode: 401,
                success: false,
                message: 'No se pudo identificar al usuario autenticado.',
                data: []
            );
        }

        // Leer JSON body
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody, true);

        if ($body === null || !is_array($body)) {
            ResponseHelper::json(
                statusCode: 400,
                success: false,
                message: 'El body de la petición debe ser un JSON válido.',
                data: []
            );
        }

        // Skill 10 — Casteo explícito defensivo
        $serviceId = (int) ($body['service_id'] ?? 0);
        $professionalProfileId = (int) ($body['professional_profile_id'] ?? 0);
        $branchId = (int) ($body['branch_id'] ?? 1);
        $scheduledDate = (string) ($body['scheduled_date'] ?? '');
        $scheduledTime = (string) ($body['scheduled_time'] ?? '');
        $promotionId = isset($body['promotion_id']) && $body['promotion_id'] !== null
            ? (int) $body['promotion_id']
            : null;
        $notes = isset($body['notes']) && $body['notes'] !== null
            ? (string) $body['notes']
            : null;

        $clientProfileId = (int) $authUser['user_id'];

        try {
            $dto = new CreateAppointmentDTO(
                serviceId: $serviceId,
                clientProfileId: $clientProfileId,
                professionalProfileId: $professionalProfileId,
                branchId: $branchId,
                scheduledDate: $scheduledDate,
                scheduledTime: $scheduledTime,
                promotionId: $promotionId,
                notes: $notes
            );

            $result = $this->createUseCase->execute($dto);

            ResponseHelper::json(
                statusCode: 201,
                success: true,
                message: 'Cita creada exitosamente.',
                data: $result
            );
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::json(
                statusCode: 400,
                success: false,
                message: $e->getMessage(),
                data: []
            );
        } catch (\DomainException $e) {
            ResponseHelper::json(
                statusCode: 422,
                success: false,
                message: $e->getMessage(),
                data: []
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error interno al crear la cita.',
                data: []
            );
        }
    }
}
