<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Http;

use App\Domain\Dashboard\Repositories\DashboardMetricsRepositoryInterface;
use App\Infrastructure\Shared\Helpers\ResponseHelper;

/**
 * GetAppointmentsController
 *
 * Controlador HTTP para el endpoint GET /api/v1/booking/appointments.
 * Soporta el parámetro de consulta ?date=today para obtener las citas
 * del día actual, utilizado por el Dashboard Administrativo.
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor
 *  - Skill 7  → DateTimeImmutable con zona America/Bogota
 *  - Skill 10 → Parámetros saneados con casteo explícito
 */
final class GetAppointmentsController
{
    private DashboardMetricsRepositoryInterface $metricsRepository;

    /**
     * Se inyecta DashboardMetricsRepositoryInterface porque ya contiene
     * el método getTodayAppointments() con JOINs optimizados (Skill 12).
     */
    public function __construct(DashboardMetricsRepositoryInterface $metricsRepository)
    {
        $this->metricsRepository = $metricsRepository;
    }

    /**
     * Maneja la petición GET /api/v1/booking/appointments.
     *
     * Parámetros de consulta soportados:
     *   - date=today  → Retorna citas del día actual
     *   - date=YYYY-MM-DD → Retorna citas de una fecha específica
     *
     * @param array<string, string> $params Parámetros de ruta inyectados por el Router.
     */
    public function handle(array $params = []): void
    {
        try {
            // Skill 10 — Casteo explícito y sanitización perimetral del query param
            $dateParam = (string) ($_GET['date'] ?? '');

            // Skill 7 — DateTimeImmutable con zona horaria explícita
            if ($dateParam === 'today' || $dateParam === '') {
                $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
                $date = $now->format('Y-m-d');
            } else {
                // Validar formato de fecha YYYY-MM-DD
                $parsed = \DateTimeImmutable::createFromFormat(
                    'Y-m-d',
                    $dateParam,
                    new \DateTimeZone('America/Bogota')
                );

                if ($parsed === false) {
                    ResponseHelper::json(
                        statusCode: 400,
                        success: false,
                        message: 'El parámetro "date" debe ser "today" o una fecha en formato YYYY-MM-DD.',
                        data: []
                    );
                }

                $date = $parsed->format('Y-m-d');
            }

            $appointments = $this->metricsRepository->getTodayAppointments($date);

            ResponseHelper::json(
                statusCode: 200,
                success: true,
                message: 'Citas obtenidas exitosamente.',
                data: $appointments
            );
        } catch (\Throwable $e) {
            ResponseHelper::json(
                statusCode: 500,
                success: false,
                message: 'Error interno al consultar las citas.',
                data: []
            );
        }
    }
}
