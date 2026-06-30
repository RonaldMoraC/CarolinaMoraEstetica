<?php declare(strict_types=1);

namespace App\Infrastructure\Booking\Persistence;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\ConflictException;
use App\Domain\Shared\Exceptions\DomainException;
use PDO;
use PDOException;

/**
 * PdoAppointmentRepository — Implementación de Infraestructura
 *
 * Persiste y recupera citas contra MySQL usando PDO con sentencias preparadas.
 * sentencias preparadas (Skill 10).
 */
final class PdoAppointmentRepository implements AppointmentRepositoryInterface // Implementa la interfaz
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @inheritDoc
     */
    public function findById(int $appointmentId): ?array
    { // Modificado para retornar array
        $sql = "SELECT a.*, s.name AS service_name, s.base_price AS service_price,
                       u.first_name, u.last_name, pp.public_biography,
                       b.branch_name
                FROM appointment a
                LEFT JOIN service s ON s.service_id IN (
                    SELECT ps.service_id FROM professional_service ps
                    WHERE ps.professional_profile_id = a.professional_profile_id
                    LIMIT 1
                )
                LEFT JOIN user u ON u.user_id = a.client_profile_id
                LEFT JOIN professional_profile pp ON pp.professional_profile_id = a.professional_profile_id
                LEFT JOIN branch b ON b.branch_id = a.branch_id
                WHERE a.appointment_id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $appointmentId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrateToArray($row) : null; // Usa hydrateToArray
    }

    /**
     * @inheritDoc
     */
    public function getAppointmentsToday(): array
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d');
        return $this->getAppointmentsForDateRange($today, $today);
    }

    public function findByClientId(int $clientProfileId): array
    {
        $sql = "SELECT a.*, s.name AS service_name, s.base_price AS service_price,
                       s.duration_minutes, sc.name AS category_name,
                       pp.public_biography,
                       b.branch_name
                FROM appointment a
                LEFT JOIN professional_service ps ON ps.professional_profile_id = a.professional_profile_id
                LEFT JOIN service s ON s.service_id = ps.service_id
                LEFT JOIN service_category sc ON sc.category_id = s.category_id
                LEFT JOIN professional_profile pp ON pp.professional_profile_id = a.professional_profile_id
                LEFT JOIN branch b ON b.branch_id = a.branch_id
                WHERE a.client_profile_id = :clientId
                ORDER BY a.scheduled_timestamp DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clientId', $clientProfileId, PDO::PARAM_INT);
        $stmt->execute();

        $appointments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $appointments[] = $this->hydrateToArray($row);
        }

        return $appointments;
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO appointment (
                    client_profile_id, professional_profile_id, branch_id,
                    promotion_id, scheduled_timestamp, estimated_end_timestamp,
                    appointment_status, total_price, final_price, notes
                ) VALUES (
                    :clientId, :professionalId, :branchId,
                    :promotionId, :scheduledTs, :estimatedEndTs,
                    :status, :totalPrice, :finalPrice, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clientId', $data['client_profile_id'], PDO::PARAM_INT);
        $stmt->bindValue(':professionalId', $data['professional_profile_id'], PDO::PARAM_INT);
        $stmt->bindValue(':branchId', $data['branch_id'], PDO::PARAM_INT);
        $stmt->bindValue(':promotionId', $data['promotion_id'] ?? null, $data['promotion_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':scheduledTs', $data['scheduled_timestamp'], PDO::PARAM_STR);
        $stmt->bindValue(':estimatedEndTs', $data['estimated_end_timestamp'], PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['appointment_status'], PDO::PARAM_STR);
        $stmt->bindValue(':totalPrice', $data['total_price'], PDO::PARAM_STR);
        $stmt->bindValue(':finalPrice', $data['final_price'], PDO::PARAM_STR);
        $stmt->bindValue(':notes', $data['notes'] ?? null, $data['notes'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();
        return $id;
    }

    /**
     * @inheritDoc
     */
    public function updateStatus(int $appointmentId, string $newStatus, int $actorId, string $reason): bool
    {
        try {
            // Skill 2: Nivel de aislamiento para consistencia total
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();

            // Skill 2: Verificación de existencia con bloqueo pesimista (FOR UPDATE)
            $checkSql = "SELECT appointment_status FROM appointment WHERE appointment_id = :id FOR UPDATE";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->bindValue(':id', $appointmentId, PDO::PARAM_INT);
            $checkStmt->execute();
            $previousStatus = $checkStmt->fetchColumn();

            if ($previousStatus === false) {
                $this->pdo->rollBack();
                return false; // Cita no encontrada
            }

            // Actualizar estado en appointment
            $updateSql = "UPDATE appointment SET appointment_status = :status, updated_at = NOW() WHERE appointment_id = :id";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->bindValue(':status', $newStatus, PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $appointmentId, PDO::PARAM_INT);
            $updateStmt->execute();

            // Skill 9: Registrar en appointment_history (audit trail)
            $historySql = "INSERT INTO appointment_history (
                                appointment_id, changed_by_user_id, previous_status, new_status, change_reason
                            ) VALUES (
                                :appointmentId, :userId, :prevStatus, :newStatus, :reason
                            )";
            $historyStmt = $this->pdo->prepare($historySql);
            $historyStmt->bindValue(':appointmentId', $appointmentId, PDO::PARAM_INT);
            $historyStmt->bindValue(':userId', $actorId, PDO::PARAM_INT);
            $historyStmt->bindValue(':prevStatus', $previousStatus, PDO::PARAM_STR);
            $historyStmt->bindValue(':newStatus', $newStatus, PDO::PARAM_STR);
            $historyStmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $historyStmt->execute();

            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Skill 2: Captura específica de bloqueos (Deadlock o Lock Wait Timeout)
            if ($e->getCode() === '40001' || $e->getCode() === 'HY000') {
                throw new ConflictException("El recurso está bloqueado temporalmente por otra operación administrativa.", 409);
            }
            throw $e; // Re-lanzar otras excepciones PDO
        }
    }

    /**
     * @inheritDoc
     */
    public function checkIn(int $appointmentId, int $actorId): bool
    {
        return $this->updateStatus($appointmentId, 'IN_PROGRESS', $actorId, 'Check-in realizado por recepción.');
    }

    /**
     * @inheritDoc
     */
    public function complete(int $appointmentId, int $actorId): bool
    {
        return $this->updateStatus($appointmentId, 'COMPLETED', $actorId, 'Servicio finalizado por profesional.');
    }

    /**
     * @inheritDoc
     */
    public function markNoShow(int $appointmentId, int $actorId, string $reason): bool
    {
        return $this->updateStatus($appointmentId, 'NOSHOW', $actorId, $reason);
    }

    /**
     * @inheritDoc
     */
    public function cancel(int $appointmentId, int $actorId, string $reason): bool
    {
        return $this->updateStatus($appointmentId, 'CANCELLED', $actorId, $reason);
    }

    /**
     * @inheritDoc
     */
    public function getAppointmentsForDateRange(string $startDate, string $endDate, ?int $professionalId = null): array
    {
        $sql = "SELECT
                    a.appointment_id,
                    a.scheduled_timestamp,
                    a.estimated_end_timestamp,
                    a.appointment_status,
                    a.total_price,
                    a.final_price,
                    s.name AS service_name,
                    sc.name AS service_category,
                    u_cli.first_name AS client_first_name,
                    u_cli.last_name AS client_last_name,
                    u_prof.first_name AS professional_first_name,
                    u_prof.last_name AS professional_last_name,
                    b.branch_name
                FROM appointment a
                JOIN service s ON s.service_id = a.service_id
                JOIN service_category sc ON sc.category_id = s.category_id
                JOIN user u_cli ON u_cli.user_id = a.client_profile_id
                JOIN professional_profile pprof ON pprof.professional_profile_id = a.professional_profile_id
                JOIN user u_prof ON u_prof.user_id = pprof.professional_profile_id
                JOIN branch b ON b.branch_id = a.branch_id
                WHERE a.scheduled_timestamp BETWEEN :startDate AND :endDate";

        if ($professionalId !== null) {
            $sql .= " AND a.professional_profile_id = :professionalId";
        }

        $sql .= " ORDER BY a.scheduled_timestamp ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':startDate', $startDate . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate . ' 23:59:59', PDO::PARAM_STR);
        if ($professionalId !== null) {
            $stmt->bindValue(':professionalId', $professionalId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $appointments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $appointments[] = [
                'appointment_id' => (int) $row['appointment_id'],
                'scheduled_timestamp' => $row['scheduled_timestamp'],
                'estimated_end_timestamp' => $row['estimated_end_timestamp'],
                'appointment_status' => $row['appointment_status'],
                'total_price' => (float) $row['total_price'],
                'final_price' => (float) $row['final_price'],
                'service_name' => $row['service_name'],
                'service_category' => $row['service_category'],
                'client_name' => $row['client_first_name'] . ' ' . $row['client_last_name'],
                'professional_name' => $row['professional_first_name'] . ' ' . $row['professional_last_name'],
                'branch_name' => $row['branch_name'],
            ];
        }
        return $appointments;
    }

    /**
     * Convierte un row de DB a array plano para API responses (sin hidratar entidad).
     */
    private function hydrateToArray(array $row): array
    {
        // Ensure scheduled_timestamp is a string before passing to DateTimeImmutable
        $scheduledTimestamp = (string) $row['scheduled_timestamp'];
        $scheduled = new \DateTimeImmutable($scheduledTimestamp, new \DateTimeZone('America/Bogota'));

        return [
            'id'                 => (int) $row['appointment_id'],
            'service_name'       => $row['service_name'] ?? 'Servicio',
            'category'           => $row['category_name'] ?? '',
            'date'               => $scheduled->format('Y-m-d'),
            'time'               => $scheduled->format('H:i'),
            'fecha_formateada'   => $scheduled->format('d \d\e F \d\e Y'),
            'status'             => strtolower($row['appointment_status']),
            'estado'             => $row['appointment_status'],
            'price'              => (float) ($row['final_price'] ?? $row['total_price'] ?? 0),
            'precio'             => (float) ($row['total_price'] ?? 0),
            'branch_name'        => $row['branch_name'] ?? '',
            'notes'              => $row['notes'],
            'professional_bio'   => $row['public_biography'] ?? '',
        ];
    }

    // La función hydrateEntity ya no es necesaria si findById retorna array.
    // Si otras partes del sistema la necesitan, se podría mover a un Factory.
    // private function hydrateEntity(array $row): Appointment { ... }
}
