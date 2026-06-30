<?php
declare(strict_types=1);

namespace App\Application\IAM\Authenticate;

/**
 * DTO para el transporte de credenciales de login.
 */
final readonly class AuthenticateDTO
{
    public function __construct(
        public string $email,
        public string $password
    ) {}
}