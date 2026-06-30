<?php
declare(strict_types=1);

namespace App\Application\IAM\UpdateProfile;

/**
 * UpdateProfileDTO
 * Objeto de transferencia de datos inmutable para la actualización de perfil.
 */
final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $email,
        public ?string $password = null
    ) {}
}