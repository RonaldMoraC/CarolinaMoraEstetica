<?php
declare(strict_types=1);

namespace App\Application\IAM\UpdateProfile;

/**
 * UpdateProfileDTO
 * Objeto de transferencia de datos inmutable para la actualización de perfil
 * de usuario y ficha de salud/preferencias de estética.
 */
final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $email,
        public ?string $password = null,
        public ?string $medicalNotesAllergies = null,
        public ?string $preferences = null
    ) {}
}
