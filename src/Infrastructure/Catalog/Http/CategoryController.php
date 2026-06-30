<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Application\Catalog\BrowseCatalog\BrowseServiceCatalogUseCase;
use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

final class CategoryController
{
    private BrowseServiceCatalogUseCase $browseUseCase;

    public function __construct(BrowseServiceCatalogUseCase $browseUseCase)
    {
        $this->browseUseCase = $browseUseCase;
    }

    public function handle(): void
    {
        try {
            $categories = $this->browseUseCase->getCategories();
            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Categorías cargadas exitosamente',
                data: $categories
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error al cargar las categorías',
                data: []
            );
        }
    }
}