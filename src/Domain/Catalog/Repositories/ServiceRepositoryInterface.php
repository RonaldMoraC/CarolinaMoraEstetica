<?php
declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

/**
 * ServiceRepositoryInterface
 *
 * Contrato del Dominio para la persistencia del catálogo de servicios.
 *
 * Cumple Skill 1 → Interface pura, sin dependencias de infraestructura.
 */
interface ServiceRepositoryInterface
{
    /**
     * Retorna una colección paginada de servicios activos e inactivos.
     *
     * @param int    $page       Página actual (1-based).
     * @param int    $perPage    Cantidad de registros por página (default 15).
     * @param string $search     Filtro de búsqueda por nombre (LIKE, vacío = sin filtro).
     * @param int|null $categoryId Filtro por categoría (null = todas).
     * @param bool|null $isActive  Filtro por estado de actividad (true/false/null).
     * @return array{data: array<int, array<string, mixed>>, meta: array{total_records: int, current_page: int, total_pages: int}}
     */
    public function getPaginated(int $page = 1, int $perPage = 15, string $search = '', ?int $categoryId = null, ?bool $isActive = null): array;

    /**
     * Retorna un servicio por su ID.
     *
     * @param int $serviceId
     * @return array<string, mixed>|null
     */
    public function findById(int $serviceId): ?array;

    /**
     * Persiste un nuevo servicio en el catálogo.
     *
     * @param array<string, mixed> $data
     * @return int El service_id generado por AUTO_INCREMENT.
     */
    public function create(array $data): int;

    /**
     * Actualiza un servicio existente.
     *
     * @param int                  $serviceId
     * @param array<string, mixed> $data
     * @return bool True si la actualización afectó al menos 1 fila.
     */
    public function update(int $serviceId, array $data): bool;

    /**
     * Alterna el estado is_active de un servicio (soft toggle).
     *
     * @param int  $serviceId
     * @param bool $isActive
     * @return bool
     */
    public function toggleActive(int $serviceId, bool $isActive): bool;

    public function findAllActive(): array;

    /**
     * Retorna todos los servicios (activos e inactivos) sin paginación.
     * Usado por el panel administrativo para operaciones masivas.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;
}
