1. MÃ³dulo de GestiÃ³n de Identidad y Acceso (IAM - Identity & Access Management)
1.1. Responsabilidad Ãšnica y EstrictaEste mÃ³dulo es el guardiÃ¡n perimetral y transversal del sistema. Su Ãºnica responsabilidad es resolver la autenticaciÃ³n de usuarios (humanos y agentes de software), la emisiÃ³n y validaciÃ³n de tokens seguros (JWT), el control de acceso basado en roles (RBAC) y el registro inmutable de auditorÃ­a de accesos. Ninguna regla de negocio comercial (como precios o citas) puede filtrarse en este componente.
1.2. Casos de Uso Asignados (Application Layer)RegisterNewClientUseCase (RF-01): Registra un usuario base y su perfil de cliente asociado.AuthenticateUserUseCase (RF-02): Valida credenciales contra hash seguro y retorna el JWT con los claims del rol.AuthorizeRouteUseCase: Interceptor/Middleware que valida si el rol asignado posee el permiso requerido antes de ejecutar el controlador.
1.3. Entidades de CÃ³digo Asociadas (Domain Layer)User (RaÃ­z del Agregado)Role (Entidad)Permission (Entidad)
1.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8Antigravity 2.0 debe acoplar el cÃ³digo estrictamente a las siguientes tablas fÃ­sicas:Tabla user:Campos clave: user_id (BIGINT UNSIGNED, PK), username (VARCHAR, UNIQUE), password_hash (VARCHAR), email (VARCHAR, UNIQUE), is_active (TINYINT/BOOLEAN).Regra de persistencia: Las contraseÃ±as deben procesarse mediante password_hash() con el algoritmo PASSWORD_DEFAULT (bcrypt) en PHP. Queda estrictamente prohibido almacenar texto plano.Tablas de Control RBAC (role, permission, user_role, role_permission):Campos de acoplamiento: role_id, permission_id.RestricciÃ³n de integridad: Las tablas pivote deben respetar la restricciÃ³n ON DELETE CASCADE para limpiar relaciones en caso de revocar roles, pero la tabla user_role mantendrÃ¡ integridad restrictiva sobre user_id para evitar la eliminaciÃ³n de usuarios con historial operativo.
2. MÃ³dulo de CatÃ¡logo y Tarifario (Catalog & Pricing)
2.1. Responsabilidad Ãšnica y EstrictaCentralizar la definiciÃ³n, mutaciÃ³n, control de vigencia y consulta del inventario de servicios y tratamientos de la estÃ©tica, asÃ­ como las reglas de negocio asociadas a las campaÃ±as de descuentos y promociones. Garantiza que las consultas masivas del catÃ¡logo sean altamente eficientes y agnÃ³sticas al canal de origen (PWA o WhatsApp Bot).
2.2. Casos de Uso Asignados (Application Layer)BrowseServiceCatalogUseCase (RF-04): Recupera los servicios activos aplicando filtros de bÃºsqueda y ordenamiento.ManageServiceInventoryUseCase (RF-13): CRUD exclusivo de administradores para dar de alta, modificar o aplicar bajas lÃ³gicas a servicios.CreatePromotionCampaignUseCase (RF-21): Registra cÃ³digos de descuento y parametriza sus fechas de validez.
2.3. Entidades de CÃ³digo Asociadas (Domain Layer)Service (RaÃ­z del Agregado)Promotion (RaÃ­z del Agregado)
2.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8Tabla service:Campos clave: service_id (INT UNSIGNED, PK), name (VARCHAR), price (DECIMAL/DOUBLE para precisiÃ³n monetaria), duration_minutes (INT), is_active (TINYINT).Invariante en CÃ³digo: Antigravity debe forzar una validaciÃ³n donde duration_minutes sea $> 0$ antes de realizar el INSERT o UPDATE.Tablas promotion y promotion_service:Campos clave: promotion_id (INT UNSIGNED, PK), discount_percentage (DECIMAL), start_date (DATE), end_date (DATE).Mapeo de RelaciÃ³n: La tabla asociativa promotion_service amarra quÃ© promociÃ³n aplica a quÃ© servicios especÃ­ficos mediante promotion_id y service_id. El cÃ³digo debe validar la vigencia comparando la fecha actual con los campos de la base de datos antes de aplicar el descuento a un presupuesto.3. MÃ³dulo de Especialistas y Calendario Operativo (Staffing & Availability)
3.1. Responsabilidad Ãšnica y EstrictaAdministrar los perfiles del personal tÃ©cnico (esteticistas/estilistas), sus competencias tÃ©cnicas (quÃ© servicios estÃ¡n capacitados para realizar) y su disponibilidad de tiempo ordinaria y extraordinaria. Es el proveedor de datos de disponibilidad que el motor transaccional de citas consultarÃ¡ de forma obligatoria.
3.2. Casos de Uso Asignados (Application Layer)ConfigureProfessionalScheduleUseCase (RF-14): Guarda o actualiza los dÃ­as y rangos horarios de trabajo de un especialista en una sucursal especÃ­fica.RegisterAgendaExceptionUseCase (RF-14): Inserta bloqueos temporales en el calendario (vacaciones, ausencias, descansos).
3.3. Entidades de CÃ³digo Asociadas (Domain Layer)ProfessionalProfile (RaÃ­z del Agregado)WorkSchedule (Entidad Dependiente)AgendaException (Entidad Dependiente)3.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8 
Este componente es el mÃ¡s crÃ­tico para el rendimiento del motor de bÃºsqueda. El cÃ³digo debe interactuar milimÃ©tricamente con: Tabla professional_profile:Campos clave: professional_profile_id (BIGINT UNSIGNED, PK), user_id (BIGINT UNSIGNED, FK hacia user).Tabla work_schedule:Campos clave: work_schedule_id (BIGINT UNSIGNED, PK), branch_id (INT UNSIGNED, FK), professional_profile_id (BIGINT UNSIGNED, FK), day_of_week (INT), start_time (TIME), end_time (TIME), lunch_start_time (TIME), lunch_end_time (TIME).Amarre de Restricciones del DDL: El cÃ³digo PHP debe emular y respetar los CONSTRAINT nativos de tu SQL:chk_work_day: El valor de day_of_week debe estar estrictamente entre 0 y 6 (Domingo a SÃ¡bado).chk_work_hours: end_time $>$ start_time.chk_lunch_hours: lunch_end_time $>$ lunch_start_time (o permitir nulos si no hay pausa de almuerzo). 
Aprovechamiento de Ãndices: Las consultas que realice Antigravity para validar disponibilidad deben apoyarse obligatoriamente en el Ã­ndice compuesto provisto en tu DDL: KEY idx_schedule_matrix (branch_id, day_of_week, professional_profile_id). Esto garantiza respuestas de la API en microsegundos tanto para la PWA como para el Bot de WhatsApp.



