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
 *
 * Orquesta la actualización atómica de los datos personales (user) y ficha clínica/estética
 * (client_profile: medical_notes_allergies, consumption_preferences).
 *
 * Cumple:
 *   - Skill 1: Strict types y Clean Architecture.
 *   - Skill 2: Transacción PDO con consistencia total.
 *   - Skill 9: Auditoría inmutable de cambios (diff old vs new).
 *   - Skill 10: Sentencias preparadas sin concatenación directa.
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
        // 1. Obtener datos actuales para auditoría forense (Skill 9)
        $stmtOld = $this->pdo->prepare("
            SELECT u.email, u.auth_phone, u.first_name, u.last_name,
                   cp.medical_notes_allergies, cp.consumption_preferences
            FROM user u
            LEFT JOIN client_profile cp ON cp.client_profile_id = u.user_id
            WHERE u.user_id = :id
            LIMIT 1
        ");
        $stmtOld->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmtOld->execute();
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            throw new NotFoundException("Perfil de usuario no encontrado.");
        }

        // 2. Validar duplicidad de email (Skill 10)
        if ($oldData['email'] !== $dto->email) {
            $stmtCheck = $this->pdo->prepare("SELECT user_id FROM user WHERE email = :email AND user_id != :id LIMIT 1");
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

            // 4. Actualizar o insertar en client_profile (alergias y preferencias) (MEDIO-04)
            if ($dto->medicalNotesAllergies !== null || $dto->preferences !== null) {
                $sqlClient = "INSERT INTO client_profile (client_profile_id, medical_notes_allergies, consumption_preferences)
                              VALUES (:cid, :allergies, :preferences)
                              ON DUPLICATE KEY UPDATE
                                  medical_notes_allergies = VALUES(medical_notes_allergies),
                                  consumption_preferences = VALUES(consumption_preferences),
                                  updated_at = NOW()";

                $stmtClient = $this->pdo->prepare($sqlClient);
                $stmtClient->bindValue(':cid', $userId, PDO::PARAM_INT);
                $stmtClient->bindValue(':allergies', $dto->medicalNotesAllergies, $dto->medicalNotesAllergies !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmtClient->bindValue(':preferences', $dto->preferences, $dto->preferences !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmtClient->execute();
            }

            // 5. Registro de Auditoría Forense (Skill 9)
            $newValues = [
                'email'      => $dto->email,
                'phone'      => $dto->phone,
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName
            ];
            if ($dto->medicalNotesAllergies !== null) {
                $newValues['medical_notes_allergies'] = $dto->medicalNotesAllergies;
            }
            if ($dto->preferences !== null) {
                $newValues['consumption_preferences'] = $dto->preferences;
            }

            $this->auditLogRepository->insert(
                actorId: $userId,
                action: 'USER_PROFILE_UPDATED',
                entityType: 'user_profile',
                entityId: $userId,
                oldValues: $oldData,
                newValues: $newValues
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
