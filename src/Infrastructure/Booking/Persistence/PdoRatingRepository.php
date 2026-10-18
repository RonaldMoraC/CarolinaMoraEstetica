<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Persistence;

use App\Domain\Booking\Entities\ServiceRating;
use App\Domain\Booking\Repositories\RatingRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoRatingRepository implements RatingRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function save(ServiceRating $rating): void
    {
        $sql = "INSERT INTO service_rating (
                    appointment_id, client_profile_id, professional_profile_id, score, comments, is_visible
                ) VALUES (
                    :appointmentId, :clientProfileId, :professionalProfileId, :score, :comments, 1
                )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointmentId', $rating->getAppointmentId(), PDO::PARAM_INT);
        $stmt->bindValue(':clientProfileId', $rating->getClientProfileId(), PDO::PARAM_INT);
        $stmt->bindValue(':professionalProfileId', $rating->getProfessionalProfileId(), PDO::PARAM_INT);
        $stmt->bindValue(':score', $rating->getScore(), PDO::PARAM_INT);
        $stmt->bindValue(':comments', $rating->getComments(), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function findAll(): array
    {
        $sql = "SELECT
                    sr.rating_id,
                    sr.score,
                    sr.comments,
                    sr.is_visible,
                    sr.created_at,
                    CONCAT(u_client.first_name, ' ', u_client.last_name) AS client_name,
                    CONCAT(u_prof.first_name, ' ', u_prof.last_name) AS professional_name,
                    s.name AS service_name,
                    sr.professional_profile_id
                FROM service_rating sr
                JOIN appointment a ON sr.appointment_id = a.appointment_id
                JOIN user u_client ON sr.client_profile_id = u_client.user_id
                JOIN user u_prof ON sr.professional_profile_id = u_prof.user_id
                LEFT JOIN service s ON a.service_id = s.service_id
                ORDER BY sr.created_at DESC";
        $stmt = $this->pdo->query($sql);

        $results = [];
        $professionalsMap = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $profId = (int)$row['professional_profile_id'];
            $profName = $row['professional_name'];
            if (!isset($professionalsMap[$profId])) {
                $professionalsMap[$profId] = [
                    'id' => $profId,
                    'nombre' => $profName
                ];
            }

            $results[] = [
                'rating_id' => (int)$row['rating_id'],
                'client_name' => $row['client_name'],
                'professional_name' => $profName,
                'service_name' => $row['service_name'] ?? 'Servicio General',
                'score' => (int)$row['score'],
                'comments' => $row['comments'],
                'is_visible' => (int)$row['is_visible'],
                'created_at' => $row['created_at']
            ];
        }

        // Calcular resumen general solo con las visibles
        $visibleRatings = array_filter($results, fn($r) => $r['is_visible'] === 1);
        $total = count($visibleRatings);
        $sum = 0;
        $count5 = 0;

        // Para reseñas de este mes (año-mes actual)
        $currentYearMonth = date('Y-m');
        $resenasMes = 0;

        foreach ($visibleRatings as $r) {
            $sum += $r['score'];
            if ($r['score'] === 5) {
                $count5++;
            }
            if (str_starts_with($r['created_at'], $currentYearMonth)) {
                $resenasMes++;
            }
        }

        $average = $total > 0 ? round($sum / $total, 1) : 0.0;
        $percent5 = $total > 0 ? round(($count5 / $total) * 100) : 0;

        return [
            'ratings' => $results,
            'summary' => [
                'rating_promedio' => $average,
                'total_resenas' => $total,
                'resenas_mes' => $resenasMes,
                'porcentaje_5' => $percent5
            ],
            'professionals' => array_values($professionalsMap)
        ];
    }

    public function findByAppointmentAndClient(int $appointmentId, int $clientProfileId): ?ServiceRating
    {
        $sql = "SELECT * FROM service_rating
                WHERE appointment_id = :appointmentId AND client_profile_id = :clientProfileId
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointmentId', $appointmentId, PDO::PARAM_INT);
        $stmt->bindValue(':clientProfileId', $clientProfileId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new ServiceRating(
            (int)$row['rating_id'],
            (int)$row['appointment_id'],
            (int)$row['client_profile_id'],
            (int)$row['professional_profile_id'],
            (int)$row['score'],
            $row['comments'],
            new DateTimeImmutable($row['created_at'])
        );
    }

    public function hide(int $ratingId): void
    {
        $sql = "UPDATE service_rating SET is_visible = 0 WHERE rating_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $ratingId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
