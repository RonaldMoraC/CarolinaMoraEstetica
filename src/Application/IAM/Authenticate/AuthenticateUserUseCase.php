<?php
declare(strict_types=1);

namespace App\Application\IAM\Authenticate;

use App\Domain\IAM\Repositories\UserRepositoryInterface;
use App\Domain\IAM\ValueObjects\Email;
use DomainException;

/**
 * Caso de Uso: Autenticar Usuario
 * Orquesta la validación de credenciales y la emisión del token.
 */
final class AuthenticateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        // Desacoplamiento (Skill 1): Depender de la interfaz, no de la implementación
        private readonly TokenManagerInterface $tokenManager
    ) {}

    /**
     * Ejecuta el proceso de autenticación.
     * 
     * @param AuthenticateDTO $dto
     * @return string Token JWT generado
     * @throws DomainException Si las credenciales son incorrectas o el usuario está inactivo.
     */
    public function execute(AuthenticateDTO $dto): string
    {
        // 1. Validar formato de email mediante Value Object
        $emailVO = new Email($dto->email);

        // 2. Buscar usuario en el repositorio
        $user = $this->repository->findByEmail($emailVO);

        // 3. Verificación de existencia y estado activo
        if (!$user || !$user->isActive()) {
            // Skill 4: Error semántico para el GlobalExceptionHandler
            throw new DomainException("El usuario no existe o se encuentra inhabilitado.", 401);
        }

        // 4. Verificar hash de contraseña (encapsulado en el Value Object de Dominio)
        if (!$user->getPasswordHash()->verify($dto->password)) {
            throw new DomainException("La contraseña ingresada es incorrecta.", 401);
        }

        // 5. Generar Claims para el JWT (Skill 6 del documento 06_Seguridad_JWT_RBAC)
        $claims = [
            'user_id' => $user->getUserId(),
            'email'   => $user->getEmail()->value,
            'status'  => $user->getAccountStatus(),
            'role'    => (string) $user->getRole()->getName() // Extraemos el nombre (string) para evitar el error de conversión
        ];

        // El TTL se gestiona internamente en el Infrastructure/JwtTokenManager
        return $this->tokenManager->generate($claims);
    }
}