<?php
declare(strict_types=1);

namespace App\Infrastructure\Dashboard\Persistence;

use App\Domain\Dashboard\Repositories\DashboardMetricsRepositoryInterface;
use PDO;

/**
 * PdoDashboardMetricsRepository
 *
 * Provee métricas agregadas optimizadas para el Dashboard Administrativo.
 * Aplica Skill 12: JOINs estratégicos para evitar N+1 y Skill 10: Prepared Statements.
 */
final class PdoDashboardMetricsRepository implements DashboardMetricsRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    // ── KPIs requeridos por GetDashboardMetricsUseCase ──────────────────

    /**
     * Ingresos del mes actual (final_price de citas completadas).
     * @param string $month Formato Y-m (ej: 2026-06)
     */
    public function getMonthRevenue(string $month): float
    {
        $sql = "SELECT COALESCE(SUM(a.final_price), 0.00)
                FROM appointment a
                WHERE DATE_FORMAT(a.scheduled_timestamp, '%Y-%m') = :month
                  AND a.appointment_status IN ('COMPLETED', 'IN_PROGRESS', 'CONFIRMED', 'PENDING')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    /**
     * Ingresos del mes anterior para cálculo de tendencia.
     * @param string $prevMonth Formato Y-m (ej: 2026-05)
     */
    public function getPreviousMonthRevenue(string $prevMonth): float
    {
        $sql = "SELECT COALESCE(SUM(a.final_price), 0.00)
                FROM appointment a
                WHERE DATE_FORMAT(a.scheduled_timestamp, '%Y-%m') = :prev_month
                  AND a.appointment_status IN ('COMPLETED', 'IN_PROGRESS', 'CONFIRMED', 'PENDING')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prev_month', $prevMonth, PDO::PARAM_STR);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total de citas del mes (todos los estados).
     * @param string $month Formato Y-m
     */
    public function getMonthAppointmentCount(string $month): int
    {
        $sql = "SELECT COUNT(a.appointment_id)
                FROM appointment a
                WHERE DATE_FORMAT(a.scheduled_timestamp, '%Y-%m') = :month";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Citas completadas del mes (para calcular ocupación).
     * @param string $month Formato Y-m
     */
    public function getMonthCompletedAppointmentCount(string $month): int
    {
        $sql = "SELECT COUNT(a.appointment_id)
                FROM appointment a
                WHERE DATE_FORMAT(a.scheduled_timestamp, '%Y-%m') = :month
                  AND a.appointment_status IN ('COMPLETED', 'IN_PROGRESS', 'CONFIRMED')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Clientes activos: cantidad de perfiles de cliente con cuenta ACTIVE.
     */
    public function getActiveClientCount(): int
    {
        try {
            $sql = "SELECT COUNT(DISTINCT u.user_id)
                    FROM user u
                    INNER JOIN user_role ur ON u.user_id = ur.user_id
                    INNER JOIN role r ON ur.role_id = r.role_id
                    WHERE u.account_status = 'ACTIVE' AND r.role_code = 'CLIENT'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Fallback si las tablas de roles aún no están pobladas
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM user WHERE account_status = 'ACTIVE'");
            return (int) $stmt->fetchColumn();
        }
    }

    /**
     * Clientes recurrentes: clientes con 2+ citas completadas o confirmadas.
     */
    public function getRecurringClientCount(): int
    {
        $sql = "SELECT COUNT(DISTINCT a.client_profile_id)
                FROM appointment a
                WHERE a.appointment_status IN ('COMPLETED', 'CONFIRMED')
                GROUP BY a.client_profile_id
                HAVING COUNT(a.appointment_id) >= 2";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return count($rows);
    }

    /**
     * Calificación promedio de todas las reseñas (service_rating).
     */
    public function getAverageRating(): float
    {
        $sql = "SELECT COALESCE(AVG(sr.score), 0.00) FROM service_rating sr";
        $stmt = $this->pdo->query($sql);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total de reseñas registradas.
     */
    public function getTotalReviews(): int
    {
        $sql = "SELECT COUNT(sr.rating_id) FROM service_rating sr";
        $stmt = $this->pdo->query($sql);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Citas de hoy con datos hidratados para la tabla del dashboard.
     * @param string $today Formato Y-m-d (ej: 2026-06-18)
     */
    public function getTodayAppointments(string $today): array
    {
        $sql = "SELECT
                    a.appointment_id,
                    a.scheduled_timestamp,
                    a.appointment_status,
                    CONCAT(u_cli.first_name, ' ', u_cli.last_name) as client_name,
                    s.name as service_name,
                    CONCAT(u_prof.first_name, ' ', u_prof.last_name) as professional_name
                FROM appointment a
                LEFT JOIN user u_cli ON a.client_profile_id = u_cli.user_id
                LEFT JOIN professional_profile pp ON a.professional_profile_id = pp.professional_profile_id
                LEFT JOIN user u_prof ON pp.professional_profile_id = u_prof.user_id
                LEFT JOIN service s ON a.service_id = s.service_id
                WHERE DATE(a.scheduled_timestamp) = :today
                ORDER BY a.scheduled_timestamp ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Métodos para analíticas avanzadas ────────────────────────────────

    /**
     * Obtiene KPIs principales: Ingresos, Citas totales, Clientes únicos y No-shows.
     * Skill 10: bindValue para el rango de fechas.
     */
    public function getMetrics(string $period = 'month'): array
    {
        $startDate = $this->calculateStartDate($period);

        $sql = "SELECT
                    COALESCE(SUM(final_price), 0) as revenue,
                    COUNT(appointment_id) as total_appointments,
                    COUNT(DISTINCT client_profile_id) as unique_clients,
                    SUM(CASE WHEN appointment_status = 'NOSHOW' THEN 1 ELSE 0 END) as noshow_count,
                    SUM(CASE WHEN appointment_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_count
                FROM appointment
                WHERE scheduled_timestamp >= :start";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start', $startDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Consulta detallada de citas para hoy con hidratación eficiente (Skill 12).
     */
    public function getAppointmentsToday(): array
    {
        return $this->getTodayAppointments(
            (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d')
        );
    }

    /**
     * Agregación de citas por categoría para gráficos de torta/donas.
     */
    public function getAppointmentsByCategory(): array
    {
        $sql = "SELECT sc.name as category, COUNT(a.appointment_id) as count
                FROM appointment a
                JOIN service s ON a.service_id = s.service_id
                JOIN service_category sc ON s.category_id = sc.category_id
                GROUP BY sc.category_id
                ORDER BY count DESC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado de servicios más solicitados (Top 5).
     */
    public function getTopServices(): array
    {
        $sql = "SELECT s.name, COUNT(a.appointment_id) as total_uses, SUM(a.final_price) as revenue
                FROM appointment a
                JOIN service s ON a.service_id = s.service_id
                WHERE a.appointment_status = 'COMPLETED'
                GROUP BY s.service_id
                ORDER BY total_uses DESC
                LIMIT 5";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Métricas de rendimiento por profesional.
     */
    public function getProfessionalPerformance(): array
    {
        $sql = "SELECT
                    u.first_name, u.last_name,
                    COUNT(a.appointment_id) as completed_appointments,
                    COALESCE(SUM(a.final_price), 0) as generated_revenue
                FROM professional_profile pp
                JOIN user u ON pp.professional_profile_id = u.user_id
                LEFT JOIN appointment a ON pp.professional_profile_id = a.professional_profile_id
                    AND a.appointment_status = 'COMPLETED'
                GROUP BY pp.professional_profile_id
                ORDER BY generated_revenue DESC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desglose de ingresos por método de pago.
     */
    public function getPaymentsByMethod(): array
    {
        $sql = "SELECT pm.method_code as method, SUM(ip.amount_paid) as total
                FROM invoice_payment ip
                JOIN payment_method pm ON ip.method_id = pm.method_id
                GROUP BY pm.method_id";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Helper para calcular fecha de inicio inmutable (Skill 7).
     */
    private function calculateStartDate(string $period): string
    {
        $tz = new \DateTimeZone('America/Bogota');
        $now = new \DateTimeImmutable('now', $tz);

        $date = match ($period) {
            'today' => $now->modify('00:00:00'),
            'week'  => $now->modify('-7 days'),
            'year'  => $now->modify('first day of january this year'),
            default => $now->modify('first day of this month'), // 'month'
        };

        return $date->format('Y-m-d H:i:s');
    }
}