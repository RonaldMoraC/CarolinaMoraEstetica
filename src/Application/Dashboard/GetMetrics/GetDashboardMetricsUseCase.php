<?php
declare(strict_types=1);

namespace App\Application\Dashboard\GetMetrics;

use App\Domain\Dashboard\Repositories\DashboardMetricsRepositoryInterface;

final class GetDashboardMetricsUseCase
{
    public function __construct(
        private readonly DashboardMetricsRepositoryInterface $repository
    ) {}

    public function execute(): array
    {
        $tz = new \DateTimeZone('America/Bogota');
        $now = new \DateTimeImmutable('now', $tz);
        $currentMonth = $now->format('Y-m');
        $prevMonth = $now->modify('first day of last month')->format('Y-m');
        $today = $now->format('Y-m-d');

        // 1. Ingresos y Tendencia
        $rev = $this->repository->getMonthRevenue($currentMonth);
        $prevRev = $this->repository->getPreviousMonthRevenue($prevMonth);
        $revTrend = $prevRev > 0 
            ? ($rev >= $prevRev ? '▲' : '▼') . ' ' . round((abs($rev - $prevRev) / $prevRev) * 100) . '% vs mes ant.'
            : '— Sin datos previos';

        // 2. Citas y Ocupación
        $totalCitas = $this->repository->getMonthAppointmentCount($currentMonth);
        $completadas = $this->repository->getMonthCompletedAppointmentCount($currentMonth);
        $ocupacion = $totalCitas > 0 ? round(($completadas / $totalCitas) * 100) : 0;

        // 3. Clientes y Fidelidad
        $totalClientes = $this->repository->getActiveClientCount();
        $recurrentes = $this->repository->getRecurringClientCount();
        $fidelidad = $totalClientes > 0 ? round(($recurrentes / $totalClientes) * 100) : 0;

        // 4. Rating
        $rating = $this->repository->getAverageRating();
        $reviews = $this->repository->getTotalReviews();

        // 5. Citas de Hoy
        $citasHoy = $this->repository->getTodayAppointments($today);

        return [
            'metrics' => [
                'total_ingresos'    => $rev,
                'ingresos_trend'    => $revTrend,
                'total_citas'       => $totalCitas,
                'ocupacion'         => $ocupacion,
                'total_clientes'    => $totalClientes,
                'clientes_trend'    => $fidelidad . '% de fidelidad',
                'rating_promedio'   => $rating,
                'total_resenas'     => $reviews
            ],
            'citas_hoy' => $citasHoy
        ];
    }
}