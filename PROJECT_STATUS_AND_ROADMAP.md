# Estado Actual del Proyecto

## Funcionalidades Implementadas

* **Gestión de Identidad y Autenticación (IAM)**
  * *Módulos involucrados:* IAM (Identity & Access Management), Shared Infrastructure.
  * *Componentes:* `User`, `Role` (Domain Entities), `Email`, `HashedPassword` (Value Objects), `UserRepositoryInterface` (Domain), `PdoUserRepository` (Infrastructure), `AuthenticateUserUseCase`, `RegisterNewClientUseCase`, `AssignRoleUseCase` (Application), `LoginController`, `RegisterController`, `MeController`, `UserController`, `ProfessionalServiceController`, `UpdateProfileController` (Infrastructure/Http), `JwtTokenManager` (Security).

* **Enrutamiento Seguro y Middleware de Acceso**
  * *Módulos involucrados:* Shared Infrastructure, IAM.
  * *Componentes:* `Router`, `routes.php` (Routing), `AuthMiddleware`, `ViewAuthMiddleware` (Security/Routing) que valida la sesión y los permisos atómicos basados en roles (RBAC) inyectando JWT en las cabeceras HTTP.

* **Búsqueda y Navegación de Catálogo**
  * *Módulos involucrados:* Catalog & Pricing, Shared Infrastructure.
  * *Componentes:* `ServiceRepositoryInterface` (Domain), `PdoServiceRepository`, `PdoCategoryRepository` (Infrastructure/Persistence), `BrowseServiceCatalogUseCase` (Application), `BrowseCatalogController`, `GetCategoriesController`, `GetProfessionalsByServiceController` (Infrastructure/Http).

* **Agendamiento de Citas (Creación y Cancelación)**
  * *Módulos involucrados:* Booking Core, IAM, Shared Infrastructure.
  * *Componentes:* `Appointment` (Domain Entity), `AppointmentRepositoryInterface`, `ClientProfileRepositoryInterface` (Domain/Repositories), `PdoAppointmentRepository`, `PdoClientProfileRepository` (Infrastructure/Persistence), `CreateAppointmentUseCase`, `CancelAppointmentUseCase` (Application), `CreateAppointmentValidator` (Application/Validators), `CreateAppointmentController`, `CancelAppointmentController`, `GetAppointmentsController` (Infrastructure/Http).

* **Trazabilidad y Registro de Auditoría Inmutable**
  * *Módulos involucrados:* Shared Infrastructure (Audit).
  * *Componentes:* `SystemAuditLogRepository` (Infrastructure/Persistence), `AuditLogger` (Infrastructure/Shared) que registra de forma inmutable todas las mutaciones críticas en `system_audit_log` en formato JSON (valores anteriores y posteriores).

* **Panel y Métricas de Dashboard Administrativo**
  * *Módulos involucrados:* Dashboard, Shared Infrastructure.
  * *Componentes:* `DashboardMetricsRepositoryInterface` (Domain), `PdoDashboardMetricsRepository` (Infrastructure/Persistence), `GetDashboardMetricsUseCase` (Application), `AdminMetricsController` (Infrastructure/Http), vista `admin-dashboard.php`.

* **Interfaz de Usuario Base (PWA y Panel de Administración)**
  * *Módulos involucrados:* Shared Infrastructure, Frontend.
  * *Componentes:* Vistas Blade-like en PHP puro (`public/views/`), layout global del administrador (`admin-layout.php`), layout del cliente (`app-layout.php`), hojas de estilo base (`admin-global.css`, `app-global.css`), clientes API en Vanilla JS (`admin-app.js`, `app-client.js`).

---

## Funcionalidades Parcialmente Implementadas

