<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Persistence;

use App\Domain\IAM\Entities\User;
use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Domain\IAM\ValueObjects\Email;
use App\Domain\IAM\ValueObjects\HashedPassword;
use App\Domain\IAM\Entities\Role;
use PDO;

/**
 * PdoUserRepository
 *
 * Implementación física del repositorio de usuarios utilizando PDO y SQL nativo.
 * (Clean Architecture - Capa de Infraestructura - Skills 1, 10 y 12).
 */
final class PdoUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca un usuario por su email para el proceso de autenticación.
     */
    public function findByEmail(Email $email): ?User
    {
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.auth_phone, u.first_name, u.last_name, u.account_status, r.role_code
                FROM `user` u
                LEFT JOIN `user_role` ur ON u.user_id = ur.user_id
                LEFT JOIN `role` r ON ur.role_id = r.role_id
                WHERE u.email = :email
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':email', $email->getValue(), PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->mapToEntity($row);
    }

    /**
     * Busca un usuario por su número de teléfono.
     */
    public function findByPhone(string $phone): ?User
    {
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.auth_phone, u.first_name, u.last_name, u.account_status, r.role_code 
                FROM `user` u
                LEFT JOIN `user_role` ur ON u.user_id = ur.user_id
                LEFT JOIN `role` r ON ur.role_id = r.role_id
                WHERE u.auth_phone = :phone 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->mapToEntity($row);
    }

    /**
     * Busca un usuario por su ID.
     */
    public function findById(int $userId): ?User
    {
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.auth_phone, u.first_name, u.last_name, u.account_status, r.role_code 
                FROM `user` u
                LEFT JOIN `user_role` ur ON u.user_id = ur.user_id
                LEFT JOIN `role` r ON ur.role_id = r.role_id
                WHERE u.user_id = :user_id 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->mapToEntity($row);
    }

    /**
     * Guarda un nuevo usuario en la base de datos.
     *
     * @param array<string, mixed> $data Los datos del usuario a guardar.
     * @return int El ID del usuario recién creado.
     */
    public function save(array $data): int
    {
        $sql = "INSERT INTO `user` (email, password_hash, auth_phone, first_name, last_name, account_status)
                VALUES (:email, :password_hash, :auth_phone, :first_name, :last_name, :account_status)";

        $stmt = $this->pdo->prepare($sql);

        // Skill 10: Mapeo explícito mediante bindValue
        $stmt->bindValue(':email',          (string) $data['email'],         PDO::PARAM_STR);
        $stmt->bindValue(':password_hash',  (string) $data['password_hash'], PDO::PARAM_STR);
        $stmt->bindValue(':auth_phone',     (string) $data['auth_phone'],    PDO::PARAM_STR);
        $stmt->bindValue(':first_name',     (string) $data['first_name'],    PDO::PARAM_STR);
        $stmt->bindValue(':last_name',      (string) $data['last_name'],     PDO::PARAM_STR);
        $stmt->bindValue(':account_status', (string) ($data['account_status'] ?? 'PENDING_VERIFICATION'), PDO::PARAM_STR);
        
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Skill 12: Hidratación manual y eficiente.
     */
    private function mapToEntity(array $row): User
    {
        return new User(
            userId:         (int) $row['user_id'],
            email:          new Email((string) ($row['email'] ?? '')),
            passwordHash:   new HashedPassword((string) ($row['password_hash'] ?? '')),
            authPhone:      (string) ($row['auth_phone'] ?? ''),
            firstName:      (string) ($row['first_name'] ?? ''),
            lastName:       (string) ($row['last_name'] ?? ''),
            accountStatus:  (string) ($row['account_status'] ?? 'INACTIVE'),
            role:           new Role((string) ($row['role_code'] ?? 'CLIENT')) // Default a CLIENT si por alguna razón no se encuentra el rol (aunque INNER JOIN lo evitaría)
        );
    }
}
