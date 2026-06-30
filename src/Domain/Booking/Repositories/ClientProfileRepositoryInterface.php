<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

interface ClientProfileRepositoryInterface
{
    /**
     * Guarda un nuevo perfil de cliente en la base de datos.
     *
     * @param array<string, mixed> $data Los datos del perfil a guardar.
     * @return int El ID del perfil de cliente recién creado.
     */
    public function save(array $data): int;
}
