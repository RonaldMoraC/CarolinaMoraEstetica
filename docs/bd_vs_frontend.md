# Análisis: Relación Base de Datos vs Frontend/Backend

Este documento registra la trazabilidad entre las tablas de la base de datos `db_estetica_carolinamora` y las interfaces de usuario (Vistas/Controladores) que realizan operaciones CRUD sobre ellas.

## Estado del Análisis
| Tabla | Estado CRUD (C/R/U/D) | Interfaces/Endpoints Identificados |
| :--- | :--- | :--- |
| `appointment` | Create, Read, Update (Status change) | `CreateAppointmentController`, `PdoAppointmentRepository`, `CreateAppointmentUseCase` | ya está
| `appointment_history` | Create (Audit log on status change), Read (Indirect via timeline) | `PdoAppointmentRepository::updateStatus()` | ya está
| `branch` | Create, Read, Update | `PdoBranchRepository` (utilizado en módulos de reserva, horarios y selección de sede) |
| `cash_flow_transaction` | Pendiente (Sin interfaz/repositorio activo en el backend actual) | Ninguno |
| `cash_register_session` | Pendiente (Sin interfaz/repositorio activo en el backend actual) | Ninguno |
| `client_profile` | Create, Read, Update | `PdoClientProfileRepository`, `RegisterNewClientUseCase`, `UserController` |
| `holiday` | Pendiente (Definida en base de datos, implementación parcial) | `public/views/admin/admin-horarios.php` (vía manejo de excepciones) |
| `inventory_movement` | Create, Read | `MovimientoInventario`, `InventarioController` (módulo de inventario) |
| `invoice` | Create, Read | `PdoDashboardMetricsRepository::getPaymentsByMethod` (indirect) |
| `invoice_payment` | Read | `PdoDashboardMetricsRepository::getPaymentsByMethod()` |
| `payment_method` | Read | `PdoDashboardMetricsRepository::getPaymentsByMethod()` |
| `permission` | Read | Catálogo paramétrico RBAC (gestionado a nivel de base de datos y middlewares) |
| `product` | Analizada | `InventarioController` |
| `product_brand` | Analizada | `InventarioController` |
| `product_category` | Analizada | `InventarioController` |
| `product_supplier` | Analizada | `InventarioController` |
| `professional_commission_ledger` | Pendiente | Tabla de libro contable auxiliar para automatización de nómina (definida en BD, pendiente de integración activa en repositorios) |
| `professional_profile` | Create, Read, Update | `PdoStaffingRepository` (`upsertProfessionalProfile`, `findProfessionalProfileById`, `findAllProfessionals`), `PdoDashboardMetricsRepository` |
| `professional_service` | Create, Read, Delete | `PdoStaffingRepository` (`getProfessionalServices`, `syncProfessionalServices`, `findProfessionalsByService`) |
| `promotion` | Create, Read, Update (Deactivate) | `PdoPromotionRepository`, `CreatePromotionUseCase`, `CreatePromotionController`, `admin-promociones.php` |
| `promotion_service` | Create, Delete | `PdoPromotionRepository::create()` (inserción masiva asociada a promociones) |
| `role_permission` | Read | Consultada por RBAC para mapear permisos a roles |
| `schedule_exception` | Create, Read, Delete | `PdoStaffingRepository` (vía `admin-horarios.php` y casos de uso) |
| `service` | Read | Catálogo de servicios para la PWA de reservas y reportes |
| `service_category` | Read | Taxonomía para el menú de servicios (agrupación en PWA) |
| `service_rating` | Create, Read | `PdoDashboardMetricsRepository` (reportes de calidad), `RatingController` |
| `system_audit_log` | Read | `AuditLogger` (trazabilidad forense) |
| `user` | Create, Read, Update | `UserController`, `LoginController`, `RegisterNewClientUseCase` |
| `user_role` | Read | Mapeo de roles en middlewares de autenticación |

---

## Hallazgos: Tabla `product`, `product_brand`, `product_category`, `product_supplier`

Las tablas relacionadas con el inventario (`product`, `product_brand`, `product_category`, `product_supplier`) ya han sido refactorizadas y analizadas.

### Operaciones CRUD Identificadas:
*   **`product`**:
    *   **Create**: `InventarioController::guardarProducto()`
    *   **Read**: `Producto::obtenerTodos()`, `Producto::obtenerPorId()`, `Producto::obtenerMetricas()`
    *   **Update**: `Producto::actualizar()`, `Producto::actualizarStock()`
    *   **Delete**: `Producto::eliminar()`
*   **`product_brand` / `product_category` / `product_supplier`**:
    *   **Read**: `InventarioController::obtenerDatosIniciales()`
    *   **Create (Supplier only)**: `InventarioController::guardarProveedor()`

*Estado: Módulo de inventario refactorizado a Clean Architecture con inyección de dependencias PDO.*

---
*Continuando con el análisis: Esperando autorización para comenzar con la tabla `appointment`.*
