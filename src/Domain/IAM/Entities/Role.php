<?php
declare(strict_types=1);

namespace App\Domain\IAM\Entities;

final class Role
{
    public const CLIENT = 'CLIENT';
    public const ADMIN = 'ADMIN';
    public const PROFESSIONAL = 'PROFESSIONAL';
    public const RECEPTIONIST = 'RECEPTIONIST';
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const WHATSAPP_BOT_AGENT = 'WHATSAPP_BOT_AGENT';

    private string $name;

    public function __construct(string $name)
    {
        if (!in_array($name, [self::CLIENT, self::ADMIN, self::PROFESSIONAL, self::RECEPTIONIST, self::SUPER_ADMIN, self::WHATSAPP_BOT_AGENT], true)) {
            throw new \InvalidArgumentException("Invalid role name: {$name}");
        }
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}