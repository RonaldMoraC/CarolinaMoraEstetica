<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\BrowseCatalog\BrowseServiceCatalogUseCase;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

/**
 * BrowseCatalogController
 *
 * Controlador HTTP para GET /api/v1/catalog/services.
 * Soporta paginación server-side y filtros por query params.
 *
 * Query Params:
 *   - page        (int)    Página actual, default 1
 *   - per_page    (int)    Registros por página, default 15
 *   - search      (string) Filtro LIKE por nombre del servicio
 *   - category_id (int)    Filtro por categoría
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor
 *  - Skill 10 → Casteo explícito y saneo de query params perimetral
 *  - Skill 4  → Errores via ResponseHelper (RFC 7807 compatible)
 */
final class BrowseCatalogController
{
    private BrowseServiceCatalogUseCase $browseUseCase;
    private ServiceRepositoryInterface $serviceRepository;

    public function __construct(
        BrowseServiceCatalogUseCase $browseUseCase,
        ServiceRepositoryInterface $serviceRepository
    ) {
        $this->browseUseCase       = $browseUseCase;
        $this->serviceRepository   = $serviceRepository;
    }

    /**
     * GET /api/v1/catalog/services — Listado paginado con filtros.
     *
     * @param array<string, string> $params Parámetros de ruta inyectados por Router.
     */
    public function handle(array $params = []): void
    {
        // Skill 10 — Casteo explícito defensivo en la periferia
        $page       = (int) ($_GET['page'] ?? 1);
        $perPage    = (int) ($_GET['per_page'] ?? 15);
        $search     = (string) ($_GET['search'] ?? '');
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
            ? (int) $_GET['category_id']
            : null;
        
        // Nuevo: Filtrar por estado activo (Skill 10: Sanitización)
        $isActive = null;
        if (isset($_GET['is_active'])) {
            $param = strtolower((string)$_GET['is_active']);
            if ($param === 'true' || $param === '1') {
                $isActive = true;
            } elseif ($param === 'false' || $param === '0') {
                $isActive = false;
            }
        }

        try {
            $result = $this->browseUseCase->execute($page, $perPage, $search, $categoryId, $isActive);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Catálogo de servicios obtenido exitosamente.',
                data: $result['data'],
                meta: $result['meta']
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error interno al consultar el catálogo de servicios.',
                data: []
            );
        }
    }

    /**
     * GET /api/v1/catalog/services/{id} — Detalle de un servicio.
     *
     * @param array<string, string> $params Parámetros de ruta ({id} inyectado por Router).
     */
    public function show(array $params = []): void
    {
        $serviceId = (int) ($params['id'] ?? 0);

        if ($serviceId <= 0) {
            ResponseHelper::json(
                statusCode: 400,
                success: false,
                message: 'El ID del servicio debe ser un número entero positivo.',
                data: []
            );
        }

        try {
            $service = $this->serviceRepository->findById($serviceId);

            if ($service === null) {
                ResponseHelper::json(
                    statusCode: 404,
                    success: false,
                    message: 'Servicio no encontrado.',
                    data: []
                );
            }

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Servicio encontrado.',
                data: $service
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error interno al consultar el servicio.',
                data: []
            );
        }
    }
}
