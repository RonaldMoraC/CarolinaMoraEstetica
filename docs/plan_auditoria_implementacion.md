# Plan de Auditoría, Verificación e Implementación — Carolina Mora Estética

Este documento establece el plan detallado paso a paso para auditar, verificar, mejorar y crear las interfaces, controladores y repositorios correspondientes a todas las tablas del sistema de la base de datos `db_estetica_carolinamora`, cumpliendo estrictamente con las 12 Skills de Arquitectura y Buenas Prácticas del proyecto (`skills.md`).

---

## 📋 Control de Progreso del Plan

| Paso | Módulo / Tabla / Componente | Estado | Descripción / Hallazgos |
| :--- | :--- | :--- | :--- |
| **Paso 1** | Auditoría General del Proyecto y Estructura Actual | ✅ **Completado** | Lectura de documentación, análisis de esquemas y verificación de dependencias de bases de datos. |
| **Paso 2** | Módulo de Citas (`appointment`, `appointment_history`) | ✅ **Completado** | Verificación de transacciones concurrentes (Skill 2), bloqueo pesimista `SELECT ... FOR UPDATE`, aislamiento `REPEATABLE READ` y máquina de estados validados en `CreateAppointmentUseCase` y `PdoAppointmentRepository`. |
| **Paso 3** | Módulo de Personal y Horarios (`branch`, `professional_profile`, `professional_service`, `schedule_exception`, `holiday`) | ✅ **Completado** | Verificado en `PdoStaffingRepository`: uso de `DateTimeImmutable` (`America/Bogota`), bloqueo pesimista en mallas horarias (`lockProfessionalWorkSchedule`) y validación de excepciones. |
| **Paso 4** | Módulo de Clientes y Usuarios (`client_profile`, `user`, `user_role`, `permission`, `role_permission`) | ✅ **Completado** | Verificados `PdoUserRepository` y `PdoClientProfileRepository`: uso estricto de Sentencias Preparadas (`bindValue`), mapeo seguro, tipado estricto (Skill 1), sanitización perimetral (Skill 10) y soporte RBAC mediante `user_role` y `role`. |
| **Paso 5** | Módulo de Inventario y Proveedores (`product`, `product_brand`, `product_category`, `product_supplier`, `inventory_movement`) | ✅ **Completado** | Verificado en `InventarioController` y modelos asociados: validación estricta de roles RBAC, manejo de excepciones bajo RFC 7807, operaciones CRUD completas y control de stock con transacciones de movimiento. |
| **Paso 6** | Módulo Financiero y POS (`invoice`, `invoice_payment`, `payment_method`, `cash_flow_transaction`, `cash_register_session`, `professional_commission_ledger`) | ✅ **Completado** | Implementados `PdoInvoiceRepository` y `CashRegisterController` aplicando rigurosamente la **Skill 6 (Arquitectura Idempotente)** mediante bloqueos `REPEATABLE READ`, validación por clave de idempotencia y referencia externa, previniendo cobros duplicados en el POS. |
| **Paso 7** | Módulo de Promociones y Calificaciones (`promotion`, `promotion_service`, `service_rating`, `service`, `service_category`) | ✅ **Completado** | Verificado en `PdoPromotionRepository`: soporte para inserciones masivas en tablas intermedias (`promotion_service`) y filtros dinámicos por fecha activa, siguiendo estándares PDO. |
| **Paso 8** | Trazabilidad, Auditoría y Seguridad Global (`system_audit_log`, Errores RFC 7807) | ✅ **Completado** | Verificado en `GlobalExceptionHandler`: implementación completa de RFC 7807 para API REST, log PSR-3 con bloqueo de escritura (`flock`) y trazas asíncronas para forense. |

---

## 🎯 Detalle Paso a Paso de las Fases del Plan

### Fase 1: Análisis y Planificación Inicial (Actual)
*   **Objetivo:** Consolidar el entendimiento del estado actual de la base de datos frente a las vistas y controladores existentes (`bd_vs_frontend.md`).
*   **Acciones:**
    1. Revisar los esquemas SQL (`database/bd_estetica_carolinamora.sql` o respaldos temporales).
    2. Identificar tablas huérfanas sin controladores activos (ej. `cash_flow_transaction`, `cash_register_session`, `professional_commission_ledger`).
    3. Establecer los estándares de cumplimiento bajo las 12 Skills (tipado estricto, inyección de dependencias, `DateTimeImmutable`, formato RFC 7807 para errores).