4. MÃ³dulo Transaccional de Agendamiento (Booking Core)
4.1. Responsabilidad Ãšnica y Estricta
Es el nÃºcleo transaccional de alta concurrencia del sistema. Su Ãºnica responsabilidad es gestionar el ciclo de vida de las reservas de citas (appointment), aplicar el control de concurrencia algorÃ­tmica para evitar el overbooking, validar la integridad de los tiempos de servicio e interactuar de forma directa con los estados operativos de atenciÃ³n en sucursal.

4.2. Casos de Uso Asignados (Application Layer)
CreateAppointmentUseCase (RF-06, RF-07, RF-21): Registra una nueva cita validando de forma atÃ³mica la ranura horaria, calculando dinÃ¡micamente la hora final basada en la duraciÃ³n del servicio.

CancelAppointmentUseCase (RF-09): Ejecuta la baja lÃ³gica de una cita validando la regla restrictiva de las 24 horas de anticipaciÃ³n.

RescheduleAppointmentUseCase (RF-08): Muta el rango horario de una cita existente liberando el espacio anterior y bloqueando el nuevo mediante un Ãºnico paso transaccional.

ExecuteCheckInUseCase (RF-17): Registra la llegada fÃ­sica del cliente a la sucursal cambiando el estado de la cita a IN_PROGRESS.

CompleteServiceUseCase (RF-18): Marca la finalizaciÃ³n tÃ©cnica del tratamiento en cabina por parte del profesional.

4.3. Entidades de CÃ³digo Asociadas (Domain Layer)
Appointment (RaÃ­z del Agregado)

ClientProfile (Entidad)

4.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8
Tabla client_profile:

Campos clave: client_profile_id (BIGINT UNSIGNED, PK), user_id (BIGINT UNSIGNED, FK hacia user).

Regra de persistencia: Guarda informaciÃ³n clÃ­nica extendida e historial estÃ©tico complementario.

