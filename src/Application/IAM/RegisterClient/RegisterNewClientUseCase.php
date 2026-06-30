<?php
declare(strict_types=1);

namespace App\Application\IAM\RegisterClient;

use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Domain\Booking\Repositories\ClientProfileRepositoryInterface;
use App\Domain\IAM\ValueObjects\Email;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Domain\IAM\ValueObjects\HashedPassword;

/**
 * RegisterNewClientUseCase
 * 
 * Orquesta el registro atómico de una nueva identidad (user) y su perfil (client_profile).
 */
final class RegisterNewClientUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClientProfileRepositoryInterface $profileRepository,
        private SystemAuditLogRepository $auditRepository,
        private \PDO $pdo // Skill 1 & 2: Gestión de transacciones
    ) {}

    public function execute(RegisterClientDTO $dto): void
    {
        // Skill 10: Verificaciones previas (Zero-Trust)
        if ($this->userRepository->findByEmail(new Email($dto->email))) {
            throw new \DomainException("El correo electrónico ya está registrado.", 409);
        }

        if ($this->userRepository->findByPhone($dto->phone)) {
            throw new \DomainException("El número de teléfono ya está registrado.", 409);
        }

        // Iniciar Transacción SQL
        // Skill 2: Garantizar nivel de aislamiento para concurrencia
        $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->pdo->beginTransaction();

        try {
            // 1. Insertar en tabla 'user' (email, password_hash, auth_phone)
            // Usamos Value Objects para el hashing automático (Skill 10)
            $hashedPassword = HashedPassword::fromRaw($dto->password);
            
            $userId = $this->userRepository->save([
                'email'         => $dto->email,
                'password_hash' => $hashedPassword->getValue(),
                'auth_phone'    => $dto->phone,
                'first_name'    => $dto->firstName,
                'last_name'     => $dto->lastName,
                'account_status'=> 'ACTIVE' // O PENDING_VERIFICATION según lógica
            ]);

            // 2. Insertar en tabla 'client_profile' usando el ID generado
            $this->profileRepository->save([
                'user_id'    => $userId
            ]);

            // Skill 9: Auditoría inmutable de la mutación
            $this->auditRepository->insert(
                actorId:    (string) $userId,
                action:     'CLIENT_SELF_REGISTER',
                entityType: 'user',
                entityId:   $userId,
                oldValues:  [],
                newValues:  [
                    'email' => $dto->email,
                    'phone' => $dto->phone,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName
                ]
            );

            // 3. Confirmar cambios
            $this->pdo->commit();

        } catch (\Exception $e) {
            // Skill 2: En caso de cualquier error, revertir para evitar datos huérfanos
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
