<?php declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\CancelBooking\CancelAppointmentDTO;
use App\Application\Booking\CancelBooking\CancelAppointmentUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use InvalidArgumentException;

/**
 * CancelAppointmentController — Controlador HTTP
 *
 * DELETE /api/v1/booking/appointments/{id}
 *
 * Permite a un cliente cancelar su propia cita.
 * Verifica ownership mediante el auth_user inyectado por AuthMiddleware.
 *
 * Cumple:
 *  - Skill 1  — strict_types, inyección por constructor
 *  - Skill 4  — Errores formateados RFC 7807 compatible
 *  - Skill 10 — Casteo explícito defensivo y sanitización de entrada JSON
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
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-id',
                title: 'ID de cita inválido',
                detail: 'El ID de la cita debe ser un entero positivo.',
                instance: '/api/v1/booking/appointments'
            );
            return;
        }

        if ($authUser === null || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'No autorizado',
                detail: 'No se pudo identificar al usuario autenticado.',
                instance: '/api/v1/booking/appointments/' . $appointmentId
            );
            return;
        }

        $userId = (int) $authUser['user_id'];
        $userRole = (string) ($authUser['role'] ?? $authUser['user_role'] ?? 'CLIENT');

        // Leer motivo desde body JSON (estándar REST) o query parameter de respaldo
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];
        $reason = trim((string) ($body['reason'] ?? $_GET['reason'] ?? ''));

        try {
            $dto = new CancelAppointmentDTO($appointmentId, $userId, $userRole, $reason !== '' ? $reason : null);
            $result = $this->cancelUseCase->execute($dto);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Cita cancelada exitosamente.',
                data: $result
            );
        } catch (InvalidArgumentException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-argument',
                title: 'Parámetro inválido',
                detail: $e->getMessage(),
                instance: '/api/v1/booking/appointments/' . $appointmentId
            );
        } catch (NotFoundException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/appointment-not-found',
                title: 'Cita no encontrada',
                detail: $e->getMessage(),
                instance: '/api/v1/booking/appointments/' . $appointmentId
            );
        } catch (DomainException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 422,
                type: 'https://carolinamoraestetica.com/errors/business-rule-conflict',
                title: 'Operación no permitida',
                detail: $e->getMessage(),
                instance: '/api/v1/booking/appointments/' . $appointmentId
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error interno',
                detail: 'Ocurrió un error inesperado al procesar la cancelación.',
                instance: '/api/v1/booking/appointments/' . $appointmentId
            );
        }
    }
}
