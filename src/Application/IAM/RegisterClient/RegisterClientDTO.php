<?php
declare(strict_types=1);

namespace App\Application\IAM\RegisterClient;

/**
 * RegisterClientDTO — Objeto inmutable de transporte de datos de registro.
 */
final readonly class RegisterClientDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $phone,
        public string $firstName,
        public string $lastName
    ) {}
}