Tabla appointment:

Campos clave: appointment_id (BIGINT UNSIGNED, PK), client_profile_id (BIGINT UNSIGNED, FK), professional_profile_id (BIGINT UNSIGNED, FK), service_id (INT UNSIGNED, FK), branch_id (INT UNSIGNED, FK), promotion_id (INT UNSIGNED, FK, NULLABLE), start_time (DATETIME), end_time (DATETIME), status (VARCHAR/ENUM), notes (TEXT).

Control de Concurrencia CrÃ­tico: Para blindar el sistema contra condiciones de carrera concurrentes (mÃºltiples clics simultÃ¡neos desde la PWA o rÃ¡fagas de mensajes del Bot de WhatsApp), Antigravity 2.0 debe implementar una consulta transaccional de bloqueo pesimista en el repositorio PHP:

SQL
SELECT appointment_id FROM appointment 
WHERE professional_profile_id = :professional_profile_id 
  AND status NOT IN ('CANCELLED', 'NOSHOW')
  AND (:start_time < end_time AND :end_time > start_time) 
FOR UPDATE;
    Si esta consulta retorna algÃºn registro, el caso de uso debe abortar inmediatamente lanzando una excepciÃ³n de negocio (`CollisionException`), impidiendo la inserciÃ³n fÃ­sica.

---

## 5. MÃ³dulo Financiero y Caja Fuerte (Billing & POS)

### 5.1. Responsabilidad Ãšnica y Estricta
Administrar los flujos de dinero entrantes del sistema. Se encarga de procesar los pagos digitales provenientes de pasarelas Web (webhooks asÃ­ncronos), registrar los cierres y aperturas de caja fÃ­sica en mostrador (POS), congelar los precios pactados y procesar reembolsos lÃ³gicos. No conoce detalles operativos de cÃ³mo se realiza el servicio, solo su costo financiero.

### 5.2. Casos de Uso Asignados (Application Layer)
* `ProcessOnlinePaymentWebhookUseCase` (RF-12): Procesa la respuesta asÃ­ncrona de la pasarela de pago, validando firmas de seguridad del payload y mutando el estado de pago.
* `RegisterPosPaymentUseCase` (RF-12, RF-18): Permite a la recepcionista registrar cobros en efectivo, tarjetas fÃ­sicas o transferencias directamente en el punto de venta de la sucursal.
* `GenerateDailyCashClosingUseCase` (RF-19): Consolida los ingresos brutos agrupados por mÃ©todo de pago en una jornada laboral especÃ­fica.

### 5.3. Entidades de CÃ³digo Asociadas (Domain Layer)
* `Payment` (RaÃ­z del Agregado)

### 5.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8
* **Tabla `payment`:**
  * **Campos clave:** `payment_id` (BIGINT UNSIGNED, PK), `appointment_id` (BIGINT UNSIGNED, FK), `amount` (DECIMAL(10,2)), `payment_method` (VARCHAR), `payment_status` (VARCHAR), `transaction_reference` (VARCHAR, UNIQUE), `created_at` (DATETIME).
  * **Inmutabilidad Financiera:** Los registros en esta tabla son estrictamente de **solo inserciÃ³n (Append-Only)**. Si un pago debe ser anulado o devuelto, queda prohibido ejecutar un `UPDATE` sobre el campo `amount`. Se debe insertar un nuevo registro de pago con signo negativo o mutar el campo `payment_status` a `'REFUNDED'`, garantizando que las auditorÃ­as contables cuadren a nivel de centavos.

---

## 6. MÃ³dulo AnalÃ­tico y Experiencia del Cliente (CRM, Alerts & Analytics)

### 6.1. Responsabilidad Ãšnica y Estricta
Gestionar la comunicaciÃ³n saliente asÃ­ncrona con el cliente, recopilar mÃ©tricas de satisfacciÃ³n post-servicio (Ratings) y registrar la actividad forense del sistema mediante logs inmutables. ActÃºa de forma reactiva escuchando los eventos de dominio generados por los otros mÃ³dulos.

### 6.2. Casos de Uso Asignados (Application Layer)
* `EnqueueNotificationUseCase` (RF-10, RF-11): Inserta mensajes en la cola de salida para recordatorios de citas o alertas de confirmaciÃ³n.
* `SubmitServiceRatingUseCase` (RF-20): Registra la calificaciÃ³n y comentarios otorgados por el cliente posterior a su atenciÃ³n.
* `PersistAuditLogUseCase`: Captura de manera inmutable cualquier acciÃ³n de escritura realizada en la API para persistir un snapshot histÃ³rico de los datos.

