<?php
declare(strict_types=1);

namespace App\Application\Dashboard\GetAnalytics;

use App\Domain\Dashboard\Repositories\DashboardMetricsRepositoryInterface;

final class GetBusinessAnalyticsUseCase
{
    public function __construct(
        private readonly DashboardMetricsRepositoryInterface $repository
    ) {}

    public function execute(string $period = 'month'): array
    {
        $tz = new \DateTimeZone('America/Bogota');
        $now = new \DateTimeImmutable('now', $tz);
        $currentMonth = $now->format('Y-m');
        $prevMonth = $now->modify('first day of last month')->format('Y-m');

        // 1. KPIs principales
        $rawMetrics = $this->repository->getMetrics($period);
        $ingresosMes = (float)($rawMetrics['revenue'] ?? 0);
        $citasMes = (int)($rawMetrics['total_appointments'] ?? 0);
        $clientesUnicos = (int)($rawMetrics['unique_clients'] ?? 0);

        // Ingresos mes anterior para tendencia
        $prevRevenue = $this->repository->getPreviousMonthRevenue($prevMonth);
        $ingresosTrend = $prevRevenue > 0
            ? ($ingresosMes >= $prevRevenue ? '▲ +' : '▼ ') . round((abs($ingresosMes - $prevRevenue) / $prevRevenue) * 100) . '% vs mes anterior'
            : '— Sin datos previos';

        $ticketPromedio = $citasMes > 0 ? $ingresosMes / $citasMes : 0.0;

        // Clientes recurrentes porcentaje
        $totalClientes = $this->repository->getActiveClientCount();
        $recurrentes = $this->repository->getRecurringClientCount();
        $recurrentesPct = $totalClientes > 0 ? round(($recurrentes / $totalClientes) * 100, 1) : 0.0;

        $kpis = [
            'ingresos_mensuales' => $ingresosMes,
            'ingresos_trend' => $ingresosTrend,
            'citas_mes' => $citasMes,
            'citas_trend' => "🎯 {$citasMes} citas registradas en el periodo",
            'clientes_recurrentes_pct' => $recurrentesPct,
            'clientes_trend' => "⭐ {$recurrentes} clientes recurrentes",
            'ticket_promedio' => $ticketPromedio,
            'ticket_trend' => 'Promedio por cita atendida'
        ];

        // 2. Volumen por categoría
        $categoriasRaw = $this->repository->getAppointmentsByCategory();
        $maxCitasCat = max(array_column($categoriasRaw, 'count')) ?: 1;
        $categorias = array_map(function($cat) use ($maxCitasCat) {
            $citas = (int)$cat['count'];
            return [
                'nombre' => $cat['category'] ?? 'General',
                'citas' => $citas,
                'porcentaje' => (int)round(($citas / $maxCitasCat) * 100),
                'color' => ''
            ];
        }, $categoriasRaw);

        // 3. Desglose por método de pago
        $pagosRaw = $this->repository->getPaymentsByMethod();
        $totalPagos = array_sum(array_column($pagosRaw, 'total')) ?: 1;
        $metodosPago = array_map(function($pago) use ($totalPagos) {
            $monto = (float)$pago['total'];
            return [
                'nombre' => ucfirst((string)$pago['method']),
                'monto' => $monto,
                'porcentaje' => (int)round(($monto / $totalPagos) * 100),
                'color' => ''
            ];
        }, $pagosRaw);

        // Si no hay métodos de pago registrados aún, proveer estructura vacía o limpia
        if (empty($metodosPago)) {
            $metodosPago = [
                ['nombre' => 'Efectivo / Transferencia', 'monto' => 0.0, 'porcentaje' => 100, 'color' => '#2ecc71']
            ];
        }

        // 4. Top 5 servicios
        $topServiciosRaw = $this->repository->getTopServices();
        $topServicios = array_map(function($srv) {
            return [
                'nombre' => $srv['name'],
                'citas' => (int)$srv['total_uses'],
                'ingreso' => (float)$srv['revenue']
            ];
        }, $topServiciosRaw);

        // 5. Rendimiento profesionales
        $profesionalesRaw = $this->repository->getProfessionalPerformance();
        $profesionales = array_map(function($prof) {
            return [
                'nombre' => trim($prof['first_name'] . ' ' . $prof['last_name']),
                'citas' => (int)$prof['completed_appointments'],
                'ingreso' => (float)$prof['generated_revenue'],
                'rating' => 5.0 // Valor por defecto o calculado si estuviera en repositorio
            ];
        }, $profesionalesRaw);

        return [
            'kpis' => $kpis,
            'categorias' => $categorias,
            'metodos_pago' => $metodosPago,
            'top_servicios' => $topServicios,
            'profesionales' => $profesionales
        ];
    }
}
