1. Módulo de Gestión de Identidad y Acceso (IAM - Identity & Access Management)
1.1. Responsabilidad Única y EstrictaEste módulo es el guardián perimetral y transversal del sistema. Su única responsabilidad es resolver la autenticación de usuarios (humanos y agentes de software), la emisión y validación de tokens seguros (JWT), el control de acceso basado en roles (RBAC) y el registro inmutable de auditoría de accesos. Ninguna regla de negocio comercial (como precios o citas) puede filtrarse en este componente.
1.2. Casos de Uso Asignados (Application Layer)RegisterNewClientUseCase (RF-01): Registra un usuario base y su perfil de cliente asociado.AuthenticateUserUseCase (RF-02): Valida credenciales contra hash seguro y retorna el JWT con los claims del rol.AuthorizeRouteUseCase: Interceptor/Middleware que valida si el rol asignado posee el permiso requerido antes de ejecutar el controlador.
1.3. Entidades de Código Asociadas (Domain Layer)User (Raíz del Agregado)Role (Entidad)Permission (Entidad)
1.4. Mapeo Exacto y Amarre Físico a MySQL 8Antigravity 2.0 debe acoplar el código estrictamente a las siguientes tablas físicas:Tabla user:Campos clave: user_id (BIGINT UNSIGNED, PK), username (VARCHAR, UNIQUE), password_hash (VARCHAR), email (VARCHAR, UNIQUE), is_active (TINYINT/BOOLEAN).Regra de persistencia: Las contraseñas deben procesarse mediante password_hash() con el algoritmo PASSWORD_DEFAULT (bcrypt) en PHP. Queda estrictamente prohibido almacenar texto plano.Tablas de Control RBAC (role, permission, user_role, role_permission):Campos de acoplamiento: role_id, permission_id.Restricción de integridad: Las tablas pivote deben respetar la restricción ON DELETE CASCADE para limpiar relaciones en caso de revocar roles, pero la tabla user_role mantendrá integridad restrictiva sobre user_id para evitar la eliminación de usuarios con historial operativo.
2. Módulo de Catálogo y Tarifario (Catalog & Pricing)
2.1. Responsabilidad Única y EstrictaCentralizar la definición, mutación, control de vigencia y consulta del inventario de servicios y tratamientos de la estética, así como las reglas de negocio asociadas a las campañas de descuentos y promociones. Garantiza que las consultas masivas del catálogo sean altamente eficientes y agnósticas al canal de origen (PWA o WhatsApp Bot).
2.2. Casos de Uso Asignados (Application Layer)BrowseServiceCatalogUseCase (RF-04): Recupera los servicios activos aplicando filtros de búsqueda y ordenamiento.ManageServiceInventoryUseCase (RF-13): CRUD exclusivo de administradores para dar de alta, modificar o aplicar bajas lógicas a servicios.CreatePromotionCampaignUseCase (RF-21): Registra códigos de descuento y parametriza sus fechas de validez.
2.3. Entidades de Código Asociadas (Domain Layer)Service (Raíz del Agregado)Promotion (Raíz del Agregado)
2.4. Mapeo Exacto y Amarre Físico a MySQL 8Tabla service:Campos clave: service_id (INT UNSIGNED, PK), name (VARCHAR), price (DECIMAL/DOUBLE para precisión monetaria), duration_minutes (INT), is_active (TINYINT).Invariante en Código: Antigravity debe forzar una validación donde duration_minutes sea $> 0$ antes de realizar el INSERT o UPDATE.Tablas promotion y promotion_service:Campos clave: promotion_id (INT UNSIGNED, PK), discount_percentage (DECIMAL), start_date (DATE), end_date (DATE).Mapeo de Relación: La tabla asociativa promotion_service amarra qué promoción aplica a qué servicios específicos mediante promotion_id y service_id. El código debe validar la vigencia comparando la fecha actual con los campos de la base de datos antes de aplicar el descuento a un presupuesto.3. Módulo de Especialistas y Calendario Operativo (Staffing & Availability)
3.1. Responsabilidad Única y EstrictaAdministrar los perfiles del personal técnico (esteticistas/estilistas), sus competencias técnicas (qué servicios están capacitados para realizar) y su disponibilidad de tiempo ordinaria y extraordinaria. Es el proveedor de datos de disponibilidad que el motor transaccional de citas consultará de forma obligatoria.
3.2. Casos de Uso Asignados (Application Layer)ConfigureProfessionalScheduleUseCase (RF-14): Guarda o actualiza los días y rangos horarios de trabajo de un especialista en una sucursal específica.RegisterAgendaExceptionUseCase (RF-14): Inserta bloqueos temporales en el calendario (vacaciones, ausencias, descansos).
3.3. Entidades de Código Asociadas (Domain Layer)ProfessionalProfile (Raíz del Agregado)WorkSchedule (Entidad Dependiente)AgendaException (Entidad Dependiente)3.4. Mapeo Exacto y Amarre Físico a MySQL 8 
Este componente es el más crítico para el rendimiento del motor de búsqueda. El código debe interactuar milimétricamente con: Tabla professional_profile:Campos clave: professional_profile_id (BIGINT UNSIGNED, PK), user_id (BIGINT UNSIGNED, FK hacia user).Tabla work_schedule:Campos clave: work_schedule_id (BIGINT UNSIGNED, PK), branch_id (INT UNSIGNED, FK), professional_profile_id (BIGINT UNSIGNED, FK), day_of_week (INT), start_time (TIME), end_time (TIME), lunch_start_time (TIME), lunch_end_time (TIME).Amarre de Restricciones del DDL: El código PHP debe emular y respetar los CONSTRAINT nativos de tu SQL:chk_work_day: El valor de day_of_week debe estar estrictamente entre 0 y 6 (Domingo a Sábado).chk_work_hours: end_time $>$ start_time.chk_lunch_hours: lunch_end_time $>$ lunch_start_time (o permitir nulos si no hay pausa de almuerzo). 
Aprovechamiento de Índices: Las consultas que realice Antigravity para validar disponibilidad deben apoyarse obligatoriamente en el índice compuesto provisto en tu DDL: KEY idx_schedule_matrix (branch_id, day_of_week, professional_profile_id). Esto garantiza respuestas de la API en microsegundos tanto para la PWA como para el Bot de WhatsApp.



