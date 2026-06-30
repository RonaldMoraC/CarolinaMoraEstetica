<?php
declare(strict_types=1);

namespace App\Domain\IAM\ValueObjects;

use InvalidArgumentException;

/**
 * Email Value Object
 * 
 * Garantiza que el correo electrónico tenga un formato válido en todo el sistema.
 */
final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        $sanitizedEmail = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        
        if ($sanitizedEmail === false) {
            throw new InvalidArgumentException(
                sprintf('"%s" no es un formato de correo electrónico válido.', $value)
            );
        }
        
        $this->value = $sanitizedEmail;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}