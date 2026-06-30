<?php declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\CancelBooking\CancelAppointmentDTO;
use App\Application\Booking\CancelBooking\CancelAppointmentUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Security\AuthMiddleware;

/**
 * CancelAppointmentController — Controlador HTTP
 *
 * DELETE /api/v1/booking/appointments/{id}
 *
 * Permite a un cliente cancelar su propia cita.
 * Verifica ownership mediante el auth_user inyectado por AuthMiddleware.
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor
 *  - Skill 4  → Errores via ResponseHelper (RFC 7807 compatible)
 *  - Skill 10 → Casteo explícito defensivo en la periferia
 */
final class CancelAppointmentController
{
    private CancelAppointmentUseCase $cancelUseCase;

    public function __construct(CancelAppointmentUseCase $cancelUseCase)
    {
        $this->cancelUseCase = $cancelUseCase;
    }

    /**
     * DELETE /api/v1/booking/appointments/{id}
     *
     * @param array<string, mixed> $params Contiene 'id' de la ruta y 'auth_user' del middleware
     */
    public function handle(array $params = []): void
    {
        $appointmentId = (int) ($params['id'] ?? 0);
        $authUser = $params['auth_user'] ?? null;

        if ($appointmentId <= 0) {
            ResponseHelper::json(
                statusCode: 400,
                success: false,
                message: 'El ID de la cita debe ser un entero positivo.',
                data: []
            );
        }

        if ($authUser === null || !isset($authUser['user_id'])) {
            ResponseHelper::json(
                statusCode: 401,
                success: false,
                message: 'No se pudo identificar al usuario autenticado.',
                data: []
            );
        }

        $userId = (int) $authUser['user_id'];
        $reason = (string) ($_POST['reason'] ?? $_GET['reason'] ?? 'Cancelación solicitada por el cliente');

        try {
            $dto = new CancelAppointmentDTO($appointmentId, $userId, $reason);
            $result = $this->cancelUseCase->execute($dto);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Cita cancelada exitosamente.',
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
                message: 'Error interno al cancelar la cita.',
                data: []
            );
        }
    }
}
