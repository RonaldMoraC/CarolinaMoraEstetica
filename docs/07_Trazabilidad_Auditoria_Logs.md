Este componente técnico establece el diseño estructural, el esquema de datos y la estrategia de persistencia para el sistema de Auditoría Forense e Inmutable de Antigravity 2.0. En un entorno de producción sobre servidores compartidos o dedicados, este módulo garantiza el cumplimiento de los Requerimientos No Funcionales de seguridad (RNF-03), la trazabilidad completa de datos y el registro histórico de accesos para todos los canales de interacción (PWA, App Móvil y Bot de WhatsApp).

1. Estrategia y Clasificación de Eventos (Estándar PSR-3)
Para evitar la saturación del almacenamiento del servidor y mantener un rendimiento óptimo en las consultas de base de datos, el sistema clasifica las trazas y logs en cuatro niveles de severidad e impacto técnico, inspirados en la interfaz de logging PSR-3:

Plaintext
┌─────────────────────────────────────────────────────────────────────────┐
│                          SEVERIDAD DE LOGS                              │
├──────────────┬──────────────────────────────────────────────────────────┤
│ 🔴 CRITICAL  │ Fallos de infraestructura: Base de datos caída, API Meta │
│              │ inaccesible, errores 500 no controlados.                 │
├──────────────┼──────────────────────────────────────────────────────────┤
│ 🟠 SECURITY  │ Violaciones perimetrales: JWT expirados/manipulados,    │
│              │ bloqueos por Rate Limiting, denegación RBAC (403).       │
├──────────────┼──────────────────────────────────────────────────────────┤
│ 🔵 AUDIT     │ Mutaciones de datos de negocio: Creación, modificación o  │
│              │ cancelación de citas, pagos y cierres de caja.          │
├──────────────┼──────────────────────────────────────────────────────────┤
│ 🟢 INFO      │ Eventos operativos: Inicio de sesión exitoso, consultas  │
│              │ de catálogos, lectura de perfiles de clientes.          │
└──────────────┴──────────────────────────────────────────────────────────┘
CRITICAL (Errores Críticos del Sistema): Eventos que detienen el flujo de negocio. Deben enviarse a archivos planos del sistema y, opcionalmente, disparar alertas inmediatas.

SECURITY (Eventos de Seguridad): Intentos de intrusión o bloqueos del cortafuegos de la API. Indispensables para análisis de vulnerabilidades y auditorías de seguridad OWASP.

AUDIT (Auditoría de Datos): Cambios de estado en los recursos del negocio. Se almacenan con un enfoque estructurado (JSON) para reconstruir el historial exacto de cualquier registro.

INFO (Información General): Trazas informativas del ciclo de vida de las solicitudes. Útiles para telemetría básica y depuración.

2. Diseño Físico de Tablas de Auditoría
Para asegurar que el rendimiento de la API no se degrade bajo condiciones de alta concurrencia (como reservas masivas concurrentes desde la PWA o el Bot de WhatsApp), el almacenamiento de auditoría se divide en dos tablas relacionales especializadas y optimizadas mediante índices compuestos.

2.1. Tabla audit_access_log (Registro de Accesos y Autenticación)
Esta tabla registra de forma cronológica cada intento de autenticación, renovación de token o denegación perimetral.

SQL
CREATE TABLE IF NOT EXISTS `audit_access_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `correlation_id` VARCHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NULL, -- NULL si es un intento fallido con usuario inexistente
    `channel` ENUM('PWA', 'MOBILE_APP', 'WHATSAPP_BOT') NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL, -- Soporta IPv4 e IPv6
    `user_agent` VARCHAR(255) NULL,
    `event_type` VARCHAR(50) NOT NULL, -- LOGIN_SUCCESS, LOGIN_FAILED, TOKEN_REFRESH, RBAC_DENIED
    `severity` ENUM('INFO', 'SECURITY', 'CRITICAL') NOT NULL,
    `details` TEXT NULL, -- Descripción textual extendida o payload de error resumido
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_access_correlation` (`correlation_id`),
    INDEX `idx_access_user_event` (`user_id`, `event_type`),
    INDEX `idx_access_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
2.2. Tabla audit_data_log (Historial Inmutable de Cambios de Datos)
Esta tabla almacena las mutaciones relacionales del negocio (INSERT, UPDATE, DELETE), guardando de forma estructurada los estados previos y posteriores del registro afectado.

SQL
CREATE TABLE IF NOT EXISTS `audit_data_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `correlation_id` VARCHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NULL, -- Identificador del operador (actor que realiza el cambio)
    `table_name` VARCHAR(50) NOT NULL, -- appointment, payment, client_profile, etc.
    `record_id` INT UNSIGNED NOT NULL, -- ID numérico del registro modificado en la tabla origen
    `action` ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
    `payload_before` JSON NULL, -- Estado del registro antes del cambio (NULL en CREATE)
    `payload_after` JSON NULL,  -- Estado del registro después del cambio (NULL en DELETE)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_data_correlation` (`correlation_id`),
    INDEX `idx_data_target` (`table_name`, `record_id`),
    INDEX `idx_data_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
