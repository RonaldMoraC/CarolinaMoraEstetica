<?php
declare(strict_types=1);

namespace App\Infrastructure\Dashboard\Http;

use App\Application\Dashboard\GetMetrics\GetDashboardMetricsUseCase;
use App\Infrastructure\Shared\Helpers\ResponseHelper;
use App\Infrastructure\Shared\Logging\AppLogger;

/**
 * AdminMetricsController
 *
 * Controlador HTTP que expone el endpoint GET /api/v1/admin/metrics.
 * Orquesta la obtención de KPIs del Dashboard y emite la respuesta JSON.
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor, sin acoplamiento directo
 *  - Skill 4  → Errores formateados bajo RFC 7807 via ResponseHelper
 *  - Skill 10 → No recibe datos de entrada que requieran sanitización (endpoint GET)
 */
final class AdminMetricsController
{
    private GetDashboardMetricsUseCase $useCase;
    private AppLogger $logger;

    public function __construct(GetDashboardMetricsUseCase $useCase, AppLogger $logger)
    {
        $this->useCase = $useCase;
        $this->logger = $logger;
    }

    /**
     * Maneja la petición GET /api/v1/admin/metrics.
     *
     * @param array<string, string> $params Parámetros de ruta (vacío para este endpoint).
     */
    public function handle(array $params = []): void
    {
        try {
            $result = $this->useCase->execute();

            // Separar citas_hoy del payload principal de KPIs
            $citasHoy = $result['citas_hoy'] ?? [];

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Métricas del dashboard obtenidas exitosamente.',
                data: [
                    'metrics' => $result['metrics'] ?? [],
                    'appointments_today' => $citasHoy,
                ]
            );
        } catch (\Throwable $e) {
            if (isset($this->logger)) {
                $this->logger->error('Error al obtener métricas del dashboard', [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error al obtener métricas del dashboard.',
                data: ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }
}
