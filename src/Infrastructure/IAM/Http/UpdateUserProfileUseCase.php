<?php
declare(strict_types=1);

namespace App\Application\IAM\UpdateProfile;

use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;
use PDO;

/**
 * UpdateUserProfileUseCase
 * Orquesta la actualización de los datos del perfil del usuario (IAM + Profile).
 * Aplica Skill 1 (Clean Code), Skill 9 (Auditoría) y Skill 10 (Sentencias Preparadas).
 */
final class UpdateUserProfileUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SystemAuditLogRepository $auditLogRepository,
        private PDO $pdo
    ) {}

    /**
     * @throws NotFoundException
     * @throws DomainException
     */
    public function execute(int $userId, UpdateProfileDTO $dto): void
    {
        // 1. Obtener datos actuales para auditoría (Skill 9)
        $stmtOld = $this->pdo->prepare("
            SELECT email, auth_phone, first_name, last_name 
            FROM user 
            WHERE user_id = :id
        ");
        $stmtOld->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmtOld->execute();
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            throw new NotFoundException("Perfil de usuario no encontrado.");
        }

        // 2. Validar duplicidad de email (Skill 10)
        if ($oldData['email'] !== $dto->email) {
            $stmtCheck = $this->pdo->prepare("SELECT user_id FROM user WHERE email = :email AND user_id != :id");
            $stmtCheck->bindValue(':email', $dto->email, PDO::PARAM_STR);
            $stmtCheck->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                throw new DomainException("El correo electrónico '{$dto->email}' ya está registrado por otro usuario.");
            }
        }

        try {
            // Skill 2: Uso de transacciones para asegurar consistencia entre user y client_profile
            $this->pdo->beginTransaction();

            // 3. Actualizar tabla user (email, auth_phone, first_name, last_name y opcionalmente password)
            $sqlUser = "UPDATE user SET email = :email, auth_phone = :phone, first_name = :first, last_name = :last, updated_at = NOW()";
            $paramsUser = [
                ':email' => $dto->email,
                ':phone' => $dto->phone,
                ':first' => $dto->firstName,
                ':last'  => $dto->lastName,
                ':id'    => $userId
            ];

            if ($dto->password !== null && $dto->password !== '') {
                $sqlUser .= ", password_hash = :pass";
                $paramsUser[':pass'] = password_hash($dto->password, PASSWORD_BCRYPT);
            }
            $sqlUser .= " WHERE user_id = :id";
            
            $stmtUser = $this->pdo->prepare($sqlUser);
            foreach ($paramsUser as $key => $val) {
                $stmtUser->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtUser->execute();

            // 5. Registro de Auditoría Forense (Skill 9)
            $this->auditLogRepository->insert(
                actorId: $userId,
                action: 'USER_PROFILE_UPDATED',
                entityType: 'user_profile',
                entityId: $userId,
                oldValues: $oldData,
                newValues: [
                    'email' => $dto->email,
                    'phone' => $dto->phone,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName
                ]
            );

            $this->pdo->commit();

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}