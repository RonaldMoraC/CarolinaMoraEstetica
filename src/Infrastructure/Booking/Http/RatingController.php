<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Application\Booking\Ratings\GetRatingsUseCase;
use App\Application\Booking\Ratings\RegisterRatingUseCase;
use App\Domain\Booking\Repositories\RatingRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

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
        } catch (\Exception $e) {
            ResponseHelper::json(400, false, $e->getMessage());
        }
    }

    public function registerRating(array $params): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $appointmentId = (int)($input['appointment_id'] ?? 0);
        $score = (int)($input['score'] ?? 0);
        $comments = $input['comments'] ?? null;

        $clientId = (int)($_SESSION['user_id'] ?? $input['client_profile_id'] ?? 0);

        try {
            $this->registerRatingUseCase->execute($appointmentId, $clientId, $score, $comments);
            ResponseHelper::json(201, true, 'Reseña registrada con éxito.');
        } catch (\Exception $e) {
            ResponseHelper::json(400, false, $e->getMessage());
        }
    }

    public function hideRating(array $params): void
    {
        $ratingId = (int)($params['id'] ?? 0);
        if ($ratingId <= 0) {
            ResponseHelper::json(400, false, 'ID de reseña no válido.');
            return;
        }

        try {
            $this->ratingRepo->hide($ratingId);
            ResponseHelper::json(200, true, 'Reseña ocultada con éxito.');
        } catch (\Exception $e) {
            ResponseHelper::json(500, false, 'Error al ocultar la reseña: ' . $e->getMessage());
        }
    }
}