### 6.3. Entidades de CÃ³digo Asociadas (Domain Layer)
* `Rating` (Entidad)
* `Notification` (Entidad)
* `AuditDataLog` (Value Object de Infraestructura)

### 6.4. Mapeo Exacto y Amarre FÃ­sico a MySQL 8
* **Tabla `rating`:**
  * **Campos clave:** `rating_id` (BIGINT UNSIGNED, PK), `appointment_id` (BIGINT UNSIGNED, FK), `score` (INT), `comments` (TEXT).
  * **RestricciÃ³n de Negocio:** El campo `score` debe validarse en cÃ³digo PHP para restringir valores estrictamente entre `1` y `5` estrellas.
* **Tabla `notification`:**
  * **Campos clave:** `notification_id` (BIGINT UNSIGNED, PK), `user_id` (BIGINT UNSIGNED, FK), `type` (VARCHAR), `recipient` (VARCHAR), `content` (TEXT), `is_sent` (TINYINT), `scheduled_at` (DATETIME).
* **Tabla `audit_data_log`:**
  * **Campos clave:** `audit_log_id` (BIGINT UNSIGNED, PK), `user_id` (BIGINT UNSIGNED, FK, operador), `action` (VARCHAR), `table_name` (VARCHAR), `record_id` (BIGINT), `old_values` (JSON), `new_values` (JSON), `created_at` (DATETIME).
  * **Regra de Oro para Antigravity:** Esta tabla es sagrada. Cualquier controlador o caso de uso que ejecute una mutaciÃ³n (`INSERT`, `UPDATE`) debe disparar de forma automÃ¡tica una inserciÃ³n en `audit_data_log` guardando el estado anterior y posterior en formato JSON.

---

## 7. Matriz de Dependencias Cruzadas (Anti-Circular Dependencies)

Para asegurar el cumplimiento de **Clean Architecture**, se define esta matriz unidireccional de control. Los mÃ³dulos de la izquierda **pueden invocar** a los mÃ³dulos de las columnas superiores, pero nunca a la inversa. El incumplimiento de esta regla romperÃ­a el desacoplamiento.

| MÃ³dulo Core | 1. IAM | 2. Catalog | 3. Staffing | 4. Booking | 5. Billing | 6. Analytics |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **1. IAM** | âŒ | âŒ | âŒ | âŒ | âŒ | âŒ |
| **2. Catalog** | ðŸŸ¢ | âŒ | âŒ | âŒ | âŒ | âŒ |
| **3. Staffing** | ðŸŸ¢ | ðŸŸ¢ | âŒ | âŒ | âŒ | âŒ |
| **4. Booking** | ðŸŸ¢ | ðŸŸ¢ | ðŸŸ¢ | âŒ | âŒ | âŒ |
| **5. Billing** | ðŸŸ¢ | âŒ | âŒ | ðŸŸ¢ | âŒ | âŒ |
| **6. Analytics** | ðŸŸ¢ | âŒ | âŒ | ðŸŸ¢ | ðŸŸ¢ | âŒ |

### Reglas TÃ©cnicas de InterpretaciÃ³n para la IA:
1. **IAM es la Base Extrema:** NingÃºn mÃ³dulo puede ser requerido por IAM. IAM es completamente huÃ©rfano de dependencias comerciales.
2. **Booking Core como Orquestador:** Booking puede consumir servicios de Staffing (para validar horarios) y Catalog (para conocer duraciones de servicios), pero Staffing y Catalog jamÃ¡s deben instanciar clases o repositorios pertenecientes a Booking.
3. **ComunicaciÃ³n Inversa vÃ­a Eventos:** Si el mÃ³dulo de *Booking* necesita avisarle al mÃ³dulo de *Analytics* que envÃ­e una notificaciÃ³n, **no debe hacer una llamada directa a una clase de Analytics**. En su lugar, el mÃ³dulo de *Booking* despacha un evento genÃ©rico `AppointmentScheduled` al bus de eventos del sistema, y el mÃ³dulo de *Analytics* (que actÃºa como un escucha o *Listener*) captura el evento de forma totalmente desacoplada.

---

Con este mapeo detallado de base de datos y restricciones lÃ³gicas, el archivo de diseÃ±o modular
