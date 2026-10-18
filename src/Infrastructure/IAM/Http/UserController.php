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
        // Join con user_role y role, uso de account_status (Skill 12)
        $sql = "SELECT u.user_id, u.email, u.auth_phone,
                       (CASE WHEN u.account_status = 'ACTIVE' THEN 1 ELSE 0 END) as is_active,
                       r.role_code as role_name,
                       u.first_name, u.last_name
                FROM user u
                LEFT JOIN user_role ur ON u.user_id = ur.user_id
                LEFT JOIN role r ON ur.role_id = r.role_id
                ORDER BY u.user_id DESC";

        $stmt  = $this->pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ResponseHelper::json(200, true, 'Usuarios recuperados', $users);
    }

    /**
     * Crea (POST) o actualiza (PUT) un usuario.
     *
     * En modo UPDATE construye el SET de forma 100% dinámica: solo incluye
     * los campos que el frontend envió explícitamente en el payload.
     * Esto evita disparar los UNIQUE constraints (uq_user_email, uq_user_auth_phone)
     * cuando el usuario no modificó esos campos (Skill 1 + Skill 10).
     */
    private function saveUser(?int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!$input) throw new \InvalidArgumentException('Cuerpo de petición inválido');

        if ($id) {
            // ── MODO UPDATE ────────────────────────────────────────────────────
            $setClauses = [];
            $bindings   = [];

            if (isset($input['email'])) {
                $email = trim((string)$input['email']);
                if ($email === '') throw new \InvalidArgumentException('El correo no puede estar vacío.');
                $setClauses[] = 'email = ?';
                $bindings[]   = $email;
            }
            if (isset($input['phone'])) {
                $setClauses[] = 'auth_phone = ?';
                $bindings[]   = trim((string)$input['phone']);
            }
            if (isset($input['is_active'])) {
                $setClauses[] = 'account_status = ?';
                $bindings[]   = (int)$input['is_active'] ? 'ACTIVE' : 'SUSPENDED';
            }
            if (isset($input['password']) && (string)$input['password'] !== '') {
                $setClauses[] = 'password_hash = ?';
                $bindings[]   = password_hash((string)$input['password'], PASSWORD_BCRYPT);
            }
            if (isset($input['first_name'])) {
                $setClauses[] = 'first_name = ?';
                $bindings[]   = trim((string)$input['first_name']);
            }
            if (isset($input['last_name'])) {
                $setClauses[] = 'last_name = ?';
                $bindings[]   = trim((string)$input['last_name']);
            }

            // Solo ejecutar UPDATE si hay al menos un campo a modificar
            if (!empty($setClauses)) {
                $setClauses[] = 'updated_at = NOW()';
                $bindings[]   = $id;
                $sql  = 'UPDATE user SET ' . implode(', ', $setClauses) . ' WHERE user_id = ?';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bindings);
            }

            // Asignar rol solo si vino en el payload
            if (isset($input['role'])) {
                $this->assignRoleUseCase->execute($id, (string)$input['role']);
            }

            // Upsert client_profile para garantizar integridad referencial (Skill 10/12)
            $stmt = $this->pdo->prepare(
                "INSERT INTO client_profile (client_profile_id, birth_date)
                 VALUES (?, '1990-01-01')
                 ON DUPLICATE KEY UPDATE client_profile_id = client_profile_id"
            );
            $stmt->execute([$id]);

        } else {
            // ── MODO INSERT ────────────────────────────────────────────────────
            $email = trim((string)($input['email'] ?? ''));
            if ($email === '') throw new \InvalidArgumentException('El correo electrónico es obligatorio.');

            $phone     = trim((string)($input['phone'] ?? ''));
            $roleCode  = (string)($input['role'] ?? 'CLIENT');
            $isActive  = (int)($input['is_active'] ?? 1);
            $status    = $isActive ? 'ACTIVE' : 'SUSPENDED';
            $firstName = trim((string)($input['first_name'] ?? ''));
            $lastName  = trim((string)($input['last_name'] ?? ''));

            $passwordHash = password_hash($input['password'] ?? 'Temporal123*', PASSWORD_BCRYPT);

            $stmt = $this->pdo->prepare(
                'INSERT INTO user (email, password_hash, auth_phone, first_name, last_name, account_status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$email, $passwordHash, $phone, $firstName, $lastName, $status]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->assignRoleUseCase->execute($newId, $roleCode);

            // birth_date es NOT NULL en el DDL físico
            $stmt = $this->pdo->prepare(
                "INSERT INTO client_profile (client_profile_id, birth_date) VALUES (?, '1990-01-01')"
            );
            $stmt->execute([$newId]);
        }

        ResponseHelper::json(200, true, $id ? 'Usuario actualizado correctamente' : 'Usuario creado exitosamente');
    }

    private function patchUser(?int $id): void
    {
        if (!$id) return;
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (isset($input['is_active'])) {
            $status = (int)$input['is_active'] ? 'ACTIVE' : 'SUSPENDED';
            $stmt   = $this->pdo->prepare("UPDATE user SET account_status = ? WHERE user_id = ?");
            $stmt->execute([$status, $id]);
            ResponseHelper::json(200, true, 'Estado actualizado');
        }
    }

    private function methodNotAllowed(string $method): void
    {
        GlobalExceptionHandler::emitRfc7807Response(
            405, '...', 'Método no permitido', "{$method} no soportado", '...'
        );
    }
}