<?php
declare(strict_types=1);

namespace App\Infrastructure\Dashboard\Http;

use App\Application\Dashboard\GetAnalytics\GetBusinessAnalyticsUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Logging\AppLogger;

final class AdminAnalyticsController
{
    public function __construct(
        private GetBusinessAnalyticsUseCase $useCase,
        private AppLogger $logger
    ) {}

    public function handle(array $params = []): void
    {
        try {
            $period = $_GET['period'] ?? 'month';
            $data = $this->useCase->execute($period);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Analíticas del negocio obtenidas con éxito.',
                data: $data
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error al obtener analíticas del negocio', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error al obtener analíticas: ' . $e->getMessage()
            );
        }
    }
}
