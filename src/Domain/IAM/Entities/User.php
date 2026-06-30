<?php
declare(strict_types=1);

namespace App\Domain\IAM\Entities;

use App\Domain\IAM\ValueObjects\Email;
use App\Domain\IAM\ValueObjects\HashedPassword;

final class User
{
    private int $userId;
    private Email $email;
    private HashedPassword $passwordHash;
    private string $authPhone;
    private string $firstName;
    private string $lastName;
    private string $accountStatus;
    private Role $role;

    public function __construct(
        int $userId,
        Email $email,
        HashedPassword $passwordHash,
        string $authPhone,
        string $firstName,
        string $lastName,
        string $accountStatus,
        ?Role $role = null // Default to CLIENT if not provided
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->authPhone = $authPhone;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->accountStatus = $accountStatus;
        $this->role = $role ?? new Role(Role::CLIENT); // Default to CLIENT
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPasswordHash(): HashedPassword
    {
        return $this->passwordHash;
    }

    public function getAuthPhone(): string
    {
        return $this->authPhone;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->accountStatus === 'ACTIVE';
    }
}