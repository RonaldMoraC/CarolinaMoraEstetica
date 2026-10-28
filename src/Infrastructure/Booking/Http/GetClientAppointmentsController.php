<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * GetClientAppointmentsController
 *
 * Controlador HTTP para el endpoint GET /api/v1/app/appointments.
 * Retorna ÚNICA Y EXCLUSIVAMENTE las citas del cliente autenticado.
 *
 * Cumple:
 *  - Skill 1  — strict_types, inyección de interfaces por constructor (Clean Architecture).
 *  - Skill 4  — Formato de error RFC 7807 compatible.
 *  - Skill 10 — Aislamiento estricto de identidad (Zero-Trust) mediante auth_user inyectado por AuthMiddleware.
 */
final class GetClientAppointmentsController
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository
    ) {}

    /**
     * Maneja la petición GET /api/v1/app/appointments
     *
     * @param array<string, mixed> $params Contiene 'auth_user' inyectado por AuthMiddleware.
     */
    public function handle(array $params = []): void
    {
        $authUser = $params['auth_user'] ?? null;

        if ($authUser === null || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'No autorizado',
                detail: 'No se pudo identificar al cliente autenticado para consultar sus citas.',
                instance: '/api/v1/app/appointments'
            );
            return;
        }

        $clientId = (int) $authUser['user_id'];

        try {
            $appointments = $this->appointmentRepository->findByClientId($clientId);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Citas del cliente obtenidas exitosamente.',
                data: $appointments
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error al consultar citas',
                detail: 'Ocurrió un error al consultar las citas del cliente.',
                instance: '/api/v1/app/appointments'
            );
        }
    }
}
