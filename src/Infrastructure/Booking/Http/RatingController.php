<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\Ratings\GetRatingsUseCase;
use App\Application\Booking\Ratings\RegisterRatingUseCase;
use App\Domain\Booking\Repositories\RatingRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * RatingController
 *
 * Controlador HTTP para gestión de reseñas y calificaciones.
 * Cumple con Skill 1 (Clean Architecture), Skill 4 (RFC 7807) y Skill 10 (Zero-Trust Security).
 */
final class RatingController
{
    public function __construct(
        private GetRatingsUseCase $getRatingsUseCase,
        private RegisterRatingUseCase $registerRatingUseCase,
        private RatingRepositoryInterface $ratingRepo
    ) {}

    public function getRatings(array $params): void
    {
        try {
            $data = $this->getRatingsUseCase->execute();
            ResponseHelper::json(200, true, 'Reseñas obtenidas con éxito.', $data);
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error al consultar reseñas',
                detail: $e->getMessage(),
                instance: '/api/v1/crm/ratings'
            );
        }
    }

    public function registerRating(array $params): void
    {
        $authUser = $params['auth_user'] ?? null;
        if (!$authUser || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 401,
                type: 'https://carolinamoraestetica.com/errors/unauthorized',
                title: 'No autorizado',
                detail: 'Debes iniciar sesión para registrar una calificación.',
                instance: '/api/v1/crm/ratings'
            );
            return;
        }

        $clientId = (int) $authUser['user_id'];

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];

        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $score = (int) ($input['score'] ?? 0);
        $comments = isset($input['comments']) && trim((string) $input['comments']) !== ''
            ? trim((string) $input['comments'])
            : null;

        if ($appointmentId <= 0 || $score < 1 || $score > 5) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-parameters',
                title: 'Parámetros inválidos',
                detail: 'El ID de cita es requerido y el puntaje debe ser un entero entre 1 y 5.',
                instance: '/api/v1/crm/ratings'
            );
            return;
        }

        try {
            $this->registerRatingUseCase->execute($appointmentId, $clientId, $score, $comments);
            ResponseHelper::json(201, true, 'Reseña registrada con éxito.');
        } catch (DomainException $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 422,
                type: 'https://carolinamoraestetica.com/errors/rating-domain-error',
                title: 'Operación no permitida',
                detail: $e->getMessage(),
                instance: '/api/v1/crm/ratings'
            );
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error al registrar calificación',
                detail: 'Ocurrió un error inesperado al procesar la reseña.',
                instance: '/api/v1/crm/ratings'
            );
        }
    }

    public function hideRating(array $params): void
    {
        $ratingId = (int) ($params['id'] ?? 0);
        if ($ratingId <= 0) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type: 'https://carolinamoraestetica.com/errors/invalid-id',
                title: 'ID inválido',
                detail: 'El ID de reseña especificado no es válido.',
                instance: '/api/v1/crm/ratings/' . $ratingId . '/hide'
            );
            return;
        }

        try {
            $this->ratingRepo->hide($ratingId);
            ResponseHelper::json(200, true, 'Reseña ocultada con éxito.');
        } catch (\Throwable $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/internal-server-error',
                title: 'Error al ocultar reseña',
                detail: $e->getMessage(),
                instance: '/api/v1/crm/ratings/' . $ratingId . '/hide'
            );
        }
    }
}
