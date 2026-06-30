<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\UpdateProfile\UpdateUserProfileUseCase;
use App\Application\IAM\UpdateProfile\UpdateProfileDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Domain\Shared\Exceptions\DomainException;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * UpdateProfileController
 * Maneja la actualización de datos personales del usuario autenticado.
 */
final class UpdateProfileController
{
    public function __construct(
        private UpdateUserProfileUseCase $useCase
    ) {}

    public function handle(array $params): void
    {
        // 1. Obtener identidad del usuario (Skill 10: Auth Context)
        $authUser = $params['auth_user'] ?? null;
        if (!$authUser || !isset($authUser['user_id'])) {
            GlobalExceptionHandler::emitRfc7807Response(
                401,
                'https://carolinamoraestetica.com/errors/unauthorized',
                'Sesión no válida',
                'No se pudo identificar al usuario autenticado.',
                '/api/v1/auth/me/profile'
            );
            return;
        }

        $userId = (int) $authUser['user_id'];

        // 2. Leer input JSON (Skill 10)
        $rawInput = (string) file_get_contents('php://input');
        $body = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            GlobalExceptionHandler::emitRfc7807Response(
                400,
                'https://carolinamoraestetica.com/errors/invalid-json',
                'JSON Inválido',
                'El cuerpo de la petición debe ser un objeto JSON válido.',
                '/api/v1/auth/me/profile'
            );
            return;
        }

        try {
            // 3. Sanitización y Validación Perimetral (Skill 10)
            $firstName = trim((string)($body['first_name'] ?? ''));
            $lastName  = trim((string)($body['last_name'] ?? ''));
            $phone     = trim((string)($body['phone'] ?? ''));
            $email     = trim((string)($body['email'] ?? ''));
            $password  = isset($body['password']) && $body['password'] !== '' ? (string)$body['password'] : null;

            if ($firstName === '' || $lastName === '' || $phone === '' || $email === '') {
                throw new InvalidArgumentException('Faltan campos obligatorios para actualizar el perfil.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El formato del correo electrónico no es válido.');
            }

            // 4. Ejecución del Caso de Uso (Skill 1)
            $dto = new UpdateProfileDTO($firstName, $lastName, $phone, $email, $password);
            $this->useCase->execute($userId, $dto);

            ResponseHelper::json(200, true, 'Perfil actualizado correctamente.');

        } catch (DomainException | NotFoundException | InvalidArgumentException $e) {
            // Skill 4: Manejo Estandarizado RFC 7807
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: $e instanceof NotFoundException ? 404 : ($e instanceof DomainException ? 422 : 400),
                type: 'https://carolinamoraestetica.com/errors/' . strtolower((new \ReflectionClass($e))->getShortName()),
                title: 'Error al actualizar perfil',
                detail: $e->getMessage(),
                instance: '/api/v1/auth/me/profile'
            );
        }
    }
}