4. Módulo Transaccional de Agendamiento (Booking Core)
4.1. Responsabilidad Única y Estricta
Es el núcleo transaccional de alta concurrencia del sistema. Su única responsabilidad es gestionar el ciclo de vida de las reservas de citas (appointment), aplicar el control de concurrencia algorítmica para evitar el overbooking, validar la integridad de los tiempos de servicio e interactuar de forma directa con los estados operativos de atención en sucursal.

4.2. Casos de Uso Asignados (Application Layer)
CreateAppointmentUseCase (RF-06, RF-07, RF-21): Registra una nueva cita validando de forma atómica la ranura horaria, calculando dinámicamente la hora final basada en la duración del servicio.

CancelAppointmentUseCase (RF-09): Ejecuta la baja lógica de una cita validando la regla restrictiva de las 24 horas de anticipación.

RescheduleAppointmentUseCase (RF-08): Muta el rango horario de una cita existente liberando el espacio anterior y bloqueando el nuevo mediante un único paso transaccional.

ExecuteCheckInUseCase (RF-17): Registra la llegada física del cliente a la sucursal cambiando el estado de la cita a IN_PROGRESS.

CompleteServiceUseCase (RF-18): Marca la finalización técnica del tratamiento en cabina por parte del profesional.

4.3. Entidades de Código Asociadas (Domain Layer)
Appointment (Raíz del Agregado)

ClientProfile (Entidad)

4.4. Mapeo Exacto y Amarre Físico a MySQL 8
Tabla client_profile:

Campos clave: client_profile_id (BIGINT UNSIGNED, PK), user_id (BIGINT UNSIGNED, FK hacia user).

