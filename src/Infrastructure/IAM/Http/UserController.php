<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Application\IAM\ManageRoles\AssignRoleUseCase;
use App\Infrastructure\Shared\Audit\SystemAuditLogRepository;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use PDO;

/**
 * UserController
 *
 * Controlador para la gestión administrativa de usuarios (Personal y Clientes).
 * Procesa el listado, creación y actualización de perfiles y estados.
 */
final class UserController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AssignRoleUseCase $assignRoleUseCase,
        private SystemAuditLogRepository $auditLogRepository,
        private PDO $pdo
    ) {}

    /**
     * Despacha la petición según el método HTTP.
     */
    public function handle(array $params): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $id = isset($params['id']) ? (int)$params['id'] : null;

        try {
            match ($method) {
                'GET'   => $this->listUsers(),
                'POST'  => $this->saveUser(null),
                'PUT'   => $this->saveUser($id),
                'PATCH' => $this->patchUser($id),
                default => $this->methodNotAllowed($method)
            };
        } catch (\Exception $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                500,
                'https://carolinamoraestetica.com/errors/internal-error',
                'Error en Gestión de Usuarios',
                $e->getMessage(),
                $_SERVER['REQUEST_URI'] ?? '/api/v1/iam/users'
            );
        }
    }

    private function listUsers(): void
    {
        // Corregido: Join con user_role y role, uso de account_status (Skill 12)
        $sql = "SELECT u.user_id, u.email, u.auth_phone,
                       (CASE WHEN u.account_status = 'ACTIVE' THEN 1 ELSE 0 END) as is_active, 
                       r.role_code as role_name, 
                       u.first_name, u.last_name
                FROM user u
                LEFT JOIN user_role ur ON u.user_id = ur.user_id
                LEFT JOIN role r ON ur.role_id = r.role_id
                ORDER BY u.user_id DESC";
        
        $stmt = $this->pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ResponseHelper::json(200, true, 'Usuarios recuperados', $users);
    }

    private function saveUser(?int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!$input) throw new \InvalidArgumentException('Cuerpo de petición inválido');

        $email     = trim((string)($input['email'] ?? ''));
        $roleCode  = $input['role'] ?? 'CLIENT';
        $isActive  = (int)($input['is_active'] ?? 1);
        $status    = $isActive ? 'ACTIVE' : 'SUSPENDED';
        $firstName = trim((string)($input['first_name'] ?? ''));
        $lastName  = trim((string)($input['last_name'] ?? ''));
        $phone     = trim((string)($input['phone'] ?? ''));
        $password  = (string)($input['password'] ?? '');

        if ($id) {
            // Actualizar datos base en la tabla user (Skill 10: Manejo de Contraseñas)
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $this->pdo->prepare(
                    "UPDATE user SET email = ?, auth_phone = ?, account_status = ?, password_hash = ?, first_name = ?, last_name = ?, updated_at = NOW() WHERE user_id = ?"
                );
                $stmt->execute([$email, $phone, $status, $passwordHash, $firstName, $lastName, $id]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE user SET email = ?, auth_phone = ?, account_status = ?, first_name = ?, last_name = ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$email, $phone, $status, $firstName, $lastName, $id]);
            }
            
            $this->assignRoleUseCase->execute($id, $roleCode);

            // Técnica Upsert (Skill 10/12): Asegurar que el perfil exista aunque el usuario sea Admin/Staff
            $sqlProfile = "INSERT INTO client_profile (client_profile_id, birth_date) 
                           VALUES (?, '1990-01-01')
                           ON DUPLICATE KEY UPDATE client_profile_id = client_profile_id";
            $stmt = $this->pdo->prepare($sqlProfile);
            $stmt->execute([$id]);
        } else {
            $passwordHash = password_hash($input['password'] ?? 'Temporal123*', PASSWORD_BCRYPT);
            // Skill 10: Inserción con auth_phone mandatorio y password_hash
            $stmt = $this->pdo->prepare("INSERT INTO user (email, password_hash, auth_phone, first_name, last_name, account_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $passwordHash, $phone, $firstName, $lastName, $status]);
            $newId = (int)$this->pdo->lastInsertId();
            $this->assignRoleUseCase->execute($newId, $roleCode);

            // birth_date es NOT NULL en el DDL físico
            $stmt = $this->pdo->prepare("INSERT INTO client_profile (client_profile_id, birth_date) VALUES (?, '1990-01-01')");
            $stmt->execute([$newId]);
        }
        ResponseHelper::json(200, true, $id ? 'Usuario actualizado correctamente' : 'Usuario creado exitosamente');
    }

    private function patchUser(?int $id): void {
        if (!$id) return;
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (isset($input['is_active'])) {
            $status = (int)$input['is_active'] ? 'ACTIVE' : 'SUSPENDED';
            $stmt = $this->pdo->prepare("UPDATE user SET account_status = ? WHERE user_id = ?");
            $stmt->execute([$status, $id]);
            ResponseHelper::json(200, true, 'Estado actualizado');
        }
    }
    private function methodNotAllowed(string $method): void { GlobalExceptionHandler::emitRfc7807Response(405, '...', 'Método no permitido', "{$method} no soportado", '...'); }
}