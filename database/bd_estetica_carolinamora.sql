-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.13.0.7147
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para estetica_carolinamora
CREATE DATABASE IF NOT EXISTS `estetica_carolinamora` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `estetica_carolinamora`;

-- Volcando estructura para tabla estetica_carolinamora.appointment
CREATE TABLE IF NOT EXISTS `appointment` (
  `appointment_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria transaccional del registro de la cita.',
  `client_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que conecta la cita con el perfil del cliente receptor.',
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que conecta con el especialista asignado a ejecutar los servicios.',
  `branch_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea que establece el establecimiento físico de la reserva.',
  `promotion_id` int(10) unsigned DEFAULT NULL COMMENT 'Llave foránea opcional que vincula la campaña de descuento aplicada a la cita.',
  `scheduled_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora exacta pactada para el inicio de la atención.',
  `estimated_end_timestamp` timestamp NOT NULL DEFAULT '1970-01-01 10:00:01' COMMENT 'Fecha y hora calculada dinámicamente de la finalización total, sumando duraciones del desglose.',
  `appointment_status` enum('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NOSHOW') NOT NULL DEFAULT 'PENDING' COMMENT 'Estado del ciclo de vida de la reserva controlado por la máquina de estados de negocio.',
  `total_price` decimal(10,2) NOT NULL COMMENT 'Suma total de los precios base regulares de los servicios asignados antes de descuentos.',
  `final_price` decimal(10,2) NOT NULL COMMENT 'Importe neto final calculado a cobrar tras la aplicación de promociones y cupones.',
  `notes` text DEFAULT NULL COMMENT 'Anotaciones puntuales, observaciones especiales o indicaciones específicas provistas para el servicio.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp de creación del ticket de la reserva en el sistema.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp del último cambio físico del estado o metadatos del registro.',
  PRIMARY KEY (`appointment_id`),
  KEY `fk_appointment_branch` (`branch_id`),
  KEY `fk_appointment_promotion` (`promotion_id`),
  KEY `idx_appointment_concurrency` (`professional_profile_id`,`scheduled_timestamp`,`estimated_end_timestamp`,`appointment_status`) COMMENT 'Índice maestro de alto rendimiento para el algoritmo anti-overbooking en tiempo de asignación.',
  KEY `idx_appointment_client_history` (`client_profile_id`,`scheduled_timestamp`) COMMENT 'Acelera la carga cronológica inversa del listado histórico de citas del usuario en la PWA.',
  CONSTRAINT `fk_appointment_branch` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`),
  CONSTRAINT `fk_appointment_client` FOREIGN KEY (`client_profile_id`) REFERENCES `client_profile` (`client_profile_id`),
  CONSTRAINT `fk_appointment_professional` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`),
  CONSTRAINT `fk_appointment_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`promotion_id`),
  CONSTRAINT `chk_appointment_time` CHECK (`estimated_end_timestamp` > `scheduled_timestamp`),
  CONSTRAINT `chk_appointment_prices` CHECK (`final_price` <= `total_price` and `final_price` >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Eje central transaccional del negocio. Coordina ventanas temporales entre clientes, sucursales y el staff.';

-- Volcando datos para la tabla estetica_carolinamora.appointment: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.appointment_history
CREATE TABLE IF NOT EXISTS `appointment_history` (
  `history_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del historial de auditoría de la cita.',
  `appointment_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada a la cita auditada.',
  `changed_by_user_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada al usuario responsable que gatilló el cambio de estado.',
  `previous_status` enum('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NOSHOW') DEFAULT NULL COMMENT 'Estado anterior de la cita previo a la mutación física.',
  `new_status` enum('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NOSHOW') NOT NULL COMMENT 'Nuevo estado consolidado en la máquina de estados.',
  `change_reason` varchar(255) DEFAULT NULL COMMENT 'Explicación del operador o sistema sobre el porqué de la transición (ej: Cancelación del cliente vía Bot).',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora exacta del cambio de estado.',
  PRIMARY KEY (`history_id`),
  KEY `fk_app_hist_user` (`changed_by_user_id`),
  KEY `idx_app_history_lookup` (`appointment_id`,`created_at`) COMMENT 'Optimiza la renderización de la línea de tiempo de auditoría en la vista del administrador.',
  CONSTRAINT `fk_app_hist_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_hist_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bitácora inmutable de auditoría para el ciclo de vida de la reserva, útil para analítica de embudos y lead times.';

-- Volcando datos para la tabla estetica_carolinamora.appointment_history: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.branch
CREATE TABLE IF NOT EXISTS `branch` (
  `branch_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria de la sucursal o sede física del negocio.',
  `branch_name` varchar(100) NOT NULL COMMENT 'Nombre comercial identificativo de la sede (ej: Sucursal Central, Sede Norte).',
  `physical_address` varchar(255) NOT NULL COMMENT 'Ubicación e infraestructura geográfica del local.',
  `contact_phone` varchar(20) NOT NULL COMMENT 'Teléfono base de atención e informes presenciales de la sucursal.',
  `cancellation_hours_notice` int(10) unsigned NOT NULL DEFAULT 24 COMMENT 'Parámetro de negocio: Horas mínimas de anticipación requeridas por la sede para cancelar una cita sin penalizaciones.',
  `autonomous_reschedule_limit` int(10) unsigned NOT NULL DEFAULT 2 COMMENT 'Parámetro de negocio: Cantidad máxima de reajustes automáticos permitidos al cliente desde la PWA.',
  `concurrency_lock_minutes` int(10) unsigned NOT NULL DEFAULT 15 COMMENT 'Parámetro técnico: Tiempo de bloqueo temporal del slot en minutos durante la selección de agenda en el Bot o PWA.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de alta de la sucursal en la red.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última modificación de parámetros de la sucursal.',
  PRIMARY KEY (`branch_id`),
  CONSTRAINT `chk_branch_cancellation_hours` CHECK (`cancellation_hours_notice` >= 1),
  CONSTRAINT `chk_branch_reschedule_limit` CHECK (`autonomous_reschedule_limit` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Estructura maestra organizacional y de configuración multisede de la franquicia de estética.';

-- Volcando datos para la tabla estetica_carolinamora.branch: ~1 rows (aproximadamente)
INSERT IGNORE INTO `branch` (`branch_id`, `branch_name`, `physical_address`, `contact_phone`, `cancellation_hours_notice`, `autonomous_reschedule_limit`, `concurrency_lock_minutes`, `created_at`, `updated_at`) VALUES
	(1, 'Carolina Mora Estetica y SPA', 'llorente barrio 30 de octubre', '3218915292', 24, 2, 15, '2026-06-14 12:01:05', '2026-06-14 12:01:05');

-- Volcando estructura para tabla estetica_carolinamora.cash_flow_transaction
CREATE TABLE IF NOT EXISTS `cash_flow_transaction` (
  `cash_flow_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del movimiento misceláneo de caja.',
  `session_id` bigint(20) unsigned NOT NULL COMMENT 'Turno de caja chica afectado por la transacción.',
  `transaction_type` enum('INFLOW_INVOICE','INFLOW_MANUAL','OUTFLOW_EXPENSE','OUTFLOW_WITHDRAWAL') NOT NULL COMMENT 'Naturaleza del movimiento de dinero.',
  `amount` decimal(10,2) NOT NULL COMMENT 'Magnitud monetaria del movimiento.',
  `concept` varchar(255) NOT NULL COMMENT 'Explicación del origen o destino (ej: Compra de insumos de estilismo, Pago de servicios públicos).',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp exacto del registro.',
  PRIMARY KEY (`cash_flow_id`),
  KEY `fk_cash_flow_session` (`session_id`),
  CONSTRAINT `fk_cash_flow_session` FOREIGN KEY (`session_id`) REFERENCES `cash_register_session` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_cash_flow_amount` CHECK (`amount` > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historial unificado de entradas y salidas de efectivo para auditorías de caja chica.';

-- Volcando datos para la tabla estetica_carolinamora.cash_flow_transaction: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.cash_register_session
CREATE TABLE IF NOT EXISTS `cash_register_session` (
  `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria de la sesión de caja chica diaria.',
  `branch_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea que vincula la caja a una sucursal específica.',
  `opened_by_user_id` bigint(20) unsigned NOT NULL COMMENT 'Usuario del staff (recepcionista/admin) que abre la caja.',
  `closed_by_user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Usuario del staff que realiza el cierre de caja y arqueo.',
  `opening_timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora exacta de apertura de caja.',
  `closing_timestamp` timestamp NOT NULL DEFAULT '1970-01-01 10:00:01' COMMENT 'Fecha y hora del cierre definitivo del turno.',
  `opening_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto base en efectivo con el que inicia el día para dar cambios.',
  `expected_closing_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto calculado por el sistema (Apertura + Ventas Efectivo - Egresos).',
  `actual_closing_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto físico real contado por el cajero durante el arqueo.',
  `cash_discrepancy` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Diferencia matemática entre lo esperado y lo real (Sobrante/Faltante).',
  `session_status` enum('OPEN','CLOSED','ARCHIVED') NOT NULL DEFAULT 'OPEN' COMMENT 'Estado del turno operativo de la caja.',
  `closure_notes` text DEFAULT NULL COMMENT 'Observaciones detalladas en caso de descuadres o incidencias.',
  PRIMARY KEY (`session_id`),
  KEY `fk_cash_session_opener` (`opened_by_user_id`),
  KEY `fk_cash_session_closer` (`closed_by_user_id`),
  KEY `idx_cash_session_lookup` (`branch_id`,`session_status`),
  CONSTRAINT `fk_cash_session_branch` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`),
  CONSTRAINT `fk_cash_session_closer` FOREIGN KEY (`closed_by_user_id`) REFERENCES `user` (`user_id`),
  CONSTRAINT `fk_cash_session_opener` FOREIGN KEY (`opened_by_user_id`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Control de flujo de efectivo en cajas físicas por turno y sucursal.';

-- Volcando datos para la tabla estetica_carolinamora.cash_register_session: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.client_profile
CREATE TABLE IF NOT EXISTS `client_profile` (
  `client_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave primaria compartida (1:1) que hereda de user.user_id.',
  `birth_date` date NOT NULL COMMENT 'Fecha de nacimiento para controles de marketing y tratamientos segmentados por edad.',
  `medical_notes_allergies` text DEFAULT NULL COMMENT 'Registro clínico obligatorio de alergias o patologías para el uso seguro de químicos y tintes estéticos.',
  `consumption_preferences` text DEFAULT NULL COMMENT 'Anotaciones comerciales sobre gustos del cliente para incentivar la retención en la PWA.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de alta del perfil comercial.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última edición del perfil.',
  PRIMARY KEY (`client_profile_id`),
  CONSTRAINT `fk_client_profile_user` FOREIGN KEY (`client_profile_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Extensión especializada uno a uno (1:1) de la tabla user encargada de los datos clínicos y comerciales del cliente.';

-- Volcando datos para la tabla estetica_carolinamora.client_profile: ~3 rows (aproximadamente)
INSERT IGNORE INTO `client_profile` (`client_profile_id`, `birth_date`, `medical_notes_allergies`, `consumption_preferences`, `created_at`, `updated_at`) VALUES
	(1, '1990-01-01', NULL, NULL, '2026-06-14 13:57:44', '2026-06-14 13:57:44'),
	(7, '2000-01-01', NULL, NULL, '2026-06-13 17:44:35', '2026-06-14 22:55:06'),
	(8, '1990-01-01', NULL, NULL, '2026-06-14 14:50:37', '2026-06-14 14:50:37');

-- Volcando estructura para tabla estetica_carolinamora.holiday
CREATE TABLE IF NOT EXISTS `holiday` (
  `holiday_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria autocorrelativa global de los días de cierre oficial de la agenda.',
  `branch_id` int(10) unsigned DEFAULT NULL COMMENT 'Llave foránea vinculada a branch. Si es NULL indica que aplica globalmente a toda la franquicia.',
  `holiday_date` date NOT NULL COMMENT 'Fecha exacta del día festivo o cierre comercial programado.',
  `description` varchar(150) NOT NULL COMMENT 'Nombre o motivo explicativo del asueto (ej: Año Nuevo, Mantenimiento General Sede).',
  `block_entire_network` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag booleano que determina si el bloqueo inhabilita la agenda de toda la red de sucursales en cascada.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp de la creación física del registro.',
  PRIMARY KEY (`holiday_id`),
  KEY `fk_holiday_branch` (`branch_id`),
  KEY `idx_holiday_lookup` (`holiday_date`,`branch_id`) COMMENT 'Optimiza el algoritmo de validación de días laborables en la PWA y el Bot.',
  CONSTRAINT `fk_holiday_branch` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_holiday_scope` CHECK (`block_entire_network` = 1 and `branch_id` is null or `block_entire_network` = 0 and `branch_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Días festivos, cierres oficiales y bloqueos institucionales de agenda tanto locales como globales.';

-- Volcando datos para la tabla estetica_carolinamora.holiday: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.invoice
CREATE TABLE IF NOT EXISTS `invoice` (
  `invoice_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del encabezado de la factura comercial.',
  `appointment_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada a la cita transaccional original.',
  `cash_session_id` bigint(20) unsigned NOT NULL COMMENT 'Asociación obligatoria al turno de caja activo para el arqueo.',
  `invoice_number` varchar(100) NOT NULL COMMENT 'Identificador único correlativo legal/fiscal de la factura corporativa.',
  `subtotal_amount` decimal(10,2) NOT NULL COMMENT 'Importe total de servicios antes de aplicar deducciones e impuestos.',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Reducción total consolidada obtenida de la tabla promotion.',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 16.00 COMMENT 'Porcentaje impositivo configurado según leyes fiscales locales (ej: IVA 16%).',
  `tax_amount` decimal(10,2) NOT NULL COMMENT 'Monto financiero derivado del cálculo del tax_rate sobre el subtotal neto.',
  `grand_total` decimal(10,2) NOT NULL COMMENT 'Monto neto final liquidado por el cliente (Subtotal - Descuento + Impuestos).',
  `billing_status` enum('UNPAID','PARTIALLY_PAID','PAID','REFUNDED','VOIDED') NOT NULL DEFAULT 'UNPAID' COMMENT 'Estado transaccional contable de la factura.',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora exacta de la emisión comercial.',
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  UNIQUE KEY `uq_invoice_appointment` (`appointment_id`),
  KEY `fk_invoice_cash_session` (`cash_session_id`),
  KEY `idx_invoice_reports` (`issued_at`,`billing_status`),
  CONSTRAINT `fk_invoice_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`),
  CONSTRAINT `fk_invoice_cash_session` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_register_session` (`session_id`),
  CONSTRAINT `chk_invoice_math` CHECK (`grand_total` >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Encabezado y consolidación contable de transacciones por servicios estéticos.';

-- Volcando datos para la tabla estetica_carolinamora.invoice: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.invoice_payment
CREATE TABLE IF NOT EXISTS `invoice_payment` (
  `payment_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del desglose de abonos recibidos.',
  `invoice_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que asocia el cobro a su respectiva factura.',
  `method_id` int(10) unsigned NOT NULL COMMENT 'Forma de pago utilizada para esta transacción parcial o total.',
  `amount_paid` decimal(10,2) NOT NULL COMMENT 'Magnitud del cobro procesado.',
  `gateway_transaction_reference` varchar(255) DEFAULT NULL COMMENT 'ID o hash de confirmación provisto por la pasarela (Stripe, MercadoPago, Banco).',
  `payment_timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha exacta en que impactó el dinero.',
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_invoice` (`invoice_id`),
  KEY `fk_payment_method` (`method_id`),
  CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_method` FOREIGN KEY (`method_id`) REFERENCES `payment_method` (`method_id`),
  CONSTRAINT `chk_payment_amount` CHECK (`amount_paid` > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Desglose de transacciones financieras. Soporta pagos híbridos (ej: mitad efectivo y mitad tarjeta).';

-- Volcando datos para la tabla estetica_carolinamora.invoice_payment: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.payment_method
CREATE TABLE IF NOT EXISTS `payment_method` (
  `method_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del catálogo de formas de pago.',
  `method_code` varchar(50) NOT NULL COMMENT 'Código único normalizado para lógica interna (CASH, CREDIT_CARD, DEBIT_CARD, TRANSFER, STRIPE).',
  `name` varchar(100) NOT NULL COMMENT 'Nombre legible del método de pago orientado al usuario.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Flag lógico de disponibilidad del método en el checkout.',
  `requires_verification` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Determina si el método requiere validación manual de un ticket de transferencia por recepción.',
  PRIMARY KEY (`method_id`),
  UNIQUE KEY `uq_payment_method_code` (`method_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo paramétrico de pasarelas y métodos de pago aprobados por la franquicia.';

-- Volcando datos para la tabla estetica_carolinamora.payment_method: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.permission
CREATE TABLE IF NOT EXISTS `permission` (
  `permission_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria correlativa del permiso atómico.',
  `permission_code` varchar(100) NOT NULL COMMENT 'Código técnico único asignado al endpoint o recurso de la API REST (ej: appointments:create, audit:view).',
  `description` varchar(255) NOT NULL COMMENT 'Explicación detallada de la acción permitida por este privilegio en el sistema.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp del registro del permiso.',
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permission_code` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo de privilegios granulares del backend encargados de restringir operaciones específicas.';

-- Volcando datos para la tabla estetica_carolinamora.permission: ~18 rows (aproximadamente)
INSERT IGNORE INTO `permission` (`permission_id`, `permission_code`, `description`, `created_at`) VALUES
	(1, 'auth:login', 'Permite el inicio de sesión en la plataforma.', '2026-06-07 17:19:53'),
	(2, 'appointments:create', 'Permite al usuario crear una nueva cita.', '2026-06-07 17:19:53'),
	(3, 'appointments:view', 'Permite visualizar sus propias citas.', '2026-06-07 17:19:53'),
	(4, 'profile:edit', 'Permite editar los datos básicos del perfil.', '2026-06-07 17:19:53'),
	(5, 'appointments:view_all', 'Permite visualizar la agenda global de citas de todos los especialistas o sucursales.', '2026-06-14 12:39:44'),
	(6, 'appointments:edit_all', 'Permite reprogramar, cancelar o modificar las citas de cualquier usuario.', '2026-06-14 12:39:44'),
	(7, 'reports:financial', 'Permite visualizar métricas financieras, ganancias globales y balance de caja.', '2026-06-14 12:39:44'),
	(8, 'commissions:view', 'Permite calcular y visualizar las comisiones generadas por los especialistas.', '2026-06-14 12:39:44'),
	(9, 'commissions:view_own', 'Permite a un especialista visualizar únicamente sus propias comisiones.', '2026-06-14 12:39:44'),
	(10, 'cashbox:manage', 'Permite realizar la apertura, registro de movimientos y cierre de la caja diaria.', '2026-06-14 12:39:44'),
	(11, 'inventory:view', 'Permite visualizar el stock de productos y suministros.', '2026-06-14 12:39:44'),
	(12, 'inventory:manage', 'Permite actualizar stock, registrar mermas y dar de alta productos en el inventario.', '2026-06-14 12:39:44'),
	(13, 'services:manage', 'Permite crear, editar o eliminar servicios del catálogo y sus precios.', '2026-06-14 12:39:44'),
	(14, 'branches:manage', 'Permite configurar los datos, horarios y recursos de las sucursales.', '2026-06-14 12:39:44'),
	(15, 'users:create', 'Permite registrar nuevos usuarios en el sistema (empleados, administradores).', '2026-06-14 12:39:44'),
	(16, 'users:view', 'Permite listar y ver el perfil de los empleados y clientes registrados.', '2026-06-14 12:39:44'),
	(17, 'users:edit', 'Permite modificar roles, permisos y datos de los usuarios del sistema.', '2026-06-14 12:39:44'),
	(18, 'users:delete', 'Permite dar de baja o inactivar usuarios en la plataforma.', '2026-06-14 12:39:44');

-- Volcando estructura para tabla estetica_carolinamora.professional_commission_ledger
CREATE TABLE IF NOT EXISTS `professional_commission_ledger` (
  `ledger_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria de la bitácora contable de comisiones.',
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Especialista beneficiario de la comisión.',
  `invoice_id` bigint(20) unsigned NOT NULL COMMENT 'Documento comercial que detonó el ingreso.',
  `calculated_commission_rate` decimal(5,2) NOT NULL COMMENT 'Porcentaje de comisión congelado al momento del cierre de la cita.',
  `net_commission_payout` decimal(10,2) NOT NULL COMMENT 'Monto financiero neto a pagar al profesional.',
  `payout_status` enum('ACCRUED','PROCESSED','PAID','DISPUTED') NOT NULL DEFAULT 'ACCRUED' COMMENT 'Estado de la nómina de la comisión.',
  `allocated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha en que se devengó el saldo.',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha real en que tesorería liquidó la nómina al especialista.',
  PRIMARY KEY (`ledger_id`),
  KEY `fk_comm_ledger_invoice` (`invoice_id`),
  KEY `idx_professional_payroll` (`professional_profile_id`,`payout_status`),
  CONSTRAINT `fk_comm_ledger_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_ledger_professional` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Libro contable auxiliar para automatización de nómina y cálculo exacto de comisiones del staff.';

-- Volcando datos para la tabla estetica_carolinamora.professional_commission_ledger: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.professional_profile
CREATE TABLE IF NOT EXISTS `professional_profile` (
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave primaria compartida (1:1) que hereda de user.user_id.',
  `service_commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de comisión asignado al especialista por cada servicio ejecutado (0.00 a 100.00).',
  `public_biography` text DEFAULT NULL COMMENT 'Breve extracto curricular o presentación del especialista visible en la PWA de cara al cliente.',
  `portfolio_photo_url` varchar(255) DEFAULT NULL COMMENT 'Ruta de almacenamiento en Cloud Object Storage de la fotografía del portafolio técnico.',
  `operational_status` enum('ACTIVE','ON_VACATION','INACTIVE') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Estado del especialista para controlar su disponibilidad inmediata en los motores de reservas.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp de la contratación o alta del profesional.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp de la última actualización física de los datos del especialista.',
  PRIMARY KEY (`professional_profile_id`),
  KEY `idx_professional_status` (`operational_status`) COMMENT 'Acelera el filtrado en la PWA para mostrar únicamente especialistas activos en la reserva pública.',
  CONSTRAINT `fk_professional_profile_user` FOREIGN KEY (`professional_profile_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_professional_commission` CHECK (`service_commission_rate` between 0.00 and 100.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Extensión especializada uno a uno (1:1) de la tabla user encargada de los metadatos operativos y de nómina del staff.';

-- Volcando datos para la tabla estetica_carolinamora.professional_profile: ~1 rows (aproximadamente)
INSERT IGNORE INTO `professional_profile` (`professional_profile_id`, `service_commission_rate`, `public_biography`, `portfolio_photo_url`, `operational_status`, `created_at`, `updated_at`) VALUES
	(1, 0.00, NULL, NULL, 'ACTIVE', '2026-06-14 13:34:01', '2026-06-14 13:34:01');

-- Volcando estructura para tabla estetica_carolinamora.professional_service
CREATE TABLE IF NOT EXISTS `professional_service` (
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea relacionada con professional_profile.professional_profile_id.',
  `service_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea relacionada con service.service_id.',
  `internal_certification_date` date NOT NULL COMMENT 'Fecha exacta en que el profesional aprobó la homologación técnica interna del salón para ejecutar el servicio.',
  PRIMARY KEY (`professional_profile_id`,`service_id`),
  KEY `idx_prof_serv_service_id` (`service_id`) COMMENT 'Optimiza búsquedas inversas en recepción para listar personal capacitado para un servicio seleccionado.',
  CONSTRAINT `fk_prof_serv_professional` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_serv_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Matriz asociativa (N:M) de capacidad operativa. Controla qué profesionales específicos pueden realizar qué servicios del menú.';

-- Volcando datos para la tabla estetica_carolinamora.professional_service: ~25 rows (aproximadamente)
INSERT IGNORE INTO `professional_service` (`professional_profile_id`, `service_id`, `internal_certification_date`) VALUES
	(1, 1, '2026-01-01'),
	(1, 2, '2026-01-01'),
	(1, 3, '2026-01-01'),
	(1, 4, '2026-01-01'),
	(1, 5, '2026-01-01'),
	(1, 6, '2026-01-01'),
	(1, 7, '2026-01-01'),
	(1, 8, '2026-01-01'),
	(1, 9, '2026-01-01'),
	(1, 10, '2026-01-01'),
	(1, 11, '2026-01-01'),
	(1, 12, '2026-01-01'),
	(1, 13, '2026-01-01'),
	(1, 14, '2026-01-01'),
	(1, 15, '2026-01-01'),
	(1, 16, '2026-01-01'),
	(1, 17, '2026-01-01'),
	(1, 18, '2026-01-01'),
	(1, 19, '2026-01-01'),
	(1, 20, '2026-01-01'),
	(1, 21, '2026-01-01'),
	(1, 22, '2026-01-01'),
	(1, 23, '2026-01-01'),
	(1, 24, '2026-01-01'),
	(1, 25, '2026-01-01');

-- Volcando estructura para tabla estetica_carolinamora.promotion
CREATE TABLE IF NOT EXISTS `promotion` (
  `promotion_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del registro de la promoción comercial.',
  `name` varchar(100) NOT NULL COMMENT 'Nombre de la campaña de marketing (ej: Black Friday Estética, Descuento de Temporada).',
  `discount_type` enum('PERCENTAGE','FIXED_AMOUNT') NOT NULL COMMENT 'Estrategia matemática de cálculo comercial para la reducción del precio en el Checkout.',
  `discount_value` decimal(10,2) NOT NULL COMMENT 'Magnitud del descuento (porcentaje o monto fijo según discount_type).',
  `start_date` date NOT NULL COMMENT 'Fecha de inicio del periodo de vigencia legal de la campaña.',
  `end_date` date NOT NULL COMMENT 'Fecha de finalización de la ventana de aplicación comercial.',
  `coupon_code` varchar(50) DEFAULT NULL COMMENT 'Código alfanumérico único para activación manual por parte del usuario (ej: PRIMERAVISITA20).',
  `usage_limit` int(10) unsigned DEFAULT NULL COMMENT 'Límite máximo global de redenciones permitidas en el sistema para resguardar la rentabilidad.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro de la promoción en la plataforma.',
  PRIMARY KEY (`promotion_id`),
  UNIQUE KEY `uq_promotion_coupon` (`coupon_code`),
  KEY `idx_promotion_validity` (`start_date`,`end_date`) COMMENT 'Acelera la consulta masiva de los Workers que evalúan campañas activas en tiempo real.',
  CONSTRAINT `chk_promotion_dates` CHECK (`end_date` >= `start_date`),
  CONSTRAINT `chk_promotion_value` CHECK (`discount_value` > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Motor paramétrico de reglas comerciales de descuento y gobernanza de campañas de retención.';

-- Volcando datos para la tabla estetica_carolinamora.promotion: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.promotion_service
CREATE TABLE IF NOT EXISTS `promotion_service` (
  `promotion_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea relacionada con promotion.promotion_id.',
  `service_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea relacionada con service.service_id.',
  PRIMARY KEY (`promotion_id`,`service_id`),
  KEY `fk_prom_serv_service` (`service_id`),
  CONSTRAINT `fk_prom_serv_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`promotion_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prom_serv_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Matriz asociativa intermedia (N:M) que restringe y delimita el alcance de las promociones a servicios específicos del catálogo.';

-- Volcando datos para la tabla estetica_carolinamora.promotion_service: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.role
CREATE TABLE IF NOT EXISTS `role` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del rol operativo.',
  `role_code` varchar(50) NOT NULL COMMENT 'Código único normalizado en mayúsculas para evaluación en código (ej: SUPER_ADMIN, RECEPCIONIST, CLIENT).',
  `role_name` varchar(100) NOT NULL COMMENT 'Nombre legible del rol orientado a interfaces administrativas (ej: Administrador de Sucursal).',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp de inserción del rol maestro.',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_code` (`role_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo maestro inmutable de perfiles y roles de acceso para el Control de Acceso Basado en Roles (RBAC).';

-- Volcando datos para la tabla estetica_carolinamora.role: ~5 rows (aproximadamente)
INSERT IGNORE INTO `role` (`role_id`, `role_code`, `role_name`, `created_at`) VALUES
	(1, 'SUPER_ADMIN', 'Administrador Principal', '2026-06-06 20:22:14'),
	(2, 'CLIENT', 'Cliente Final de Estética', '2026-06-07 17:20:12'),
	(3, 'MANAGER', 'Administrador de Sucursal', '2026-06-14 12:30:38'),
	(4, 'ESPECIALIST', 'Profesional Especialista', '2026-06-14 12:31:50'),
	(5, 'RECEPCIONIST', 'Recepcionista', '2026-06-14 12:32:51');

-- Volcando estructura para tabla estetica_carolinamora.role_permission
CREATE TABLE IF NOT EXISTS `role_permission` (
  `role_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea relacionada con role.role_id.',
  `permission_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea relacionada con permission.permission_id.',
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permission_permission` (`permission_id`),
  CONSTRAINT `fk_role_permission_permission` FOREIGN KEY (`permission_id`) REFERENCES `permission` (`permission_id`),
  CONSTRAINT `fk_role_permission_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Matriz intermedia (N:M) de seguridad que define los privilegios técnicos asociados a cada rol.';

-- Volcando datos para la tabla estetica_carolinamora.role_permission: ~45 rows (aproximadamente)
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`) VALUES
	(1, 1),
	(1, 2),
	(1, 3),
	(1, 4),
	(1, 5),
	(1, 6),
	(1, 7),
	(1, 8),
	(1, 9),
	(1, 10),
	(1, 11),
	(1, 12),
	(1, 13),
	(1, 14),
	(1, 15),
	(1, 16),
	(1, 17),
	(1, 18),
	(2, 1),
	(2, 2),
	(2, 3),
	(2, 4),
	(3, 1),
	(3, 2),
	(3, 4),
	(3, 5),
	(3, 6),
	(3, 10),
	(3, 11),
	(3, 12),
	(3, 13),
	(3, 15),
	(3, 16),
	(3, 17),
	(4, 1),
	(4, 3),
	(4, 4),
	(4, 9),
	(5, 1),
	(5, 2),
	(5, 4),
	(5, 5),
	(5, 6),
	(5, 10),
	(5, 11);

-- Volcando estructura para tabla estetica_carolinamora.schedule_exception
CREATE TABLE IF NOT EXISTS `schedule_exception` (
  `exception_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del registro de la excepción horaria.',
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada al especialista afectado por el bloqueo dinámico.',
  `start_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora exacta del inicio de la anomalía o bloqueo de agenda.',
  `end_timestamp` timestamp NOT NULL DEFAULT '1970-01-01 10:00:01' COMMENT 'Fecha y hora exacta de la finalización de la excepción de agenda.',
  `blocking_reason` enum('VACATION','SICK_LEAVE','PERSONAL_TIME','EMERGENCY') NOT NULL COMMENT 'Justificación tipificada del bloqueo para la visibilidad interna del personal de recepción.',
  `is_full_day_block` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag booleano que determina si el bloqueo inhabilita por completo la jornada ordinaria completa.',
  PRIMARY KEY (`exception_id`),
  KEY `idx_exception_timeline` (`professional_profile_id`,`start_timestamp`,`end_timestamp`) COMMENT 'Índice avanzado de rango para prevenir Overbooking al validar cruces entre solicitudes de citas y excepciones vigentes.',
  CONSTRAINT `fk_schedule_except_prof` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_exception_timestamps` CHECK (`end_timestamp` > `start_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Anulaciones dinámicas temporales sobre la cuadrícula horaria base por imprevistos o ausencias programadas del personal técnico.';

-- Volcando datos para la tabla estetica_carolinamora.schedule_exception: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.service
CREATE TABLE IF NOT EXISTS `service` (
  `service_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del servicio o tratamiento del catálogo comercial.',
  `category_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea que clasifica taxonómicamente el servicio.',
  `name` varchar(150) NOT NULL COMMENT 'Nombre comercial del servicio (ej: Balayage Platinum, Manicura Rusa).',
  `description` text DEFAULT NULL COMMENT 'Detalle técnico o comercial de lo que incluye el procedimiento estético.',
  `duration_minutes` int(10) unsigned NOT NULL COMMENT 'Duración estándar estimada del tratamiento, indispensable para el algoritmo de slots del Bot de WhatsApp.',
  `base_price` decimal(10,2) NOT NULL COMMENT 'Precio base regular del servicio antes de impuestos o promociones dinámicas.',
  `cleanup_margin_minutes` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Margen técnico post-servicio requerido para la sanitización de herramientas y preparación del tocador.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Flag lógico de vigencia comercial del ítem para evitar borrados físicos que rompan históricos contables.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de introducción del servicio al menú corporativo.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última modificación de precios o tiempos en el catálogo.',
  PRIMARY KEY (`service_id`),
  KEY `fk_service_category` (`category_id`),
  KEY `idx_service_lookup` (`is_active`,`category_id`) COMMENT 'Índice de cobertura de alta velocidad para renderizar por pestañas el menú en la PWA del cliente.',
  CONSTRAINT `fk_service_category` FOREIGN KEY (`category_id`) REFERENCES `service_category` (`category_id`),
  CONSTRAINT `chk_service_duration` CHECK (`duration_minutes` >= 1),
  CONSTRAINT `chk_service_price` CHECK (`base_price` >= 0.00)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo maestro centralizado del menú de servicios, duraciones y costos operativos del ecosistema de la estética.';

-- Volcando datos para la tabla estetica_carolinamora.service: ~25 rows (aproximadamente)
INSERT IGNORE INTO `service` (`service_id`, `category_id`, `name`, `description`, `duration_minutes`, `base_price`, `cleanup_margin_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Limpieza Facial Profunda', 'Tratamiento de higiene facial profunda para eliminación de impurezas.', 60, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(2, 1, 'Plasma', 'Aplicación de plasma rico en plaquetas para regeneración celular.', 60, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(3, 1, 'Dermapen', 'Tratamiento de microneedling para inducción de colágeno.', 45, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(4, 1, 'Baby Botox', 'Aplicación preventiva de toxina botulínica en dosis sutiles.', 45, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(5, 1, 'Aumento de Labios', 'Perfilado y volumen de labios con ácido hialurónico.', 60, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(6, 1, 'Hidratación de Labios', 'Tratamiento profundo de hidratación labial.', 30, 0.00, 15, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(7, 2, 'Delineado de ojos', 'Micropigmentación de ojos para un efecto de maquillaje permanente.', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(8, 2, 'Cejas Sombreadas', 'Técnica de micropigmentación efecto sombreado (Shadow).', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(9, 2, 'Cejas Efecto Polvo', 'Micropigmentación con acabado suave y difuminado (Powder Brows).', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(10, 2, 'Cejas Mixtas', 'Combinación de pelo a pelo y sombreado para mayor densidad.', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(11, 2, 'Microblading', 'Técnica de trazado pelo a pelo hiperrealista.', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(12, 2, 'Neutralización de Labios', 'Corrección de tonalidades oscuras o frías en los labios.', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(13, 2, 'Labios Full color', 'Micropigmentación labial con saturación de color completa.', 120, 0.00, 20, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(14, 3, 'Lifting de pestañas', 'Elevación y curvatura de las pestañas naturales desde la raíz.', 60, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(15, 3, 'Pestañas por punto', 'Aplicación de extensiones de pestañas en formato de grupos o puntos.', 60, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(16, 3, 'Laminado de Cejas', 'Tratamiento semipermanente para direccionar y fijar el vello de las cejas.', 45, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(17, 3, 'Diseño de cejas con henna', 'Diseño personalizado y tintura temporal con henna natural.', 45, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(18, 3, 'Depilación con cera', 'Depilación tradicional con cera corporal/facial.', 30, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(19, 4, 'Barba', 'Depilación láser diodo en zona de la barba.', 20, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(20, 4, 'Axilas', 'Depilación láser diodo en zona de axilas.', 15, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(21, 4, 'Media pierna', 'Depilación láser diodo desde la rodilla hasta el tobillo.', 30, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(22, 4, 'Bikini', 'Depilación láser diodo en zona íntima / bikini.', 20, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(23, 4, 'Piernas Completas', 'Depilación láser diodo en extremidades inferiores completas.', 45, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(24, 4, 'Espalda', 'Depilación láser diodo en zona completa de la espalda.', 30, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45'),
	(25, 4, 'Dedos', 'Depilación láser diodo en zona de los dedos de manos o pies.', 10, 0.00, 10, 1, '2026-06-07 11:47:45', '2026-06-07 11:47:45');

-- Volcando estructura para tabla estetica_carolinamora.service_category
CREATE TABLE IF NOT EXISTS `service_category` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria de la categoría taxonómica.',
  `name` varchar(100) NOT NULL COMMENT 'Nombre único de la agrupación de servicios (ej: Colorimetría, Estilismo, Cejas y Pestañas).',
  `description` varchar(255) DEFAULT NULL COMMENT 'Breve alcance de la categoría para interfaces de configuración.',
  `icon_url` varchar(255) DEFAULT NULL COMMENT 'Ruta de almacenamiento del icono visual representativo en la interfaz de la PWA.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de alta de la categoría.',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla maestra de taxonomías lógicas para estructurar el menú del frontend y agrupar reportes de inteligencia comercial.';

-- Volcando datos para la tabla estetica_carolinamora.service_category: ~4 rows (aproximadamente)
INSERT IGNORE INTO `service_category` (`category_id`, `name`, `description`, `icon_url`, `created_at`) VALUES
	(1, 'Estética Facial y Avanzada', 'Tratamientos avanzados de cuidado del rostro, regeneración celular e hidratación profunda.', 'assets/icons/facial-avanzada.svg', '2026-06-07 11:47:02'),
	(2, 'Micropigmentación', 'Procedimientos de maquillaje permanente e hiperrealista para el diseño de cejas, ojos y labios.', 'assets/icons/micropigmentacion.svg', '2026-06-07 11:47:02'),
	(3, 'Cejas, Pestañas y Cera', 'Servicios de diseño de mirada, laminados, lifting y técnicas de depilación tradicional.', 'assets/icons/cejas-pestanas.svg', '2026-06-07 11:47:02'),
	(4, 'Depilación Láser', 'Tratamientos corporales y faciales de depilación definitiva mediante tecnología láser.', 'assets/icons/depilacion-laser.svg', '2026-06-07 11:47:02');

-- Volcando estructura para tabla estetica_carolinamora.service_rating
CREATE TABLE IF NOT EXISTS `service_rating` (
  `rating_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del registro de calificación post-servicio.',
  `appointment_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada a la cita completada que origina la encuesta de satisfacción.',
  `client_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que identifica al cliente evaluador, garantizando que solo el receptor del servicio pueda calificar.',
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que identifica al especialista evaluado, permitiendo agregaciones de rating por profesional en el dashboard.',
  `score` tinyint(3) unsigned NOT NULL COMMENT 'Calificación numérica otorgada por el cliente en el rango cerrado [1, 5] estrellas.',
  `comments` text DEFAULT NULL COMMENT 'Observaciones textuales libres provistas por el cliente sobre la calidad de la atención recibida.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp exacto del registro de la reseña, generado automáticamente al momento de la inserción.',
  PRIMARY KEY (`rating_id`),
  UNIQUE KEY `uq_rating_appointment_client` (`appointment_id`,`client_profile_id`) COMMENT 'Garantiza que un cliente no pueda emitir más de una reseña por la misma cita, previniendo manipulación de métricas.',
  KEY `fk_rating_client` (`client_profile_id`),
  KEY `fk_rating_professional` (`professional_profile_id`),
  KEY `idx_rating_professional_avg` (`professional_profile_id`,`score`) COMMENT 'Índice de cobertura que acelera el cálculo de AVG(score) agrupado por especialista en el dashboard administrativo.',
  KEY `idx_rating_timeline` (`created_at`) COMMENT 'Optimiza consultas de reseñas recientes y filtros temporales en el panel CRM.',
  CONSTRAINT `fk_rating_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_client` FOREIGN KEY (`client_profile_id`) REFERENCES `client_profile` (`client_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_professional` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating_score` CHECK (`score` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro inmutable de calificaciones post-servicio emitidas por los clientes. Soporta métricas de calidad agregadas por especialista y globales en el Dashboard.';

-- Volcando datos para la tabla estetica_carolinamora.service_rating: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.system_audit_log
CREATE TABLE IF NOT EXISTS `system_audit_log` (
  `audit_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria atómica de auditoría.',
  `user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Identidad del usuario operador que ejecutó la transacción (NULL si fue del sistema/cron).',
  `action_type` enum('CREATE','UPDATE','DELETE','AUTH_LOGIN','AUTH_LOGOUT','SECURITY_BREACH') NOT NULL COMMENT 'Operación de datos o evento perimetral ejecutado.',
  `target_table` varchar(100) NOT NULL COMMENT 'Tabla física del esquema que sufrió la mutación (ej: appointment, user).',
  `record_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Llave primaria del registro alterado para trazabilidad forense directa.',
  `pre_mutation_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Fotografía en formato JSON del registro ANTES del cambio (NULL en CREATE).' CHECK (json_valid(`pre_mutation_state`)),
  `post_mutation_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Fotografía en formato JSON del registro DESPUÉS del cambio (NULL en DELETE).' CHECK (json_valid(`post_mutation_state`)),
  `client_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Metadatos de red del emisor (IP, User Agent, Endpoint consumido, GeoLocalización aproximada).' CHECK (json_valid(`client_metadata`)),
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora milimétrica del evento de auditoría.',
  PRIMARY KEY (`audit_id`),
  KEY `fk_audit_log_user` (`user_id`),
  KEY `idx_audit_forensic_timeline` (`target_table`,`record_id`,`executed_at`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bitácora inmutable de Auditoría y Captura de Cambios (CDC) para cumplimiento normativo y seguridad informática.';

-- Volcando datos para la tabla estetica_carolinamora.system_audit_log: ~2 rows (aproximadamente)
INSERT IGNORE INTO `system_audit_log` (`audit_id`, `user_id`, `action_type`, `target_table`, `record_id`, `pre_mutation_state`, `post_mutation_state`, `client_metadata`, `executed_at`) VALUES
	(1, NULL, '', 'user', 6, '{}', '{"email":"ana.garcia3@test.com","phone":"+573210000003","first_name":"Ana","last_name":"Garcia"}', '{"ip":"::1","user_agent":"curl/8.19.0"}', '2026-06-13 17:42:27'),
	(2, 7, '', 'user', 7, '{}', '{"email":"lauracortes@gmail.com","phone":"3211234567","first_name":"laura","last_name":"cortes"}', '{"ip":"::1","user_agent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36"}', '2026-06-13 17:44:35');

-- Volcando estructura para tabla estetica_carolinamora.user
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria autocorrelativa global de la identidad de usuario.',
  `email` varchar(191) NOT NULL COMMENT 'Dirección de correo electrónico único. Utilizada como Login Credential principal en la PWA.',
  `password_hash` varchar(255) NOT NULL COMMENT 'Hash seguro de la contraseña (BCrypt/Argon2id). Incompatible con texto plano.',
  `auth_phone` varchar(20) NOT NULL COMMENT 'Número telefónico en formato internacional (E.164). Identificador único para el Webhook del Bot de WhatsApp.',
  `first_name` varchar(100) NOT NULL COMMENT 'Nombres del usuario.',
  `last_name` varchar(100) NOT NULL COMMENT 'Apellidos del usuario.',
  `account_status` enum('ACTIVE','SUSPENDED','PENDING_VERIFICATION') NOT NULL DEFAULT 'PENDING_VERIFICATION' COMMENT 'Máquina de estados de la cuenta para control estricto de accesos a la API.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp de la creación de la identidad de autenticación.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp de la última modificación física del registro.',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_auth_phone` (`auth_phone`),
  KEY `idx_user_search` (`last_name`,`first_name`) COMMENT 'Índice de cobertura para la búsqueda predictiva rápida de usuarios.',
  CONSTRAINT `chk_user_email_format` CHECK (`email` like '%@%.%')
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Identidades de autenticación centrales desvinculadas de la lógica comercial u operativa del negocio (RBAC Base).';

-- Volcando datos para la tabla estetica_carolinamora.user: ~3 rows (aproximadamente)
INSERT IGNORE INTO `user` (`user_id`, `email`, `password_hash`, `auth_phone`, `first_name`, `last_name`, `account_status`, `created_at`, `updated_at`) VALUES
	(1, 'admin@estetica.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+573001234567', 'Carolina', 'Mora', 'ACTIVE', '2026-06-06 19:30:26', '2026-06-06 19:30:26'),
	(7, 'lauracortes@gmail.com', '$2y$10$1Be.UPRhQIxSKkdAaqprAOjuMBOwO237EQM.4OgkypydD1s5vsfYC', '3211234567', 'Laura', 'Cortes', 'ACTIVE', '2026-06-13 17:44:35', '2026-06-14 23:25:11'),
	(8, 'albac@gmail.com', '$2y$10$4K04bSS8wkHCkIMYnssM1eFg8gvO500gY7vgh1V4YeeozvZExYB.C', '3001234589', 'alba', 'castillo', 'ACTIVE', '2026-06-14 14:50:37', '2026-06-14 14:50:37');

-- Volcando estructura para tabla estetica_carolinamora.user_role
CREATE TABLE IF NOT EXISTS `user_role` (
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea que conecta con user.user_id.',
  `role_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea que conecta con role.role_id.',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora exacta de la asignación del rol al usuario.',
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_user_role_role_id` (`role_id`) COMMENT 'Optimiza listados masivos de personal filtrados por su rol operativo.',
  CONSTRAINT `fk_user_role_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`),
  CONSTRAINT `fk_user_role_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla asociativa intermedia (N:M) encargada de mapear los roles activos asignados a los usuarios del sistema.';

-- Volcando datos para la tabla estetica_carolinamora.user_role: ~3 rows (aproximadamente)
INSERT IGNORE INTO `user_role` (`user_id`, `role_id`, `assigned_at`) VALUES
	(1, 1, '2026-06-14 13:57:44'),
	(7, 2, '2026-06-14 23:25:11'),
	(8, 5, '2026-06-14 14:50:37');

-- Volcando estructura para tabla estetica_carolinamora.user_session
CREATE TABLE IF NOT EXISTS `user_session` (
  `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria única de la sesión activa.',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada al usuario dueño de la sesión.',
  `jwt_token_hash` varchar(64) NOT NULL COMMENT 'Hash SHA-256 del token JWT persistido para validaciones rápidas de revocación o cierre remoto.',
  `ip_address` varchar(45) NOT NULL COMMENT 'Dirección IP de origen de la solicitud (compatible con IPv4 e IPv6) para análisis forense.',
  `user_agent` text NOT NULL COMMENT 'Metadatos crudos del navegador y dispositivo emisor para trazabilidad de la sesión de la PWA.',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de expiración física de la sesión, sincronizada con la vigencia del JWT.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de login de la sesión.',
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `uq_user_session_token` (`jwt_token_hash`),
  KEY `idx_user_session_user_expires` (`user_id`,`expires_at`) COMMENT 'Agiliza procesos Cron que depuran sesiones expiradas de la base de datos de forma masiva.',
  CONSTRAINT `fk_user_session_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Control y persistencia de estados de sesiones Web para permitir revocación de tokens y auditoría perimetral.';

-- Volcando datos para la tabla estetica_carolinamora.user_session: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.wa_chat_session
CREATE TABLE IF NOT EXISTS `wa_chat_session` (
  `chat_session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria de la sesión transaccional de mensajería.',
  `phone_number` varchar(20) NOT NULL COMMENT 'Identificador telefónico único del usuario de WhatsApp en formato internacional estricto E.164.',
  `current_node_code` varchar(50) NOT NULL DEFAULT 'START_NODE' COMMENT 'Código clave del nodo actual del árbol conversacional en el que se ubica el usuario (ej: CHOOSE_DATE).',
  `session_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Payload de estado transaccional intermedio (datos temporales recolectados como sucursal, servicio o fecha antes del DDL definitivo).' CHECK (json_valid(`session_context`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de inicialización del chat.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última interacción registrada del usuario con el webhook.',
  PRIMARY KEY (`chat_session_id`),
  UNIQUE KEY `uq_wa_chat_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Máquina de estados persistente asíncrona para el control de flujos conversacionales automatizados del chatbot.';

-- Volcando datos para la tabla estetica_carolinamora.wa_chat_session: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.wa_message_log
CREATE TABLE IF NOT EXISTS `wa_message_log` (
  `message_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria secuencial del log atómico de mensajería.',
  `chat_session_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada a la sesión de chat propietaria del mensaje.',
  `direction` enum('INBOUND','OUTBOUND') NOT NULL COMMENT 'Dirección de la transmisión (INBOUND: Usuario a Bot, OUTBOUND: Bot a Usuario).',
  `message_body` text NOT NULL COMMENT 'Contenido textual explícito o metadatos de la carga enviada o recibida.',
  `whatsapp_api_message_id` varchar(150) NOT NULL COMMENT 'Identificador canónico único retornado oficialmente por la infraestructura de la API de Meta Cloud.',
  `delivery_status` enum('SENT','DELIVERED','READ','FAILED') NOT NULL DEFAULT 'SENT' COMMENT 'Estado dinámico de confirmación de entrega controlado asíncronamente por los webhooks de Meta.',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora exacta del envío o recepción del evento.',
  PRIMARY KEY (`message_id`),
  UNIQUE KEY `uq_wa_api_msg_id` (`whatsapp_api_message_id`),
  KEY `fk_wa_msg_log_session` (`chat_session_id`),
  CONSTRAINT `fk_wa_msg_log_session` FOREIGN KEY (`chat_session_id`) REFERENCES `wa_chat_session` (`chat_session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historial físico completo e inmutable de la mensajería del canal oficial de WhatsApp Business API.';

-- Volcando datos para la tabla estetica_carolinamora.wa_message_log: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.wa_notification_queue
CREATE TABLE IF NOT EXISTS `wa_notification_queue` (
  `queue_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria correlativa de la cola de envíos salientes.',
  `template_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea que determina la plantilla HSM obligatoria a utilizar para construir el mensaje.',
  `recipient_phone` varchar(20) NOT NULL COMMENT 'Teléfono de destino en formato internacional estricto E.164.',
  `dynamic_parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Arreglo JSON ordenado que inyecta los valores dinámicos a las variables de la plantilla (ej: ["Maria", "15:00"]).' CHECK (json_valid(`dynamic_parameters`)),
  `scheduled_send_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora programada en que el proceso en segundo plano (Daemon/Worker) debe despachar la transacción.',
  `processing_status` enum('QUEUED','PROCESSING','SENT','FAILED') NOT NULL DEFAULT 'QUEUED' COMMENT 'Control de estados de procesamiento de la cola asíncrona.',
  `retry_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Contador interno de intentos fallidos de transmisión por red antes de marcar excepción definitiva.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de inserción en cola de envío.',
  PRIMARY KEY (`queue_id`),
  KEY `fk_wa_queue_template` (`template_id`),
  KEY `idx_wa_queue_worker` (`processing_status`,`scheduled_send_time`) COMMENT 'Índice de cobertura de alta velocidad para barrido recurrente de Daemons de notificación cada minuto.',
  CONSTRAINT `fk_wa_queue_template` FOREIGN KEY (`template_id`) REFERENCES `wa_template` (`template_id`),
  CONSTRAINT `chk_wa_retry_limit` CHECK (`retry_count` <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Cola transaccional persistente optimizada para el despacho asíncrono masivo de recordatorios automatizados.';

-- Volcando datos para la tabla estetica_carolinamora.wa_notification_queue: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.wa_template
CREATE TABLE IF NOT EXISTS `wa_template` (
  `template_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria del catálogo de plantillas HSM.',
  `template_code` varchar(100) NOT NULL COMMENT 'Nombre o código identificador único asignado a la plantilla dentro de Meta Business Manager (ej: appointment_remind).',
  `language_code` varchar(10) NOT NULL DEFAULT 'es' COMMENT 'Código ISO internacional de idioma del texto cargado (ej: es, en).',
  `template_body` text NOT NULL COMMENT 'Cuerpo estructural con variables posicionales de la plantilla oficial aprobada (ej: Hola {{1}}, tu cita es el {{2}}).',
  `meta_status` enum('APPROVED','PENDING','REJECTED') NOT NULL DEFAULT 'PENDING' COMMENT 'Estado de homologación del recurso en la plataforma de Meta Cloud.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de registro interno del recurso.',
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `uq_wa_template_code_lang` (`template_code`,`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo paramétrico de plantillas Highly Structured Messages (HSM) autorizadas por Meta para notificaciones salientes.';

-- Volcando datos para la tabla estetica_carolinamora.wa_template: ~0 rows (aproximadamente)

-- Volcando estructura para tabla estetica_carolinamora.work_schedule
CREATE TABLE IF NOT EXISTS `work_schedule` (
  `work_schedule_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Llave primaria autocorrelativa de la regla horaria.',
  `professional_profile_id` bigint(20) unsigned NOT NULL COMMENT 'Llave foránea vinculada al profesional dueño de la cuadrícula horaria.',
  `branch_id` int(10) unsigned NOT NULL COMMENT 'Llave foránea vinculada a la sucursal física donde opera en este bloque horario.',
  `day_of_week` tinyint(3) unsigned NOT NULL COMMENT 'Día ordinario de la semana codificado numéricamente estándar (0 = Domingo a 6 = Sábado).',
  `start_time` time NOT NULL COMMENT 'Hora de inicio de la jornada laboral ordinaria dentro del salón.',
  `end_time` time NOT NULL COMMENT 'Hora de finalización de la jornada de trabajo ordinaria.',
  `lunch_start_time` time DEFAULT NULL COMMENT 'Hora de inicio de la ventana de descanso/almuerzo no disponible para citas.',
  `lunch_end_time` time DEFAULT NULL COMMENT 'Hora de finalización del periodo de descanso/almuerzo.',
  PRIMARY KEY (`work_schedule_id`),
  KEY `fk_work_schedule_professional` (`professional_profile_id`),
  KEY `idx_schedule_matrix` (`branch_id`,`day_of_week`,`professional_profile_id`) COMMENT 'Índice de cobertura crítico para el motor de búsqueda de disponibilidad ordinaria del Bot y la PWA.',
  CONSTRAINT `fk_work_schedule_branch` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`),
  CONSTRAINT `fk_work_schedule_professional` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profile` (`professional_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_work_day` CHECK (`day_of_week` between 0 and 6),
  CONSTRAINT `chk_work_hours` CHECK (`end_time` > `start_time`),
  CONSTRAINT `chk_lunch_hours` CHECK (`lunch_end_time` > `lunch_start_time` or `lunch_start_time` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Malla horaria maestra semanal ordinaria de prestación de servicios por especialista y sucursal.';

-- Volcando datos para la tabla estetica_carolinamora.work_schedule: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