Regra de persistencia: Guarda información clínica extendida e historial estético complementario.

Tabla appointment:

Campos clave: appointment_id (BIGINT UNSIGNED, PK), client_profile_id (BIGINT UNSIGNED, FK), professional_profile_id (BIGINT UNSIGNED, FK), service_id (INT UNSIGNED, FK), branch_id (INT UNSIGNED, FK), promotion_id (INT UNSIGNED, FK, NULLABLE), start_time (DATETIME), end_time (DATETIME), status (VARCHAR/ENUM), notes (TEXT).

Control de Concurrencia Crítico: Para blindar el sistema contra condiciones de carrera concurrentes (múltiples clics simultáneos desde la PWA o ráfagas de mensajes del Bot de WhatsApp), Antigravity 2.0 debe implementar una consulta transaccional de bloqueo pesimista en el repositorio PHP:

SQL
SELECT appointment_id FROM appointment 
WHERE professional_profile_id = :professional_profile_id 
  AND status NOT IN ('CANCELLED', 'NOSHOW')
  AND (:start_time < end_time AND :end_time > start_time) 
FOR UPDATE;
    Si esta consulta retorna algún registro, el caso de uso debe abortar inmediatamente lanzando una excepción de negocio (`CollisionException`), impidiendo la inserción física.

---

## 5. Módulo Financiero y Caja Fuerte (Billing & POS)

### 5.1. Responsabilidad Única y Estricta
Administrar los flujos de dinero entrantes del sistema. Se encarga de procesar los pagos digitales provenientes de pasarelas Web (webhooks asíncronos), registrar los cierres y aperturas de caja física en mostrador (POS), congelar los precios pactados y procesar reembolsos lógicos. No conoce detalles operativos de cómo se realiza el servicio, solo su costo financiero.

### 5.2. Casos de Uso Asignados (Application Layer)
* `ProcessOnlinePaymentWebhookUseCase` (RF-12): Procesa la respuesta asíncrona de la pasarela de pago, validando firmas de seguridad del payload y mutando el estado de pago.
* `RegisterPosPaymentUseCase` (RF-12, RF-18): Permite a la recepcionista registrar cobros en efectivo, tarjetas físicas o transferencias directamente en el punto de venta de la sucursal.
* `GenerateDailyCashClosingUseCase` (RF-19): Consolida los ingresos brutos agrupados por método de pago en una jornada laboral específica.

### 5.3. Entidades de Código Asociadas (Domain Layer)
* `Payment` (Raíz del Agregado)

### 5.4. Mapeo Exacto y Amarre Físico a MySQL 8
* **Tabla `payment`:**
  * **Campos clave:** `payment_id` (BIGINT UNSIGNED, PK), `appointment_id` (BIGINT UNSIGNED, FK), `amount` (DECIMAL(10,2)), `payment_method` (VARCHAR), `payment_status` (VARCHAR), `transaction_reference` (VARCHAR, UNIQUE), `created_at` (DATETIME).
  * **Inmutabilidad Financiera:** Los registros en esta tabla son estrictamente de **solo inserción (Append-Only)**. Si un pago debe ser anulado o devuelto, queda prohibido ejecutar un `UPDATE` sobre el campo `amount`. Se debe insertar un nuevo registro de pago con signo negativo o mutar el campo `payment_status` a `'REFUNDED'`, garantizando que las auditorías contables cuadren a nivel de centavos.

---

## 6. Módulo Analítico y Experiencia del Cliente (CRM, Alerts & Analytics)

### 6.1. Responsabilidad Única y Estricta
Gestionar la comunicación saliente asíncrona con el cliente, recopilar métricas de satisfacción post-servicio (Ratings) y registrar la actividad forense del sistema mediante logs inmutables. Actúa de forma reactiva escuchando los eventos de dominio generados por los otros módulos.

