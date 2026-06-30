<?php
declare(strict_types=1);

namespace App\Domain\IAM\ValueObjects;

use InvalidArgumentException;

/**
 * HashedPassword Value Object
 * 
 * Gestiona el hashing y la verificación de contraseñas de forma segura.
 */
final readonly class HashedPassword
{
    public string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('El hash de la contraseña no puede estar vacío.');
        }
        $this->value = $value;
    }

    /**
     * Crea una instancia a partir de una contraseña en texto plano, aplicando el hash.
     * Utilizado durante el registro de usuarios.
     */
    public static function fromRaw(string $plainPassword): self
    {
        // Skill 10: Validación mínima de seguridad antes del hash
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
        
        return new self(password_hash($plainPassword, PASSWORD_DEFAULT));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Verifica si una contraseña en texto plano coincide con este hash.
     * Utilizado durante el login.
     */
    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->value);
    }
}