* **Mallas Horarias Ordinarias y Excepciones del Personal (Staffing)**
  * *Estado actual:* El esquema de base de datos MySQL tiene implementadas las tablas estructurales (`work_schedule`, `schedule_exception`, `professional_profile`, `holiday`) con sus respectivas restricciones de integridad CHECK. En PHP, los archivos correspondientes a las entidades de dominio y casos de uso existen como archivos vacíos con comentarios `// TODO: implementar`.
  * *Qué falta completar:* Hydratación y lógica de dominio en `WorkSchedule.php`, `ScheduleException.php`, `ProfessionalProfile.php`. Implementación de `StaffingRepositoryInterface` y su adaptador físico `PdoStaffingRepository` para persistir agendas ordinarias y excepciones. Lógica de negocio en `ConfigureScheduleUseCase`, `CreateScheduleExceptionUseCase` y el motor de generación de ranuras horarias disponibles `GetAvailableSlotsUseCase`. Controladores de HTTP (`GetSlotsController`, `SaveScheduleController`, `ScheduleExceptionController`).
  * *Dependencias:* Ninguna (Modulo base de infraestructura de mallas).

* **Soporte Offline y Caché en PWA (Offline-First)**
  * *Estado actual:* Se cuenta con el Service Worker (`sw.js`) y el manifiesto (`manifest.json`) en el directorio público, pero la lógica del service worker está vacía (solo define constantes base de caché y canal).
  * *Qué falta completar:* Implementar la estrategia de caché Stale-While-Revalidate en `sw.js` para los recursos estáticos y el catálogo de servicios. Implementar la cola de salida local (`outbox_queue`) utilizando IndexedDB y la API Background Sync para agendar citas offline, sincronizándolas al recuperar red.
  * *Dependencias:* PWA Frontend, endpoints REST del catálogo y agendamiento estables.

---

## Funcionalidades Pendientes

* **Módulo de Facturación y Puntos de Venta (Billing & POS)**
  * *Descripción breve:* Gestión financiera completa del negocio que incluye: cobro e inmutabilidad de abonos, registro e impresión de facturas, control de sesiones de caja chica (apertura, egresos misceláneos, arqueos y cierre diario de caja) y auditoría contable por sesión de sucursal.
  * *Dependencias:* Agendamiento Core (`CreateAppointmentUseCase`, `PdoAppointmentRepository` listos).

* **Procesamiento de Webhooks e Integración de Pagos Online**
  * *Descripción breve:* Recepción asíncrona de confirmaciones de pago en línea de pasarelas (ej. Stripe/MercadoPago), validación criptográfica de firmas, control de idempotencia mediante claves y registro de abonos parciales o totales.
  * *Dependencias:* Módulo de Facturación base (`Invoice`, `Payment` implementados).

* **Integración Conversacional y Webhook de WhatsApp Bot**
  * *Descripción breve:* Webhook receptor de mensajes de Meta Cloud API (`WhatsAppWebhookController.php`), verificación de firmas X-Hub-Signature-256, parser de payloads, persistencia de sesiones conversacionales en `wa_chat_session` para retener contexto del flujo y motor de chat transaccional.
  * *Dependencias:* Malla horaria (`GetAvailableSlotsUseCase`), Agendamiento Core (`CreateAppointmentUseCase`).

* **Procesamiento de Cola de Notificaciones Salientes (Workers)**
  * *Descripción breve:* Script por lotes CLI (`NotificationQueueWorker.php`) ejecutado por Cron para enviar recordatorios y confirmaciones automáticas de WhatsApp utilizando plantillas aprobadas por Meta. Debe usar bloqueos físicos (`flock`), evitar solapamientos y aplicar respaldo exponencial (Exponential Backoff).
  * *Dependencias:* WhatsApp Webhook y tablas de cola (`wa_notification_queue`, `wa_template`).

* **Calificación y Reseñas Post-Servicio (CRM)**
  * *Descripción breve:* Caso de uso `SubmitServiceRatingUseCase` y controlador en la API para capturar la satisfacción de los clientes (reseñas de 1 a 5 estrellas con comentarios) garantizando unicidad por cita y agregación por especialista.
  * *Dependencias:* Agendamiento Core (`CompleteServiceUseCase` implementado).

* **Módulo de Control de Inventario y Mermas**
  * *Descripción breve:* CRUD y lógica de control de stock de productos profesionales e insumos de cabina (`ManageServiceInventoryUseCase`), alertas de inventario bajo y mermas.
  * *Dependencias:* Ninguna.