### 6.2. Casos de Uso Asignados (Application Layer)
* `EnqueueNotificationUseCase` (RF-10, RF-11): Inserta mensajes en la cola de salida para recordatorios de citas o alertas de confirmación.
* `SubmitServiceRatingUseCase` (RF-20): Registra la calificación y comentarios otorgados por el cliente posterior a su atención.
* `PersistAuditLogUseCase`: Captura de manera inmutable cualquier acción de escritura realizada en la API para persistir un snapshot histórico de los datos.

### 6.3. Entidades de Código Asociadas (Domain Layer)
* `Rating` (Entidad)
* `Notification` (Entidad)
* `AuditDataLog` (Value Object de Infraestructura)

### 6.4. Mapeo Exacto y Amarre Físico a MySQL 8
* **Tabla `rating`:**
  * **Campos clave:** `rating_id` (BIGINT UNSIGNED, PK), `appointment_id` (BIGINT UNSIGNED, FK), `score` (INT), `comments` (TEXT).
  * **Restricción de Negocio:** El campo `score` debe validarse en código PHP para restringir valores estrictamente entre `1` y `5` estrellas.
* **Tabla `notification`:**
  * **Campos clave:** `notification_id` (BIGINT UNSIGNED, PK), `user_id` (BIGINT UNSIGNED, FK), `type` (VARCHAR), `recipient` (VARCHAR), `content` (TEXT), `is_sent` (TINYINT), `scheduled_at` (DATETIME).
* **Tabla `audit_data_log`:**
  * **Campos clave:** `audit_log_id` (BIGINT UNSIGNED, PK), `user_id` (BIGINT UNSIGNED, FK, operador), `action` (VARCHAR), `table_name` (VARCHAR), `record_id` (BIGINT), `old_values` (JSON), `new_values` (JSON), `created_at` (DATETIME).
  * **Regra de Oro para Antigravity:** Esta tabla es sagrada. Cualquier controlador o caso de uso que ejecute una mutación (`INSERT`, `UPDATE`) debe disparar de forma automática una inserción en `audit_data_log` guardando el estado anterior y posterior en formato JSON.

---

## 7. Matriz de Dependencias Cruzadas (Anti-Circular Dependencies)

Para asegurar el cumplimiento de **Clean Architecture**, se define esta matriz unidireccional de control. Los módulos de la izquierda **pueden invocar** a los módulos de las columnas superiores, pero nunca a la inversa. El incumplimiento de esta regla rompería el desacoplamiento.

| Módulo Core | 1. IAM | 2. Catalog | 3. Staffing | 4. Booking | 5. Billing | 6. Analytics |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **1. IAM** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **2. Catalog** | 🟢 | ❌ | ❌ | ❌ | ❌ | ❌ |
| **3. Staffing** | 🟢 | 🟢 | ❌ | ❌ | ❌ | ❌ |
| **4. Booking** | 🟢 | 🟢 | 🟢 | ❌ | ❌ | ❌ |
| **5. Billing** | 🟢 | ❌ | ❌ | 🟢 | ❌ | ❌ |
| **6. Analytics** | 🟢 | ❌ | ❌ | 🟢 | 🟢 | ❌ |

### Reglas Técnicas de Interpretación para la IA:
1. **IAM es la Base Extrema:** Ningún módulo puede ser requerido por IAM. IAM es completamente huérfano de dependencias comerciales.
2. **Booking Core como Orquestador:** Booking puede consumir servicios de Staffing (para validar horarios) y Catalog (para conocer duraciones de servicios), pero Staffing y Catalog jamás deben instanciar clases o repositorios pertenecientes a Booking.
3. **Comunicación Inversa vía Eventos:** Si el módulo de *Booking* necesita avisarle al módulo de *Analytics* que envíe una notificación, **no debe hacer una llamada directa a una clase de Analytics**. En su lugar, el módulo de *Booking* despacha un evento genérico `AppointmentScheduled` al bus de eventos del sistema, y el módulo de *Analytics* (que actúa como un escucha o *Listener*) captura el evento de forma totalmente desacoplada.

---

Con este mapeo detallado de base de datos y restricciones lógicas, el archivo de diseño modular