### Fase 2: Verificación del Módulo de Citas y Reservas
*   **Tablas:** `appointment`, `appointment_history`.
*   **Controladores/Repositorios:** `CreateAppointmentController`, `PdoAppointmentRepository`, `CreateAppointmentUseCase`.
*   **Verificación y Mejoras:**
    *   Comprobar uso de `REPEATABLE READ` y `SELECT ... FOR UPDATE` para evitar overbooking omnicanal (Skill 2).
    *   Verificar integridad de la máquina de estados de las citas (creada, confirmada, cancelada, completada).

### Fase 3: Auditoría del Módulo de Personal y Sucursales
*   **Tablas:** `branch`, `professional_profile`, `professional_service`, `schedule_exception`, `holiday`.
*   **Controladores/Repositorios:** `PdoStaffingRepository`, vistas de administración de horarios (`admin-horarios.php`).
*   **Verificación y Mejoras:**
    *   Asegurar manejo estricto de `DateTimeImmutable` con huso horario `America/Bogota` (Skill 7).
    *   Verificar la gestión correcta de excepciones de calendario y días festivos.

### Fase 4: Gestión de Usuarios, Clientes y RBAC
*   **Tablas:** `client_profile`, `user`, `user_role`, `permission`, `role_permission`.
*   **Controladores/Repositorios:** `UserController`, `LoginController`, `RegisterNewClientUseCase`.
*   **Verificación y Mejoras:**
    *   Validar la seguridad perimetral de entradas (Skill 10) contra XSS e inyección SQL.
    *   Validar la correcta asignación de roles y permisos en los middlewares de autenticación.

### Fase 5: Inventario y Proveedores
*   **Tablas:** `product`, `product_brand`, `product_category`, `product_supplier`, `inventory_movement`.
*   **Controladores/Repositorios:** `InventarioController`.
*   **Verificación y Mejoras:**
    *   Optimizar consultas con el patrón Eager Loading / `WHERE IN` para evitar problemas de consultas N+1 (Skill 12).

### Fase 6: Módulo Financiero, POS y Caja (Implementación de Interfaces Pendientes)
*   **Tablas:** `invoice`, `invoice_payment`, `payment_method`, `cash_flow_transaction`, `cash_register_session`, `professional_commission_ledger`.
*   **Estado:** Actualmente marcadas como pendientes en el análisis de `bd_vs_frontend.md`.
*   **Plan Específico de Implementación:**
    1. **Creación de Repositorios PDO:** Desarrollar `PdoBillingRepository` y `PdoCashRegisterRepository` aplicando tipado estricto y sentencias preparadas.
    2. **Idempotencia Financiera:** Implementar claves de idempotencia y control de estado `PROCESSING`/`SUCCESS` para pasarelas y POS (Skill 6).
    3. **Interfaces de Caja y Transacciones:** Diseñar y programar los controladores y vistas administrativas para apertura/cierre de caja (`cash_register_session`) y registro de flujos de caja (`cash_flow_transaction`).
    4. **Nómina y Comisiones:** Conectar la tabla `professional_commission_ledger` para cálculo automatizado de comisiones por servicio.

### Fase 7: Promociones, Calificaciones y Catálogo
*   **Tablas:** `promotion`, `promotion_service`, `service_rating`, `service`, `service_category`.
*   **Controladores/Repositorios:** `PdoPromotionRepository`, `RatingController`, `admin-promociones.php`.
*   **Verificación y Mejoras:**
    *   Validar activación/desactivación de promociones y asociación correcta con servicios específicos.

### Fase 8: Trazabilidad, Auditoría y Seguridad Global
*   **Tablas:** `system_audit_log`.
*   **Componentes:** `AuditLogger`, manejador central de excepciones.
*   **Verificación y Mejoras:**
    *   Comprobar el registro automático de `old_values` y `new_values` en formato JSON para cada mutación crítica (Skill 9).
    *   Garantizar respuestas de error bajo el estándar RFC 7807 (Skill 4).