* **Nómina y Libro de Comisiones del Staff**
  * *Descripción breve:* Libro contable auxiliar (`professional_commission_ledger`) para calcular y liquidar automáticamente comisiones devengadas por los profesionales sobre facturas liquidadas.
  * *Dependencias:* Módulo de Facturación (`Invoice`, `Payment`).

---

## Deuda Técnica

### Arquitectura
* **Ausencia de Objetos de Valor (Value Objects) en Domain:** Los archivos `Money.php`, `TimeRange.php`, `UuidVO.php` están declarados pero vacíos. Toda la lógica del sistema maneja tipos primitivos directamente en lugar de estructurar estas invariantes en el dominio.
* **Falta de Despachador de Eventos de Dominio:** `EventDispatcher.php` y `DomainEventInterface.php` están vacíos. Los eventos de dominio como `AppointmentCreatedEvent` no se disparan ni se procesan de forma asíncrona/desacoplada, forzando acoplamiento directo entre capas en los casos de uso.

### Backend
* **Lógica Incompleta en Casos de Uso Core:** Varios controladores HTTP del backend asumen la existencia de interfaces de persistencia y servicios que actualmente no tienen lógica operativa real en sus respectivos repositorios.
* **Controlador de Cierre de Sesión:** `LogoutController.php` no tiene lógica implementada para revocar el token JWT o invalidar la sesión en base de datos.

### Frontend
* **Ausencia de IndexedDB en PWA:** No existe persistencia local estructurada en la PWA del cliente para el almacenamiento de citas e historial offline, operando actualmente de forma 100% dependiente de red.
* **Falta de Sincronización en Segundo Plano:** El Service Worker no intercepta peticiones de mutación fallidas por falta de conectividad ni las encola para Background Sync.

### Base de Datos
* **Falta de Migraciones Automatizadas:** No existe un gestor de migraciones (como Phinx o Doctrine Migrations) ni seeders estructurados para datos iniciales de base de datos; la base de datos se despliega cargando manualmente un archivo SQL monolítico.

### DevOps
* **Mecanismos de Control de Concurrencia de Workers:** `NotificationQueueWorker.php` es una clase vacía. No implementa el mecanismo físico de semáforo con `flock` requerido para evitar el solapamiento de ejecuciones del Cron de Hostinger.

### Seguridad
* **Encriptación de Datos Clínicos:** `EncryptionService.php` está vacío. No se encriptan ni desencriptan las notas médicas y de alergias obligatorias del perfil de cliente (`medical_notes_allergies` en `client_profile`), exponiendo datos sensibles en texto plano en la base de datos.

### Testing
* **Ausencia Total de Pruebas Unitarias:** Las carpetas `tests/Unit/Application` y `tests/Unit/Domain` están completamente vacías. No existe cobertura de pruebas para las entidades ni reglas de negocio.
* **Prueba de Integración Fallida:** El único test de integración (`IAM_Login_Test.php`) falla en ejecución directa debido a la falta de un entorno de pruebas aislado (base de datos en memoria o fixture limpia) y el acoplamiento a credenciales locales.

---

## Roadmap Priorizado

### Prioridad Alta
* **Tarea H1: Implementación de la Capa de Dominio Base (Value Objects y Eventos)**
  * *Objetivo:* Completar la lógica de Value Objects (`Money`, `TimeRange`, `UuidVO`) y el despachador de eventos de dominio (`EventDispatcher`) para desacoplar el ecosistema.
  * *Dependencias:* Ninguna.
  * *Área responsable:* Backend.
* **Tarea H2: Motor de Mallas Horarias y Excepciones (Staffing Core)**
  * *Objetivo:* Desarrollar las entidades del dominio de personal, configurar mallas horarias, registrar bloqueos o vacaciones, y calcular ranuras disponibles (`GetAvailableSlotsUseCase`).
  * *Dependencias:* Tarea H1.
  * *Área responsable:* Backend / Database.
