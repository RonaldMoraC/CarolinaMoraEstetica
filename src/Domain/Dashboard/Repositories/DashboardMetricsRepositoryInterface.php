<?php
declare(strict_types=1);

namespace App\Domain\Dashboard\Repositories;

/**
 * DashboardMetricsRepositoryInterface
 * Contrato para la obtención de métricas del negocio.
 */
interface DashboardMetricsRepositoryInterface
{
    public function getMonthRevenue(string $month): float;
    public function getPreviousMonthRevenue(string $prevMonth): float;
    public function getMonthAppointmentCount(string $month): int;
    public function getMonthCompletedAppointmentCount(string $month): int;
    public function getActiveClientCount(): int;
    public function getRecurringClientCount(): int;
    public function getAverageRating(): float;
    public function getTotalReviews(): int;
    public function getTodayAppointments(string $today): array;
}