3. Mecanismo de Inmutabilidad Rigurosa (Append-Only)
Un sistema de auditoría pierde su validez legal y forense si un usuario con altos privilegios (como un SUPER_ADMIN o un atacante que haya comprometido las credenciales del servidor) puede alterar o borrar los registros de auditoría para ocultar actividades maliciosas.

Para garantizar la integridad y la persistencia de tipo Append-Only (solo inserción) en la base de datos MySQL, se configuran dos capas de protección concurrentes:

3.1. Protección por Capa de Permisos de Base de Datos
En el entorno de producción (Hostinger/Servidor Dedicado), las credenciales de conexión utilizadas por la API de PHP Puro (DB_USER) deben tener restringidos los privilegios sobre las tablas de auditoría.

El usuario de la base de datos de la API tendrá permisos completos (SELECT, INSERT, UPDATE, DELETE) en las tablas de negocio como appointment, payment, etc.

El mismo usuario tendrá estrictamente prohibidos los comandos UPDATE y DELETE sobre las tablas audit_data_log y audit_access_log. Solo dispondrá de permisos SELECT e INSERT.

3.2. Protección por Triggers Internos (Respaldo en el Motor MySQL)
Como salvaguarda ante cualquier elevación de privilegios o ejecución accidental de scripts, se inyectan disparadores (triggers) directamente en el motor de base de datos MySQL para abortar de forma automática cualquier intento de alteración o borrado físico:

SQL
DELIMITER $$

-- Bloqueo absoluto de modificaciones en el log de datos
CREATE TRIGGER `tg_prevent_update_data_log` 
BEFORE UPDATE ON `audit_data_log`
FOR EACH ROW 
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Operación prohibida: Los registros de auditoría de datos son inmutables.';
END$$

-- Bloqueo absoluto de eliminaciones en el log de datos
CREATE TRIGGER `tg_prevent_delete_data_log` 
BEFORE DELETE ON `audit_data_log`
FOR EACH ROW 
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Operación prohibida: Los registros de auditoría de datos son inmutables y no pueden eliminarse.';
END$$

-- Bloqueo absoluto de modificaciones en el log de accesos
CREATE TRIGGER `tg_prevent_update_access_log` 
BEFORE UPDATE ON `audit_access_log`
FOR EACH ROW 
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Operación prohibida: Los registros de auditoría de accesos son inmutables.';
END$$

-- Bloqueo absoluto de eliminaciones en el log de accesos
CREATE TRIGGER `tg_prevent_delete_access_log` 
BEFORE DELETE ON `audit_access_log`
FOR EACH ROW 
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Operación prohibida: Los registros de auditoría de accesos son inmutables y no pueden eliminarse.';
END$$

DELIMITER ;
Con esta base de infraestructura e inmutabilidad establecida, la arquitectura queda lista para la persistencia de registros de auditoría a nivel del motor de base de datos.

4. Generador de Diferenciales (Diff Generator) en PHP Puro
Para registrar de manera eficiente qué datos cambiaron en un evento UPDATE, el backend de Antigravity 2.0 no debe almacenar duplicados exactos de filas idénticas. En su lugar, el componente de infraestructura calcula dinámicamente un diferencial (diff) comparando el estado previo del dominio con el estado posterior.

Este servicio se integra directamente en la capa de persistencia mediante el siguiente componente desacoplado (src/Infrastructure/Shared/Errors/DataAuditLogger.php):

PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Errors;

use \PDO;

class DataAuditLogger {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Registra una mutación física de datos en la tabla audit_data_log.
     */
    public function logChange(
        string $correlationId,
        ?int $userId,
        string $tableName,
        int $recordId,
        string $action,
        ?array $before,
        ?array $after
    ): void {
        $payloadBefore = null;
        $payloadAfter = null;

        if ($action === 'UPDATE' && $before !== null && $after !== null) {
            // Filtrar y almacenar exclusivamente los campos que mutaron
            $diff = $this->calculateDiff($before, $after);
            if (empty($diff['before']) && empty($diff['after'])) {
                return; // No hubo cambios reales en los datos, abortar inserción en log
            }
            $payloadBefore = json_encode($diff['before']);
            $payloadAfter = json_encode($diff['after']);
        } else {
            // Para operaciones CREATE o DELETE, serializar el snapshot completo disponible
            $payloadBefore = $before ? json_encode($before) : null;
            $payloadAfter = $after ? json_encode($after) : null;
        }

        $sql = "INSERT INTO audit_data_log (correlation_id, user_id, table_name, record_id, action, payload_before, payload_after) 
                VALUES (:correlation_id, :user_id, :table_name, :record_id, :action, :payload_before, :payload_after)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'correlation_id' => $correlationId,
            'user_id'        => $userId,
            'table_name'     => $tableName,
            'record_id'      => $recordId,
            'action'         => $action,
            'payload_before' => $payloadBefore,
            'payload_after'  => $payloadAfter
        ]);
    }

    /**
     * Compara dos arreglos asociativos y extrae únicamente los valores divergentes.
     */
    private function calculateDiff(array $before, array $after): array {
        $diffBefore = [];
        $diffAfter = [];

        foreach ($after as $key => $value) {
            if (array_key_exists($key, $before)) {
                if ($before[$key] !== $value) {
                    $diffBefore[$key] = $before[$key];
                    $diffAfter[$key] = $value;
                }
            }
        }

        return [
            'before' => $diffBefore,
            'after'  => $diffAfter
        ];
    }
}
5. Trazabilidad Omnicanal: Propagación del Identificador de Correlación
Para enlazar de forma inequívoca el rastro de una operación que atraviesa múltiples capas del sistema (desde que ingresa por la API HTTP hasta que impacta la base de datos o se encola una alerta saliente), el sistema implementa un token de rastreo único denominado Correlation ID.

