<?php declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Infrastructure\Catalog\Persistence\PdoCategoryRepository;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

/**
 * GetCategoriesController
 *
 * Controlador HTTP para GET /api/v1/catalog/categories.
 * Retorna las categorías de servicios disponibles para filtros PWA.
 *
 * Cumple Skill 1 → strict_types, Skill 10 → sin parámetros de entrada.
 */
final class GetCategoriesController
{
    private PdoCategoryRepository $categoryRepository;

    public function __construct(PdoCategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * GET /api/v1/catalog/categories
     *
     * @param array<string, mixed> $params Parámetros de ruta inyectados por Router.
     */
    public function handle(array $params = []): void
    {
        try {
            $categories = $this->categoryRepository->findAll();

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Categorías obtenidas exitosamente.',
                data: $categories
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error interno al consultar las categorías.',
                data: []
            );
        }
    }
}
