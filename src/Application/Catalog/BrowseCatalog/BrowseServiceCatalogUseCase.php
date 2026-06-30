<?php
declare(strict_types=1);

namespace App\Application\Catalog\BrowseCatalog;

use App\Domain\Catalog\Repositories\ServiceRepositoryInterface;

final class BrowseServiceCatalogUseCase
{
    private ServiceRepositoryInterface $serviceRepository;

    public function __construct(ServiceRepositoryInterface $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function execute(int $page = 1, int $perPage = 15, string $search = '', ?int $categoryId = null, ?bool $isActive = null): array
    {
        return $this->serviceRepository->getPaginated($page, $perPage, $search, $categoryId, $isActive);
    }

    public function getCategories(): array
    {
        return $this->serviceRepository->getCategories();
    }
}