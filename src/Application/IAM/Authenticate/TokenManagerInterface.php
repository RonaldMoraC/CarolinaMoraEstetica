<?php
declare(strict_types=1);

namespace App\Application\IAM\Authenticate;

/**
 * TokenManagerInterface
 *
 * Contrato de la capa de Aplicación para gestionar tokens JWT.
 * Permite desacoplar los Casos de Uso del JwtTokenManager físico.
 */
interface TokenManagerInterface
{
    /**
     * Genera un token firmado a partir de una lista de claims.
     *
     * @param array<string, mixed> $claims
     * @return string
     */
    public function generate(array $claims): string;

    /**
     * Valida el token y devuelve el payload decodificado.
     *
     * @param string $token
     * @return array<string, mixed>
     */
    public function validate(string $token): array;
}