5.1. Protocolo de Inyección y Extracción de Cabeceras
Generación Perimetral: Cada vez que la PWA, la App móvil o el Webhook de Meta Cloud API realizan una petición HTTP hacia el backend, el sistema verifica la existencia de la cabecera estándar X-Correlation-ID. Si no está presente, el controlador inicializador de la infraestructura genera de manera inmediata un identificador único global utilizando un algoritmo UUIDv4 seguro en PHP Puro:

PHP
$correlationId = bin2hex(random_bytes(16)); // Generación de ID pseudo-UUIDv4 de alta entropía
2. **Propagación en las Capas:** Este identificador es inyectado en el objeto de contexto global de la solicitud y viaja de manera obligatoria como parámetro en la ejecución de cualquier caso de uso y repositorio.
3. **Respuesta Espejo:** El backend retornará siempre la misma cabecera `X-Correlation-ID` en el ciclo final de la respuesta HTTP devuelta al cliente, lo que permite que ante un error en producción, el usuario final (o la recepcionista en mostrador) pueda reportar dicho ID para una auditoría forense inmediata.

---

## 6. Rotación y Almacenamiento en Archivos Planos Protegidos

Como mecanismo de respaldo redundante ante incidentes críticos (como saturación del motor de base de datos o ataques de inyección SQL destructivos), los eventos catalogados bajo las severidades `SECURITY` y `CRITICAL` se escribirán de manera paralela en el almacenamiento físico en disco del servidor web mediante archivos planos de texto enriquecido.

### 6.1. Formato Estructurado del Archivo Plano (`.log`)
Los eventos se almacenan en una sola línea de texto utilizando la codificación JSON estándar, facilitando su exportación posterior o análisis automatizado:

```text
{"timestamp":"2026-06-06T14:34:56.123Z","correlation_id":"4f7b2a19-8c3d-4e2a-9f1b-5a6b7c8d9e0f","severity":"SECURITY","event_type":"RBAC_VIOLATION","user_id":102,"ip":"190.24.115.8","details":"Intento no autorizado de acceso al endpoint /cash-desk/closing sin permisos suficientes."}
{"timestamp":"2026-06-06T14:35:10.456Z","correlation_id":"d9f8e7d6-c5b4-a3f2-e1d0-c9b8a7f6e5d4","severity":"CRITICAL","event_type":"DATABASE_TIMEOUT","user_id":null,"ip":"127.0.0.1","details":"Error de conexión PDO: SQLSTATE[HY000] [2002] Connection timed out en el servidor compartido Hostinger."}
6.2. Reglas de Rotación Automatizada y Seguridad Perimetral
Para evitar el desbordamiento del almacenamiento físico asignado en la infraestructura compartida, el motor de infraestructura ejecutará las siguientes validaciones operativas basadas en restricciones de archivos nativos de PHP:

Restricción de Tamaño Máximo: Los archivos activos de logs físicos se denominarán bajo el patrón app-security.log y app-critical.log. Cuando el tamaño del archivo en disco exceda los 5 MB, el sistema disparará automáticamente un proceso de rotación interno:

PHP
if (filesize($logPath) > 5 * 1024 * 1024) {
    rename($logPath, dirname($logPath) . '/app-security-' . date('Y-m-d-His') . '.log');
}
2. **Blindaje de Acceso por Apache:** Queda estrictamente prohibido que estos archivos sean accesibles de manera pública mediante solicitudes HTTP desde navegadores web. Para garantizar la confidencialidad forense completa, el directorio `src/Infrastructure/Shared/Errors/logs/` contendrá de forma obligatoria un archivo `.htaccess` interno con una directiva restrictiva absoluta:
   ```apache
# Denegar por completo el acceso web directo a los archivos de bitácora Require all denied