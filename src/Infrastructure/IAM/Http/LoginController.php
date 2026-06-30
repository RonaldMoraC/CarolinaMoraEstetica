<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\Authenticate\AuthenticateUserUseCase;
use App\Application\IAM\Authenticate\AuthenticateDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Infrastructure\Shared\Errors\Exceptions\UnauthorizedException;
use App\Infrastructure\Shared\Errors\Exceptions\ForbiddenException;
use App\Infrastructure\Shared\Security\JwtTokenManager;
use PDO;

/**
 * LoginController
 *
 * Controlador HTTP para el endpoint de autenticación (/api/v1/auth/login).
 * Recibe la solicitud JSON, invoca el Caso de Uso de autenticación
 * y responde con el token JWT correspondiente o el error RFC 7807.
 * (Clean Architecture - Capa de Infraestructura - Skill 1 y 4).
 */
final class LoginController
{
    private AuthenticateUserUseCase $authenticateUserUseCase;
    private JwtTokenManager $jwtTokenManager;
    private PDO $pdo;

    public function __construct(
        AuthenticateUserUseCase $authenticateUserUseCase,
        JwtTokenManager $jwtTokenManager,
        PDO $pdo
    ) {
        $this->authenticateUserUseCase = $authenticateUserUseCase;
        $this->jwtTokenManager = $jwtTokenManager;
        $this->pdo = $pdo;
    }

    /**
     * Procesa la solicitud POST de login.
     *
     * @param array $params Parámetros adicionales de la ruta.
     * @return void
     */
    public function handle(array $params): void
    {
        // 1. Obtener y decodificar el cuerpo JSON de la petición (Skill 10)
        $rawInput = (string) file_get_contents('php://input');
        $body     = json_decode($rawInput, true);

        // Skill 10: Validación perimetral del formato JSON
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type:       'https://carolinamoraestetica.com/errors/invalid-json',
                title:      'JSON Malformado',
                detail:     'El cuerpo de la petición debe ser un objeto JSON válido.',
                instance:   '/api/v1/auth/login'
            );
        }

        /**
         * Skill 10: Sanitización, Tipado Estricto y Casteo Explícito.
         * Desconfiamos de la entrada y forzamos el tipo antes de enviarlo a Application.
         */
        $email    = trim((string) ($body['email'] ?? $body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        // 2. Validación de presencia (RFC 7807 vía Skill 4)
        if ($email === '' || $password === '') {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 400,
                type:       'https://carolinamoraestetica.com/errors/missing-credentials',
                title:      'Credenciales Incompletas',
                detail:     'Se requiere tanto el correo electrónico como la contraseña para iniciar sesión.',
                instance:   '/api/v1/auth/login'
            );
        }

        try {
            // 3. Ejecutar caso de uso con el DTO (Inmutable)
            $dto = new AuthenticateDTO($email, $password);
            $token = $this->authenticateUserUseCase->execute($dto);

            // Skill 10: Validación de claims para determinar la navegación
            $claims = $this->jwtTokenManager->validate($token);
            
            // DEBUG: Vamos a ver en los logs qué está llegando realmente en el token
            error_log("DEBUG JWT CLAIMS para $email: " . print_r($claims, true));

            // Skill 10: Obtención resiliente del rol desde los claims del JWT
            $roleRaw = $claims['role'] ?? $claims['role_code'] ?? 'CLIENT';
            
            // Defensa contra "Array to string conversion" (Skill 1)
            // Si el claim es un array (por una versión previa del token), intentamos extraer el valor escalar
            if (is_array($roleRaw)) {
                $roleRaw = $roleRaw['name'] ?? $roleRaw['id'] ?? $roleRaw[0] ?? 'CLIENT';
            }
            
            // Normalizamos el rol a string en mayúsculas para la comparación
            $role = strtoupper(trim((string) $roleRaw));

            $redirectPath = match (true) {
                in_array($role, ['SUPER_ADMIN', 'BRANCH_ADMIN', 'RECEPCIONIST', '1', 'ADMIN'], true) => '/admin/dashboard',
                default => '/app/dashboard',
            };

            // Fetch display name from client_profile for client users
            $userId = (int) ($claims['user_id'] ?? 0);
            $displayName = '';
            $userEmail = $claims['email'] ?? $email;
            if ($userId > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT first_name, last_name FROM user WHERE user_id = :uid LIMIT 1"
                );
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($profile) {
                    $displayName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                }
            }

            // 4. Retornar respuesta exitosa (Skill 1: Formato consistente)
            ResponseHelper::json(200, true, 'Autenticación exitosa.', [
                'token'       => $token,
                'token_type'  => 'Bearer',
                'expires_in'  => (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600),
                'role_code'   => $role,
                'redirect_to' => $redirectPath,
                'user_name'   => $displayName,
                'user_email'  => $userEmail
            ]);

        } catch (\Exception $e) {
            /**
             * Skill 10: Seguridad Defensiva. 
             * Usamos un mensaje genérico para evitar ataques de enumeración de usuarios.
             */
            $code = $e->getCode();
            if ($code === 401 || $code === 404 || $code === 422) {
                throw new UnauthorizedException('Credenciales incorrectas. Por favor, verifica tus datos.');
            }
            throw $e;
        }
    }
}
