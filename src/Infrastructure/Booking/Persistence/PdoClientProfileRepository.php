<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Persistence;

use App\Domain\Booking\Repositories\ClientProfileRepositoryInterface;
use PDO;

final class PdoClientProfileRepository implements ClientProfileRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /**
     * Guarda un nuevo perfil de cliente en la base de datos.
     *
     * @param array<string, mixed> $data Los datos del perfil a guardar.
     * @return int El ID del perfil de cliente recién creado.
     */
    public function save(array $data): int
    {
        $sql = "INSERT INTO client_profile (client_profile_id, birth_date)
                VALUES (:client_profile_id, :birth_date)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'client_profile_id' => $data['user_id'],
            'birth_date'        => $data['birth_date'] ?? '2000-01-01'
        ]);
        return (int) $data['user_id'];
    }
}
