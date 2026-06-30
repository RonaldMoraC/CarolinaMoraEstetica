<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Repositories;

use App\Domain\Staffing\Entities\ScheduleException;
use App\Domain\Staffing\Entities\WorkSchedule;

/**
 * StaffingRepositoryInterface
 *
 * Contrato para acceder a la persistencia del módulo de Staffing.
 * Desacopla la lógica de base de datos de los Casos de Uso.
 */
interface StaffingRepositoryInterface
{
    /**
     * Guarda (inserta o actualiza) un bloque de malla horaria.
     */
    public function saveWorkSchedule(WorkSchedule $schedule): void;

    /**
     * Elimina todos los bloques horarios de un profesional para una sucursal dada.
     * Útil antes de reescribir la malla semanal completa.
     */
    public function deleteWorkSchedules(int $professionalId, int $branchId): void;

    /**
     * Obtiene la malla horaria semanal de un profesional en una sucursal.
     *
     * @return WorkSchedule[]
     */
    public function getWorkSchedules(int $professionalId, int $branchId): array;

    /**
     * Guarda una nueva excepción de agenda.
     */
    public function saveScheduleException(ScheduleException $exception): void;

    /**
     * Obtiene excepciones de agenda que se solapen con el rango de tiempo dado.
     *
     * @param int    $professionalId El profesional a revisar.
     * @param string $startTimestamp Fecha/Hora inicio 'Y-m-d H:i:s' en America/Bogota.
     * @param string $endTimestamp   Fecha/Hora fin 'Y-m-d H:i:s' en America/Bogota.
     * @return ScheduleException[]
     */
    public function getExceptionsInTimeRange(int $professionalId, string $startTimestamp, string $endTimestamp): array;

    /**
     * Busca un perfil profesional por su ID.
     */
    public function findProfessionalProfileById(int $professionalId): ?array;

    /**
     * Obtiene los servicios que un profesional está habilitado para realizar.
     */
    public function getProfessionalServices(int $professionalId): array;

    /**
     * Sincroniza (reemplaza) los servicios asociados a un profesional.
     * Debe ejecutarse de forma atómica.
     */
    public function syncProfessionalServices(int $professionalId, array $serviceIds): bool;

    /**
     * Obtiene los profesionales habilitados para un servicio específico.
     */
    public function findProfessionalsByService(int $serviceId): array;

    /**
     * Obtiene todos los profesionales activos con sus nombres.
     *
     * @return list<array{professional_profile_id: int, first_name: string, last_name: string, email: string, operational_status: string}>
     */
    public function findAllProfessionals(): array;
}
