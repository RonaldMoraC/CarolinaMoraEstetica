<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\RescheduleBooking\RescheduleAppointmentDTO;
use App\Application\Booking\RescheduleBooking\RescheduleAppointmentUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use InvalidArgumentException;

/**
 * RescheduleAppointmentController — Controlador HTTP
 *
 * PATCH /api/v1/booking/appointments/{id}/reschedule
 * PUT   /api/v1/booking/appointments/{id}/reschedule
 *
 * Permite a un cliente o personal administrativo reprogramar una cita existente.
 * Verifica ownership mediante el auth_user inyectado por AuthMiddleware.
 *
 * Cumple:
 *  - Skill 1  — strict_types, inyección por constructor
 *  - Skill 4  — Errores formateados RFC 7807 compatible
 *  - Skill 10 — Casteo explícito defensivo y sanitización de entrada JSON
 */
final class RescheduleAppointmentController
{
    public function __construct(
        private readonly RescheduleAppointmentUseCase $rescheduleUseCase
    ) {}

    /**
     * Maneja la petición de reprogramación.
     *
     * @param array<string, mixed> $params Contiene 'id' de la ruta y 'auth_user' del middleware
     */
    public function handle(array $params = []): void
    {
        $appointmentId = (int) ($params['id'] ?? 0);
        $authUser      = $params['auth_user'] ?? null;
        $instance      = $_SERVER['REQUEST_URI'] ?? ('/api/v1/booking/appointments/' . $appointmentId . '/reschedule');

        if ($appointmentId <= 0) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-id',
                title: 'ID de cita inválido',
                detail: 'El ID de la cita debe ser un entero positivo.',
                instance: $instance
            );
            return;
        }

        if ($authUser === null || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'No autorizado',
                detail: 'No se pudo identificar al usuario autenticado.',
                instance: $instance
            );
            return;
        }

        $userId   = (int) $authUser['user_id'];
        $userRole = (string) ($authUser['role'] ?? $authUser['user_role'] ?? 'CLIENT');

        // Leer payload JSON
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];

        // Soporta { scheduled_date, scheduled_time } o { new_scheduled_timestamp }
        $scheduledDate = trim((string) ($body['scheduled_date'] ?? ''));
        $scheduledTime = trim((string) ($body['scheduled_time'] ?? ''));

        if ($scheduledDate === '' && isset($body['new_scheduled_timestamp'])) {
            $parts = explode(' ', trim((string) $body['new_scheduled_timestamp']));
            $scheduledDate = $parts[0] ?? '';
            $scheduledTime = $parts[1] ?? '';
        }

        if ($scheduledDate === '' || $scheduledTime === '') {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/missing-parameters',
                title: 'Parámetros incompletos',
                detail: "Debe proporcionar 'scheduled_date' y 'scheduled_time' (o 'new_scheduled_timestamp') para reprogramar la cita.",
                instance: $instance
            );
            return;
        }

        $reason = isset($body['reason']) && trim((string)$body['reason']) !== ''
            ? trim((string)$body['reason'])
            : null;

        try {
            $dto = new RescheduleAppointmentDTO(
                appointmentId: $appointmentId,
                userId: $userId,
                userRole: $userRole,
                newScheduledDate: $scheduledDate,
                newScheduledTime: $scheduledTime,
                reason: $reason
            );

            $result = $this->rescheduleUseCase->execute($dto);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Cita reprogramada exitosamente.',
                data: $result
            );
        } catch (InvalidArgumentException|DomainException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 422,
                type: 'https://carolinamoraestetica.com/errors/domain-violation',
                title: 'Conflicto de Regla de Negocio',
                detail: $e->getMessage(),
                instance: $instance
            );
        } catch (NotFoundException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/appointment-not-found',
                title: 'Cita no encontrada',
                detail: $e->getMessage(),
                instance: $instance
            );
        } catch (ConflictException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 409,
                type: 'https://carolinamoraestetica.com/errors/slot-conflict',
                title: 'Conflicto de Horario o Disponibilidad',
                detail: $e->getMessage(),
                instance: $instance
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error Interno del Servidor',
                detail: 'Ocurrió un error inesperado al reprogramar la cita: ' . $e->getMessage(),
                instance: $instance
            );
        }
    }
}
