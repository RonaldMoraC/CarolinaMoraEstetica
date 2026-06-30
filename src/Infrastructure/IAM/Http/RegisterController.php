<?php
declare(strict_types=1);

namespace App\Infrastructure\IAM\Http;

use App\Application\IAM\RegisterClient\RegisterNewClientUseCase;
use App\Application\IAM\RegisterClient\RegisterClientDTO;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/**
 * RegisterController — Punto de entrada para nuevos registros
 *
 * Implementa validación perimetral estricta (Skill 10) y respuestas RFC 7807 (Skill 4).
 */
final class RegisterController
{
    public function __construct(
        private RegisterNewClientUseCase $useCase
    ) {}

    public function handle(array $params): void
    {
        $rawInput = (string) file_get_contents('php://input');
        $body     = json_decode($rawInput, true) ?? [];

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            GlobalExceptionHandler::emitRfc7807Response(400, 'https://carolinamoraestetica.com/errors/invalid-json', 'JSON Inválido', 'Cuerpo de petición malformado.', '/api/v1/auth/register');
        }

        // 1. Sanitización y Validación Perimetral (Skill 10)
        // Extraemos y casteamos explícitamente cada valor antes de validar.
        $email     = trim((string)($body['email'] ?? ''));
        $password  = (string)($body['password'] ?? '');
        $phone     = trim((string)($body['phone'] ?? ''));
        $firstName = trim((string)($body['firstName'] ?? ''));
        $lastName  = trim((string)($body['lastName'] ?? ''));

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña es demasiado corta (mínimo 8 caracteres).';
        }
        if ($phone === '') {
            $errors[] = 'El número de teléfono es obligatorio.';
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'Nombre y apellido son campos obligatorios.';
        }

        if (!empty($errors)) {
            GlobalExceptionHandler::emitRfc7807Response(
                400,
                'https://carolinamoraestetica.com/errors/validation',
                'Validación Fallida',
                implode(' ', $errors),
                '/api/v1/auth/register'
            );
        }

        try {
            // 2. Construcción de DTO inmutable con datos ya validados
            $dto = new RegisterClientDTO(
                email:     $email,
                password:  $password,
                phone:     $phone,
                firstName: $firstName,
                lastName:  $lastName
            );

            // 3. Ejecución del proceso de registro
            $this->useCase->execute($dto);
            ResponseHelper::json(201, true, 'Registro exitoso. Bienvenido a Carolina Mora Estética.');

        } catch (\DomainException $e) {
            // Skill 4: Manejo de errores de negocio (ej. email ya registrado)
            GlobalExceptionHandler::emitRfc7807Response(
                $e->getCode() ?: 400,
                'https://carolinamoraestetica.com/errors/registration-conflict',
                'Error de Registro',
                $e->getMessage(),
                '/api/v1/auth/register'
            );
        }
    }
}