* **Tarea H3: Ciclo de Vida Avanzado del Agendamiento (Booking Core)**
  * *Objetivo:* Completar las operaciones de check-in de llegada (`ExecuteCheckInUseCase`), fin de servicio (`CompleteServiceUseCase`), inasistencias (`MarkNoShowUseCase`) y reprogramación atómica (`RescheduleAppointmentUseCase`).
  * *Dependencias:* Tarea H2.
  * *Área responsable:* Backend.

### Prioridad Media
* **Tarea M1: Facturación Inmutable, POS y Caja Chica (Billing Core)**
  * *Objetivo:* Implementar entidades de facturación, abonos, e inmutabilidad de pagos de solo inserción, con lógica de apertura, flujos de caja y arqueos.
  * *Dependencias:* Tarea H3.
  * *Área responsable:* Backend / Database.
* **Tarea M2: Webhook de Pagos Online e Idempotencia**
  * *Objetivo:* Desarrollar el webhook receptor de pasarela de pagos con control de idempotencia transaccional y bloqueos pesimistas para evitar cobros dobles.
  * *Dependencias:* Tarea M1.
  * *Área responsable:* Backend / DevOps.
* **Tarea M3: Service Worker Avanzado y Soporte Offline de PWA**
  * *Objetivo:* Implementar almacenamiento IndexedDB en el cliente, caché Stale-While-Revalidate en `sw.js` y sincronización Background Sync.
  * *Dependencias:* Tarea H3, API estable.
  * *Área responsable:* Frontend / PWA.

### Prioridad Baja
* **Tarea B1: WhatsApp Webhook, parser y Máquina de Estados**
  * *Objetivo:* Desarrollar el webhook receptor de WhatsApp con validación de firmas, parser de payloads Meta y máquina de estados conversacionales interactiva en BD.
  * *Dependencias:* Tarea H2.
  * *Área responsable:* Integraciones / Backend.
* **Tarea B2: Cola de Notificaciones y CLI Batch Worker**
  * *Objetivo:* Implementar el worker CLI resiliente con límites de ejecución de 45 segundos, semáforos flock y reintentos automáticos con respaldo exponencial.
  * *Dependencias:* Tarea B1.
  * *Área responsable:* Integraciones / DevOps.
* **Tarea B3: Cobertura de Testing y Mocking de Base de Datos**
  * *Objetivo:* Construir la suite de pruebas unitarias de negocio y resolver los mocks del ciclo de vida del test de integración de base de datos.
  * *Dependencias:* Todas las anteriores.
  * *Área responsable:* Testing / DevOps.

---

## Tareas Paralelizables

### Backend
* Desarrollo de la capa de Dominio Base (Value Objects y Bus de Eventos) puede ejecutarse de forma aislada.
* Implementación del CRUD de catálogo de servicios e inventario (`ManageServiceInventoryUseCase`) es independiente del agendamiento y facturación.
* Caso de uso para reseñas post-servicio (`SubmitServiceRatingUseCase`) y su persistencia.

### Frontend
* Maquetación y estilos Vanilla CSS de los paneles de administración y vistas cliente.
* Integración del Service Worker básico y lógica de visualización del catálogo offline en IndexedDB (independiente de la lógica transaccional del backend).

### Base de Datos
* Creación de scripts de seeders y configuradores de datos maestros iniciales (servicios, categorías, roles y permisos).

### APIs
* Desarrollo del endpoint `LogoutController` para revocación de JWT e invalidación de sesiones de usuario en base de datos.

### Integraciones
* Desarrollo del cliente de Meta Cloud API (`MetaApiClient`) y su respectivo formateador de plantillas HSM (independiente del agendamiento conversacional).

### DevOps
* Implementación de scripts de rotación de logs en producción e integración de logs estructurados PSR-3.

### Seguridad
* Implementación del servicio de encriptación simétrica bidireccional (`EncryptionService`) para datos clínicos de clientes.

### Testing
* Creación de pruebas unitarias para entidades de negocio ya existentes (`User`, `Role`, `Appointment`).
