<?php
declare(strict_types=1);

namespace App\Domain\IAM\Repositories;

use App\Domain\IAM\Entities\User;
use App\Domain\IAM\ValueObjects\Email;

/**
 * UserRepositoryInterface
 *
 * Contrato de repositorio para gestionar la persistencia de usuarios.
 * (Clean Architecture - Capa de Dominio - Skill 1).
 */
interface UserRepositoryInterface
{
    /**
     * Busca un usuario en el sistema por su correo electrónico.
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Busca un usuario en el sistema por su número de teléfono.
     */
    public function findByPhone(string $phone): ?User;

    /**
     * Busca un usuario en el sistema por su ID.
     */
    public function findById(int $userId): ?User;

    /**
     * Guarda un nuevo usuario en la base de datos y retorna su ID.
     */
    public function save(array $data): int;
}
