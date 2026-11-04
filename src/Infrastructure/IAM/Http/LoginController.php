<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\Authenticate\AuthenticateUserUseCase;
use App\Application\IAM\Authenticate\AuthenticateDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Infrastructure\Shared\Errors\Exceptions\UnauthorizedException;
use App\Infrastructure\Shared\Security\JwtTokenManager;
use App\Infrastructure\Shared\Logging\AppLogger;
use PDO;

/**
 * LoginController
 *
 * Controlador HTTP para el endpoint de autenticación (/api/v1/auth/login).
 * Recibe la solicitud JSON, invoca el Caso de Uso de autenticación
 * y responde con el token JWT correspondiente o el error RFC 7807.
 *
 * Trazabilidad de acceso (Skill 9):
 *   - Login exitoso  → AppLogger::info()  — siempre registrado.
 *   - Login fallido  → AppLogger::warning() — alerta de posible ataque.
 *   - Debug de claims → AppLogger::debug() — SOLO en entornos no-producción.
 *
 * (Clean Architecture - Capa de Infraestructura - Skills 1, 4, 6, 9)
 */
final class LoginController
{
    public function __construct(
        private readonly AuthenticateUserUseCase $authenticateUserUseCase,
        private readonly JwtTokenManager         $jwtTokenManager,
        private readonly PDO                     $pdo,
        private readonly AppLogger               $logger
    ) {}

    /**
     * Procesa la solicitud POST de login.
     *
     * @param array<string, string> $params Parámetros adicionales de la ruta.
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

        // Capturar IP real (con soporte para Cloudflare / proxies inversos) — Skill 9
        $ipAddress = (string) (
            $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'desconocida'
        );

        try {
            // 3. Ejecutar caso de uso con el DTO (Inmutable)
            $dto   = new AuthenticateDTO($email, $password);
            $token = $this->authenticateUserUseCase->execute($dto);

            // Skill 10: Validación de claims para determinar la navegación
            $claims = $this->jwtTokenManager->validate($token);

            // Skill 4 — Debug seguro: solo escribe en entornos no-producción.
            // En producción AppLogger::debug() es un no-op y nunca persiste los claims.
            $this->logger->debug('JWT claims validados tras login', [
                'email'   => $email,
                'user_id' => $claims['user_id'] ?? null,
                'role'    => $claims['role'] ?? null,
            ]);

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
                in_array($role, ['SUPER_ADMIN', 'MANAGER', 'ESPECIALIST', 'RECEPCIONIST', '1', 'ADMIN'], true) => '/admin/dashboard',
                default => '/app/dashboard',
            };

            // Fetch display name from user table (JOIN optimizado — Skill 12 / ALTO-04)
            $userId      = (int) ($claims['user_id'] ?? 0);
            $displayName = '';
            $userEmail   = $claims['email'] ?? $email;
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

            // 4. Establecer cookies seguras para el backend (ViewAuthMiddleware y layouts)
            // Skill 6: httponly = true — blindaje contra robo del JWT vía XSS en el navegador.
            $cookieOptions = [
                'expires'  => time() + (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600),
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            setcookie('auth_token', $token, $cookieOptions);
            setcookie('user_role', $role, $cookieOptions);

            // Skill 9: Trazabilidad de acceso — login exitoso (sin datos sensibles)
            $this->logger->info('Login exitoso', [
                'email'    => $email,
                'user_id'  => $userId,
                'role'     => $role,
                'ip'       => $ipAddress,
                'redirect' => $redirectPath,
            ]);

            // 5. Retornar respuesta exitosa (Skill 1: Formato consistente)
            ResponseHelper::json(200, true, 'Autenticación exitosa.', [
                'token'       => $token,
                'token_type'  => 'Bearer',
                'expires_in'  => (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600),
                'role_code'   => $role,
                'redirect_to' => $redirectPath,
                'user_name'   => $displayName,
                'user_email'  => $userEmail,
            ]);

        } catch (\Exception $e) {
            /**
             * Skill 10: Seguridad Defensiva.
             * Usamos un mensaje genérico para evitar ataques de enumeración de usuarios.
             *
             * Skill 9: Trazabilidad de seguridad — intento fallido sin exponer contraseñas.
             */
            $code = $e->getCode();
            if ($code === 401 || $code === 404 || $code === 422) {
                $this->logger->warning('Intento de login fallido — credenciales incorrectas', [
                    'email' => $email,
                    'ip'    => $ipAddress,
                    'code'  => $code,
                ]);
                throw new UnauthorizedException('Credenciales incorrectas. Por favor, verifica tus datos.');
            }
            throw $e;
        }
    }
}
