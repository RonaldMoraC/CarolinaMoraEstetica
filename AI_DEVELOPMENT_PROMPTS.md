# AI DEVELOPMENT PROMPTS

---

# TAREA 01

## Nombre
Capa de Dominio Base: Value Objects y Bus de Eventos Desacoplado (Deuda Técnica)

## Rol
Backend Engineer Senior / Software Architect

## Contexto
El sistema sigue principios de Clean Architecture y DDD (Domain-Driven Design). Actualmente, las invariantes de negocio para tipos de datos complejos en la capa Domain están vacías (contienen placeholders de `TODO`). Se requiere implementar los Value Objects base (`Money`, `TimeRange`, `UuidVO`) y la infraestructura de eventos de dominio (`EventDispatcher`, `DomainEventInterface`) para desacoplar el core de agendamiento de los otros módulos (como alertas y analíticas).

## Objetivo
Implementar la lógica completa de los Value Objects y el despachador de eventos de dominio.

## Alcance
* **Modificable:**
  * `src/Domain/Shared/ValueObjects/Money.php`
  * `src/Domain/Shared/ValueObjects/TimeRange.php`
  * `src/Domain/Shared/ValueObjects/UuidVO.php`
  * `src/Domain/Shared/Events/DomainEventInterface.php`
  * `src/Domain/Shared/Events/EventDispatcher.php`
  * `src/Domain/Shared/Exceptions/DomainException.php`
* **NO Modificable:**
  * Capa de Infrastructure y controladores HTTP.

## Archivos o Módulos Involucrados
* `src/Domain/Shared/*`

## Dependencias
"Sin dependencias".

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar obligatoriamente con `declare(strict_types=1);`.
* Aplicar tipado estricto en parámetros, propiedades de clase y valores de retorno.
* Los Value Objects deben ser inmutables: propiedades de solo lectura (`readonly` o privadas sin setters, inicializadas únicamente por constructor).
* `Money.php` debe manejar precisión monetaria interna (operaciones aritméticas como sumar, restar, multiplicar porcentajes, comparaciones menores/mayores) y encapsular la moneda local (COP) o decimales estándar.
* `TimeRange.php` debe manejar validaciones de intervalos horarios (`end_time > start_time`) y métodos para verificar intersecciones de rangos (`overlapsWith`).
* `UuidVO.php` debe validar el formato de UUID v4 estándar.
* El `EventDispatcher.php` debe permitir registrar escuchas (`listeners`) para eventos específicos e invocar su despacho secuencial en memoria de forma sincrónica.

## Entregables Obligatorios
* Código completo de los Value Objects y despachador de eventos.
* Suite de Pruebas Unitarias para cada Value Object (comprobando invariantes válidas e inválidas) y para el despacho de eventos.
* Documentación técnica en los docstrings indicando cómo instanciar y despachar eventos.

## Criterios de Aceptación
* El 100% de los archivos PHP compilan bajo la directiva `declare(strict_types=1);`.
* Intentar instanciar un `Money` con valores negativos arroja un `DomainException`.
* `TimeRange` arroja un `DomainException` si la hora de fin es menor o igual a la de inicio.
* `EventDispatcher` asocia listeners a eventos y los ejecuta correctamente al dispararse.
* Todas las pruebas unitarias pasan sin errores.

## Paralelización
Puede desarrollarse de forma independiente.

---

# TAREA 02

## Nombre
Módulo de Mallas Horarias y Excepciones (Staffing & Availability Core)

## Rol
Backend Engineer Senior / Database Engineer

## Contexto
El módulo de Staffing coordina los bloques de horarios ordinarios en los que trabaja cada especialista en una sucursal, así como las excepciones extraordinarias (vacaciones, bajas médicas, imprevistos). La base de datos tiene listas las tablas (`work_schedule`, `schedule_exception`, `professional_profile`, `holiday`), pero la lógica en PHP está completamente vacía (placeholder con TODO).

## Objetivo
Implementar las clases de dominio, contratos e implementación de persistencia para la configuración de horarios y bloqueos de agenda de los especialistas.

## Alcance
* **Modificable:**
  * `src/Domain/Staffing/Entities/ProfessionalProfile.php`
  * `src/Domain/Staffing/Entities/WorkSchedule.php`
  * `src/Domain/Staffing/Entities/ScheduleException.php`
  * `src/Domain/Staffing/Repositories/StaffingRepositoryInterface.php`
  * `src/Infrastructure/Staffing/Persistence/PdoStaffingRepository.php`
  * `src/Application/Staffing/SaveSchedule/*` (ConfigureScheduleUseCase, DTO)
  * `src/Application/Staffing/ManageExceptions/*` (CreateScheduleExceptionUseCase)
  * `src/Infrastructure/Staffing/Http/SaveScheduleController.php`
  * `src/Infrastructure/Staffing/Http/ScheduleExceptionController.php`
* **NO Modificable:**
  * `bootstrap.php` (solo agregar las instancias correspondientes en el contenedor sin alterar lo existente).
  * Tablas físicas en la base de datos SQL.

## Archivos o Módulos Involucrados
* `src/Domain/Staffing/*`
* `src/Application/Staffing/SaveSchedule/*`
* `src/Application/Staffing/ManageExceptions/*`
* `src/Infrastructure/Staffing/*`

## Dependencias
* TAREA 01: Value Objects y Bus de Eventos.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* Mapear la lógica del DDL MySQL en código PHP, respetando restricciones CHECK:
  * `day_of_week` entre 0 y 6 (Domingo a Sábado).
  * `end_time > start_time`.
  * `lunch_end_time > lunch_start_time` (o null).
* El huso horario forzado en código debe ser estrictamente `America/Bogota` utilizando clases inmutables (`DateTimeImmutable`).
* Toda consulta y filtro de rangos horarios hacia la base de datos debe formatear strings bajo el estándar `'Y-m-d H:i:s'`.
* El repositorio de persistencia `PdoStaffingRepository` debe utilizar sentencias preparadas de PDO (`bindValue`), mapeando parámetros estrictamente sin concatenaciones directas.

## Entregables Obligatorios
* Entidades hydrated del dominio (`ProfessionalProfile`, `WorkSchedule`, `ScheduleException`).
* Implementación del repositorio `PdoStaffingRepository`.
* Casos de uso (`ConfigureScheduleUseCase`, `CreateScheduleExceptionUseCase`) completos.
* Controladores HTTP REST implementados.
* Tests unitarios y de integración para la inserción, lectura y validación de cruces de mallas horarias.
* Lista de archivos modificados.

## Criterios de Aceptación
* El controlador HTTP recibe payloads JSON, los valida mediante DTOs, e inyecta respuestas exitosas o errores tipificados según el estándar RFC 7807 (Problem Details).
* Arroja excepciones semánticas si los rangos de inicio/fin de horario o de pausa de almuerzo no respetan las invariantes del DDL.
* Las consultas SQL se apoyan en el índice compuesto provisto en el DDL: `KEY idx_schedule_matrix (branch_id, day_of_week, professional_profile_id)`.
* Las excepciones de agenda (`ScheduleException`) no se traslapan entre sí para un mismo especialista.

## Paralelización
Requiere completar dependencias previas (TAREA 01).

---

# TAREA 03

## Nombre
Motor de Búsqueda de Ranuras Horarias Disponibles (GetAvailableSlots)

## Rol
Backend Engineer Senior / Performance Developer

## Contexto
La reserva de citas (Booking Core) requiere un motor ultra veloz que determine qué ventanas horarias libres (slots) tiene un especialista para un servicio en particular en una sucursal, cruzando su horario laboral regular, las excepciones registradas (vacaciones, etc.), los festivos de sucursal, y las citas previamente agendadas que no estén canceladas.

## Objetivo
Implementar la lógica y algoritmos para calcular las ranuras horarias hábiles de atención de un especialista en una fecha determinada.

## Alcance
* **Modificable:**
  * `src/Application/Staffing/GetAvailableSlots/GetAvailableSlotsUseCase.php`
  * `src/Application/Staffing/GetAvailableSlots/GetSlotsDTO.php`
  * `src/Infrastructure/Staffing/Http/GetSlotsController.php`
* **NO Modificable:**
  * Módulos de facturación u otros ajenos a Staffing/Booking.

## Archivos o Módulos Involucrados
* `src/Application/Staffing/GetAvailableSlots/*`
* `src/Infrastructure/Staffing/Http/GetSlotsController.php`

## Dependencias
* TAREA 02: Mallas Horarias y Excepciones.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* Utilizar únicamente `DateTimeImmutable` con zona horaria `'America/Bogota'`.
* El caso de uso debe:
  1. Recuperar el horario laboral ordinario de la fecha elegida (apoyado en el índice `idx_schedule_matrix`).
  2. Verificar si el día es festivo global o de la sucursal (tabla `holiday`).
  3. Excluir el rango del descanso/almuerzo (`lunch_start_time` y `lunch_end_time`).
  4. Obtener las excepciones horarias vigentes (`ScheduleException`) para el especialista en el día.
  5. Obtener las citas programadas activas (`CONFIRMED`, `PENDING`, `IN_PROGRESS`) para ese día y especialista.
  6. Dividir la jornada restante en bloques/ranuras de tiempo proporcionales a la duración del servicio seleccionado (`duration_minutes`) más el margen técnico de limpieza (`cleanup_margin_minutes`).
  7. Descartar los bloques que se solapen total o parcialmente con las citas existentes o bloqueos extraordinarios.

## Entregables Obligatorios
* Caso de uso `GetAvailableSlotsUseCase` implementado.
* DTO y controlador HTTP completados.
* Pruebas unitarias de cobertura exhaustivas que simulen escenarios con citas preexistentes, almuerzo, excepciones de agenda, y retorne exactamente las ranuras disponibles correctas.
* Lista de archivos modificados.

## Criterios de Aceptación
* El controlador HTTP retorna un JSON con un array de ranuras con hora de inicio, hora de fin y estado de disponibilidad.
* Si el día coincide con un registro en la tabla `holiday` para la sucursal seleccionada, el listado de ranuras libres se retorna vacío.
* El algoritmo contempla de forma exacta el margen post-servicio (`cleanup_margin_minutes`) al verificar solapamientos con la siguiente ranura libre.

## Paralelización
Requiere completar dependencias previas (TAREA 02).

---

# TAREA 04

## Nombre
Ciclo de Vida y Transiciones de Agendamiento (Check-In, Complete, No-Show y Reprogramación)

## Rol
Backend Engineer Senior / Concurrency Expert

## Contexto
El núcleo de agendamiento (Booking Core) cuenta con la creación y cancelación básica de citas, pero carece de la lógica de operaciones operativas de cabina (llegada física del cliente, marcación como inasistencia, finalización de servicio) y la reprogramación segura en una sola transacción SQL.

## Objetivo
Implementar la lógica transaccional, los casos de uso correspondientes a las transiciones del ciclo de vida de la reserva, y la reprogramación atómica de citas.

## Alcance
* **Modificable:**
  * `src/Application/Booking/Operation/ExecuteCheckInUseCase.php`
  * `src/Application/Booking/Operation/CompleteServiceUseCase.php`
  * `src/Application/Booking/Operation/MarkNoShowUseCase.php`
  * `src/Infrastructure/Booking/Http/OperationsController.php`
  * `src/Application/Booking/CancelBooking/CancelAppointmentUseCase.php` (si requiere adaptar/afinar)
  * Crear `src/Application/Booking/RescheduleBooking/RescheduleAppointmentUseCase.php` y su DTO asociado (Nuevo caso de uso).
* **NO Modificable:**
  * `PdoAppointmentRepository.php` (se pueden agregar métodos a la interfaz y al repositorio pero respetando sentencias preparadas).

## Archivos o Módulos Involucrados
* `src/Application/Booking/Operation/*`
* `src/Application/Booking/RescheduleBooking/*` [NUEVO]
* `src/Infrastructure/Booking/Http/OperationsController.php`

## Dependencias
* TAREA 02: Mallas Horarias y Excepciones.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* Aplicar bloqueos pesimistas en transacciones PDO de nivel de aislamiento mínimo `REPEATABLE READ`.
* Para reprogramaciones u operaciones concurrentes, la verificación de disponibilidad debe ejecutarse dentro de un `SELECT ... FOR UPDATE` sobre la tabla de citas usando el índice `idx_appointment_concurrency` para evitar colisiones de agenda (overbooking).
* Toda transición de estado (`PENDING` -> `CONFIRMED` -> `IN_PROGRESS` -> `COMPLETED` / `CANCELLED` y `CONFIRMED` -> `NOSHOW`) debe validar las invariantes del dominio e insertar de forma obligatoria un registro histórico en la tabla `appointment_history`.
* Disparar eventos de dominio asíncronos desacoplados (`AppointmentCreatedEvent`, `AppointmentCancelledEvent`, etc.) al mutar el estado.

## Entregables Obligatorios
* Casos de uso (`ExecuteCheckInUseCase`, `CompleteServiceUseCase`, `MarkNoShowUseCase`, `RescheduleAppointmentUseCase`) implementados.
* Rutas registradas en `routes.php` y controlador HTTP implementado.
* Pruebas de integración concurrentes que simulen colisiones de reservas simultáneas y aseguren el rollback inmediato ante bloqueos.
* Lista de archivos modificados.

## Criterios de Aceptación
* El flujo de estados de la cita sigue estrictamente el ciclo permitido: `PENDING` -> `CONFIRMED` -> `IN_PROGRESS` -> `COMPLETED`, impidiendo saltos inválidos (ej. de `PENDING` directo a `COMPLETED`).
* La reprogramación atómica de cita libera el espacio temporal anterior del especialista y reserva el nuevo en una sola transacción PDO. Si el nuevo espacio está ocupado, realiza rollback absoluto.
* Ante un fallo de colisión concurrente, retorna una respuesta HTTP 409 o 422 bajo el estándar RFC 7807 con el detalle del conflicto horaria.

## Paralelización
Requiere completar dependencias previas (TAREA 02).

---

# TAREA 05

## Nombre
Módulo Financiero y Persistencia Inmutable (Billing & POS Core)

## Rol
Database Engineer / Financial Backend Developer

## Contexto
El módulo de Billing gestiona las facturas y cobros por tratamientos. Para auditorías financieras estrictas, los datos de pago y afectación de saldos deben ser inmutables. La base de datos tiene las tablas listas (`invoice`, `invoice_payment`, `payment_method`, `professional_commission_ledger`) pero en PHP los componentes de dominio e infraestructura correspondientes son placeholders vacíos.

## Objetivo
Implementar la estructura de Dominio, contratos de repositorios, adaptador de persistencia PDO y DTOs de facturación y cobros.

## Alcance
* **Modificable:**
  * `src/Domain/Billing/Entities/Invoice.php`
  * `src/Domain/Billing/Entities/Payment.php`
  * `src/Domain/Billing/Repositories/InvoiceRepositoryInterface.php`
  * `src/Domain/Billing/Repositories/PaymentRepositoryInterface.php`
  * `src/Infrastructure/Billing/Persistence/PdoInvoiceRepository.php`
  * `src/Infrastructure/Billing/Persistence/PdoPaymentRepository.php`
* **NO Modificable:**
  * Estructura de base de datos MySQL (DDL existente).

## Archivos o Módulos Involucrados
* `src/Domain/Billing/*`
* `src/Infrastructure/Billing/Persistence/*`

## Dependencias
* TAREA 01: Value Objects y Bus de Eventos.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* **Inmutabilidad Financiera:** La persistencia de abonos y afectaciones monetarias en la tabla `invoice_payment` es estrictamente de **solo inserción (Append-Only)**. Queda prohibido realizar `UPDATE` sobre el campo `amount_paid`. Cualquier anulación se inserta como un nuevo cobro con signo negativo o mutando el estado de la factura a `'REFUNDED'`.
* El cálculo matemático de la factura debe respetar: `grand_total = subtotal_amount - discount_amount + tax_amount` y estar protegido por la restricción `chk_invoice_math`.
* La persistencia en PDO debe aplicar sentencias preparadas limpias e inyectar el huso horario `'America/Bogota'`.

## Entregables Obligatorios
* Entidades hydrate del dominio (`Invoice`, `Payment`).
* Interfaces de repositorios y adaptadores `PdoInvoiceRepository` y `PdoPaymentRepository` implementados.
* Pruebas de integración sobre la base de datos para la generación de facturas y abonos múltiples.
* Lista de archivos modificados.

## Criterios de Aceptación
* El guardado de un pago genera una transacción atómica que actualiza en cascada el estado de la factura (`UNPAID` -> `PARTIALLY_PAID` -> `PAID`) según el saldo cubierto.
* Intentar realizar un cobro con monto menor o igual a cero arroja un `DomainException` (gatilla check de base de datos).
* Los métodos de repositorios mapean correctamente tipos estrictos de PHP a columnas DECIMAL del motor InnoDB.

## Paralelización
Puede desarrollarse en paralelo con el agendamiento siempre que TAREA 01 esté terminada.

---

# TAREA 06

## Nombre
Idempotencia y Recepción de Webhooks de Pagos Digitales (ProcessWebhook)

## Rol
Security Engineer / Integration Backend Developer

## Contexto
El sistema recibirá confirmaciones de abonos/pagos en línea de pasarelas de pago externas. Al ser un canal de red inestable, los webhooks pueden retransmitirse múltiples veces, arriesgando cobros duplicados en base de datos. Se requiere un pipeline resiliente y estrictamente idempotente.

## Objetivo
Implementar el procesamiento seguro e idempotente del Webhook de confirmaciones de pago online.

## Alcance
* **Modificable:**
  * `src/Application/Billing/ProcessWebhook/ProcessOnlinePaymentWebhookUseCase.php`
  * `src/Application/Billing/ProcessWebhook/PaymentWebhookDTO.php`
  * `src/Infrastructure/Billing/Http/PaymentWebhookController.php`
* **NO Modificable:**
  * Lógica operativa de citas ajena a la mutación de estado de pago de la cita/factura.

## Archivos o Módulos Involucrados
* `src/Application/Billing/ProcessWebhook/*`
* `src/Infrastructure/Billing/Http/PaymentWebhookController.php`

## Dependencias
* TAREA 05: Billing & POS Core.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* **Seguridad Criptográfica:** Validar la firma digital del request entrante contra el token `PAYMENT_WEBHOOK_SECRET` configurado en el archivo `.env`.
* **Arquitectura Idempotente:**
  1. Extraer la clave de idempotencia única del payload (ID de transacción externa).
  2. Verificar si la clave ya existe en una tabla de control. Si no existe, registrarla con estado `'PROCESSING'`.
  3. Si la clave existe con estado `'PROCESSING'`, abortar devolviendo HTTP 409 Conflict.
  4. Si existe con estado `'SUCCESS'`, retornar la respuesta guardada previamente con HTTP 200 sin procesar nada.
  5. Si el procesamiento de negocio falla, realizar un ROLLBACK de la transacción y liberar la clave de control.
* Integrar la lógica en una transacción PDO aislada con bloqueo pesimista.

## Entregables Obligatorios
* Caso de uso `ProcessOnlinePaymentWebhookUseCase` implementado.
* Webhook Controller e integraciones criptográficas seguras.
* Pruebas de integración automatizadas que simulen ráfagas de 3 peticiones idénticas concurrentes y demuestren que solo 1 se procesa.
* Lista de archivos modificados.

## Criterios de Aceptación
* El controlador retorna código HTTP 200 OK inmediatamente al recibir confirmación válida e idempotente.
* Si la firma criptográfica es inválida, retorna HTTP 401 Unauthorized sin revelar trazas internas del servidor (cumpliendo RFC 7807).
* Los datos resultantes de transacciones de pasarela se persisten de forma íntegra sin escapes que corrompan hashes.

## Paralelización
Requiere completar dependencias previas (TAREA 05).

---

# TAREA 07

## Nombre
Flujo de Caja Chica, Arqueo y Cobros POS (CashRegister & POS)

## Rol
Full Stack Engineer / Senior PHP Developer

## Contexto
El personal de recepción de la sucursal realiza cobros presenciales (tarjeta física, efectivo, transferencia) y abre/cierra turnos de caja chica diaria. La lógica de control de flujo de efectivo debe integrarse de forma transaccional y validarse para el arqueo.

## Objetivo
Implementar los casos de uso de apertura, arqueo, egresos misceláneos, cobros en mostrador y cierre diario de caja chica.

## Alcance
* **Modificable:**
  * `src/Domain/Billing/Entities/CashRegisterSession.php`
  * `src/Application/Billing/CashRegister/OpenCashRegisterUseCase.php`
  * `src/Application/Billing/CashRegister/CloseCashRegisterUseCase.php`
  * `src/Application/Billing/PosPayment/RegisterPosPaymentUseCase.php`
  * `src/Application/Billing/PosPayment/RegisterPosPaymentDTO.php`
  * `src/Infrastructure/Billing/Http/CashRegisterController.php`
  * `src/Infrastructure/Billing/Http/PosPaymentController.php`
  * `src/Infrastructure/Billing/Http/GetInvoicesController.php`
* **NO Modificable:**
  * Rutas y middleware del administrador del sistema (se hereda `AuthMiddleware` existente).

## Archivos o Módulos Involucrados
* `src/Domain/Billing/Entities/CashRegisterSession.php`
* `src/Application/Billing/CashRegister/*`
* `src/Application/Billing/PosPayment/*`
* `src/Infrastructure/Billing/Http/*`

## Dependencias
* TAREA 05: Billing & POS Core.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* La sesión de caja chica (`cash_register_session`) debe controlar los estados (`OPEN`, `CLOSED`, `ARCHIVED`).
* Al registrar un pago presencial via POS, verificar de forma obligatoria que la sucursal del operador tenga una sesión de caja chica en estado `'OPEN'`. Si no la tiene, arrojar excepción de negocio y abortar.
* Calcular discrepancias de efectivo al cerrar la caja (`cash_discrepancy = actual_closing_balance - expected_closing_balance`).
* Envolver la afectación de facturación, abono de abonos y flujo de caja diario dentro de una transacción PDO única.

## Entregables Obligatorios
* Lógica de la sesión de caja chica e implementaciones de casos de uso y DTOs completas.
* Controladores HTTP del flujo financiero de mostrador expuestos y conectados.
* Suite de pruebas unitarias y de integración para simulación de flujos completos (apertura -> cobros POS -> egresos -> discrepancia -> cierre).
* Lista de archivos modificados.

## Criterios de Aceptación
* El controlador del POS rechaza registros de cobros si la caja está en estado `'CLOSED'`.
* El arqueo calcula de forma exacta la discrepancia matemática (sobrantes y faltantes) registrándola de forma inmutable.
* El arqueo de cierre calcula la suma de todos los ingresos de la jornada por tipo de medio de pago y valida la coherencia contra los abonos persistidos en `invoice_payment`.

## Paralelización
Requiere completar dependencias previas (TAREA 05).

---

# TAREA 08

## Nombre
Service Worker Avanzado Offline-First y Sincronización en Segundo Plano (PWA)

## Rol
Frontend Engineer Senior / PWA Expert

## Contexto
La PWA de Estética Carolina Mora debe ser capaz de operar en zonas de baja conectividad. Los clientes deben poder ver el catálogo de servicios cargado previamente en caché y realizar solicitudes de agendamiento sin conexión a red, las cuales se guardan localmente y se sincronizan al recuperar red.

## Objetivo
Implementar la lógica offline en el Service Worker, persistencia local en IndexedDB y el mecanismo de sincronización asíncrona.

## Alcance
* **Modificable:**
  * `public/sw.js`
  * `public/assets/js/app-client.js` (sección de inicialización offline)
* **NO Modificable:**
  * Layout base HTML o estilos CSS globales (salvo agregar indicadores de red en la UI).

## Archivos o Módulos Involucrados
* `public/sw.js`
* `public/assets/js/app-client.js`
* `public/manifest.json`

## Dependencias
* "Sin dependencias" (Backend transaccional de citas debe estar listo para responder peticiones en vivo).

## Requisitos Técnicos
* **IndexedDB para Persistencia Local:** Al mutar el estado (como agendar una cita offline), el JS principal genera un token de correlación UUID y encola el payload crudo en un almacén de objetos (`Object Store`) de IndexedDB llamado `outbox_queue`.
* **Service Worker con Estrategia de Caché Avanzada:** Implementar la estrategia `Stale-While-Revalidate` en `sw.js` para recursos de interfaz (HTML, CSS, JS, imágenes de catálogo).
* **API Background Sync:** Registrar un evento de sincronización (`sync`) en el Service Worker al recuperar conectividad. El Service Worker debe recorrer la cola `outbox_queue`, despachar las peticiones pendientes a la API REST, y ante fallas horarias de disponibilidad (conflicto), emitir un mensaje al hilo principal vía `BroadcastChannel` para notificar al usuario.

## Entregables Obligatorios
* Service Worker (`sw.js`) con la lógica avanzada de caché y sincronización offline de solo inserción.
* Lógica JS en `app-client.js` para el encolado en IndexedDB y sincronización transparente.
* Guía de verificación manual de funcionamiento offline (desactivar red en navegador, interactuar y reactivar).
* Lista de archivos modificados.

## Criterios de Aceptación
* Con la red desactivada (modo offline en DevTools), la aplicación sigue respondiendo y renderizando el catálogo.
* Al agendar una cita sin conexión, la interfaz de usuario se actualiza mostrando la acción con un estado amigable "Pendiente de Sincronización".
* Al reconectar la red, el Service Worker dispara el Background Sync de forma automática, vacía la cola y sincroniza contra la API REST.

## Paralelización
Puede desarrollarse de forma independiente a la lógica interna de base de datos, maquetando la sincronización en paralelo con mockups.

---

# TAREA 09

## Nombre
Webhook y Procesador Conversacional de WhatsApp Bot (WhatsAppWebhookController)

## Rol
Backend Engineer / Security Engineer / Webhook Expert

## Contexto
El canal de WhatsApp Business de la estética recibe solicitudes de citas y consultas. El webhook debe recibir payloads de Meta, validarlos de forma asíncrona para no congestionar la conexión con Meta, e implementar una máquina de estados conversacional para guiar al cliente (Sucursal -> Servicio -> Profesional -> Fecha y Hora).

## Objetivo
Implementar el controlador del Webhook de WhatsApp, el validador criptográfico y la persistencia de estados de la sesión del chat.

## Alcance
* **Modificable:**
  * `src/Infrastructure/Integration/WhatsApp/Http/WhatsAppWebhookController.php`
  * `src/Infrastructure/Integration/WhatsApp/Services/WebhookSignatureValidator.php`
  * `src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaChatSessionRepository.php`
  * `src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaMessageLogRepository.php`
* **NO Modificable:**
  * Casos de uso de agendamiento core (solo puede consumirlos mediante la interfaz inyectada).

## Archivos o Módulos Involucrados
* `src/Infrastructure/Integration/WhatsApp/Http/WhatsAppWebhookController.php`
* `src/Infrastructure/Integration/WhatsApp/Services/WebhookSignatureValidator.php`
* `src/Infrastructure/Integration/WhatsApp/Persistence/*`

## Dependencias
* TAREA 03: Motor de Búsqueda de Slots.
* TAREA 04: Ciclo de Vida de Agendamiento.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* **Validación Perimetral de Meta:** El webhook debe validar la autenticidad del remitente verificando la firma criptográfica `X-Hub-Signature-256` contra el `WHATSAPP_APP_SECRET` del archivo `.env`.
* **Patrón Asíncrono Desacoplado (Evitar Loops de Reintento):**
  1. Validar el handshake inicial de verificación (`hub.mode === 'subscribe'`).
  2. Extraer el ID único del mensaje (`wamid`). Si el `wamid` ya existe en la tabla de logs de mensajes (`wa_message_log`), abortar de inmediato para prevenir duplicaciones.
  3. Insertar el payload crudo en la tabla física de cola de mensajes entrantes con estado `'PENDING'`.
  4. Forzar el cierre inmediato de la conexión HTTP enviando un código `HTTP 200 OK` mediante el vaciado explícito del búfer de salida (`fastcgi_finish_request()` o equivalente) antes de procesar lógica pesada de base de datos.
* **Máquina de Estados de Chat:** Administrar las interacciones mediante la tabla `wa_chat_session` mapeando el estado de progreso del flujo en `current_node_code` (ej. `CHOOSE_DATE`, `CONFIRMATION`).

## Entregables Obligatorios
* Controlador `WhatsAppWebhookController` y componente de validación criptográfica completo.
* Repositorio de base de datos para sesiones y logs de mensajes de WhatsApp.
* Pruebas de integración simulando peticiones de Meta y verificando firmas.
* Lista de archivos modificados.

## Criterios de Aceptación
* El Webhook responde HTTP 200 OK a Meta en menos de 2 segundos, liberando el hilo web antes de parsear la agenda.
* Firmas falsas o alteradas son rechazadas inmediatamente devolviendo un error HTTP 401.
* El bot retiene el contexto del cliente (sucursal, especialista y hora) incluso si el cliente se demora horas en responder, recuperando la sesión de la tabla `wa_chat_session`.

## Paralelización
Requiere completar dependencias previas (TAREA 03 y TAREA 04).

---

# TAREA 10

## Nombre
Cola de Notificaciones Salientes de WhatsApp y Worker CLI Resiliente

## Rol
DevOps Engineer / Backend Engineer Senior

## Contexto
El sistema despacha recordatorios automáticos de citas y notificaciones salientes HSM (Highly Structured Messages) pre-aprobadas por Meta. Los envíos deben automatizarse en segundo plano para no demorar las transacciones HTTP de los clientes, optimizando el uso de CPU y RAM de Hostinger (recursos compartidos).

## Objetivo
Implementar el worker por lotes en segundo plano CLI, el motor de cola, y el cliente de Meta Cloud API para notificaciones salientes.

## Alcance
* **Modificable:**
  * `src/Infrastructure/Integration/WhatsApp/Workers/NotificationQueueWorker.php`
  * `src/Infrastructure/Integration/WhatsApp/Services/MetaApiClient.php`
  * `src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaNotificationQueueRepository.php`
* **NO Modificable:**
  * Configuraciones globales del servidor Linux (se asume que se ejecuta mediante Cron standard en Hostinger).

## Archivos o Módulos Involucrados
* `src/Infrastructure/Integration/WhatsApp/Workers/*`
* `src/Infrastructure/Integration/WhatsApp/Services/MetaApiClient.php`
* `src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaNotificationQueueRepository.php`

## Dependencias
* TAREA 09: WhatsApp Webhook & Sessions.

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* **Optimización de Hosting Compartido (Anti-Suspensión):**
  * Prohibidos los demonios persistentes o bucles infinitos `while(true)`.
  * Diseñar el script de consola CLI `NotificationQueueWorker.php` para ejecutarse por lotes cortos.
  * Implementar un semáforo mediante archivo de bloqueo físico (`flock`) al iniciar, impidiendo ejecuciones simultáneas solapadas del cron.
  * Autolimitación estricta del tiempo de vida del script a un máximo de 45 segundos o a un lote controlado de N registros.
  * Invocar de forma explícita la liberación de memoria (`unset`) y cierre de conexiones PDO activas antes de finalizar.
* **Respaldo Exponencial (Exponential Backoff):**
  * Ante fallas de red de Meta API, recalcular el próximo intento de la notificación usando la fórmula: $Tiempo = Base \times 2^{attempts} + Jitter$, con un máximo de 5 intentos. Al superarlo, pasar el registro permanentemente a `'FAILED'` y loguear.

## Entregables Obligatorios
* Script CLI `NotificationQueueWorker.php` completamente operativo.
* Adaptador `MetaApiClient.php` integrado para envío de plantillas HSM.
* Repositorio de persistencia de la cola de notificaciones.
* Tests de estrés y pruebas de simulación de fallas de red con respaldo exponencial.
* Lista de archivos modificados.

## Criterios de Aceptación
* El script se ejecuta desde la consola (`php NotificationQueueWorker.php`) y procesa lotes de mensajes de la base de datos sin fugas de memoria.
* Intentar arrancar dos instancias concurrentes del script CLI resulta en la muerte inmediata de la segunda (semáforo flock validado).
* Las fallas de red externas no interrumpen el script; se recalculan los reintentos en base de datos con el respaldo exponencial exacto.

## Paralelización
Requiere completar dependencias previas (TAREA 09).

---

# TAREA 11

## Nombre
Servicio de Encriptación de Datos Clínicos Sensibles (EncryptionService)

## Rol
Security Engineer

## Contexto
El cumplimiento de las normativas de protección de datos exige que la información médica e historial de alergias de los clientes final (`medical_notes_allergies` en la tabla `client_profile`) no se guarden en texto plano. Se requiere un servicio de encriptación simétrica robusto e integrado de manera transparente antes de escribir o tras leer en la base de datos.

## Objetivo
Implementar la criptografía simétrica simulación de encriptación bidireccional segura en base a claves.

## Alcance
* **Modificable:**
  * `src/Infrastructure/Shared/Security/EncryptionService.php`
  * `src/Infrastructure/Booking/Persistence/PdoClientProfileRepository.php` (para integrar la llamada al encriptador al guardar/recuperar)
* **NO Modificable:**
  * Capa de aplicación o entidades de dominio puro (la criptografía debe ocurrir en la capa de persistencia/infraestructura).

## Archivos o Módulos Involucrados
* `src/Infrastructure/Shared/Security/EncryptionService.php`
* `src/Infrastructure/Booking/Persistence/PdoClientProfileRepository.php`

## Dependencias
"Sin dependencias".

## Requisitos Técnicos
* Todos los archivos PHP deben comenzar con `declare(strict_types=1);`.
* Utilizar el algoritmo `AES-256-CBC` mediante la extensión `openssl` nativa de PHP.
* La clave de encriptación y el vector de inicialización (IV) deben gestionarse de forma segura: la clave base se extrae de variables de entorno (`.env`).
* Cada encriptación debe generar un IV aleatorio seguro (`openssl_random_pseudo_bytes`), el cual se concatena con el ciphertext resultante y se codifica en Base64 para guardarse en la columna de base de datos.
* Al desencriptar, separar el IV y el ciphertext, y reconstruir la cadena original en texto plano.

## Entregables Obligatorios
* Clase `EncryptionService` implementada y testeada.
* Repositorio `PdoClientProfileRepository` modificado para encriptar notas al guardar y desencriptar al recuperar filas.
* Pruebas unitarias de encriptación y desencriptación validando casos nulos y cadenas vacías.
* Lista de archivos modificados.

## Criterios de Aceptación
* Los datos en la columna `medical_notes_allergies` de la base de datos aparecen encriptados y cifrados de forma ilegible en inspección directa.
* La desencriptación funciona de forma transparente; al consultar el perfil vía API/Controller, el cliente recupera la cadena original con tildes y caracteres especiales intactos.
* Ante claves de entorno corruptas o faltantes, el servicio arroja excepciones seguras sin revelar la clave en las trazas del log.

## Paralelización
Puede desarrollarse de forma independiente.

---

# TAREA 12

## Nombre
Suite de Pruebas Unitarias de Negocio y Ciclo de Vida de Pruebas con DB Virtual (Testing Suite)

## Rol
DevOps / QA Automation Engineer

## Contexto
El ecosistema carece por completo de cobertura de pruebas unitarias en la lógica del dominio y los casos de uso. Adicionalmente, el test de integración existente falla al no contar con un aislamiento de base de datos durante las pruebas, intentando conectarse a un MySQL local no disponible.

## Objetivo
Configurar el entorno de testing, mockear la base de datos utilizando SQLite en memoria o mocks de PDO, e implementar pruebas unitarias y de integración base.

## Alcance
* **Modificable:**
  * `phpunit.xml`
  * `tests/*` (Estructura de tests y crear clases de test en `tests/Unit/` y `tests/Integration/`)
* **NO Modificable:**
  * Código de negocio en `src/` (a menos que se requiera refactorizar para testear dependencias mediante interfaces).

## Archivos o Módulos Involucrados
* `phpunit.xml`
* `tests/*`

## Dependencias
"Sin dependencias".

## Requisitos Técnicos
* Todos los archivos PHP de testing deben comenzar con `declare(strict_types=1);`.
* Configurar `phpunit.xml` para aislar el entorno de testing de la base de datos real en desarrollo.
* Para pruebas de integración, inyectar una base de datos SQLite en memoria (`sqlite::memory:`) cargando un esquema equivalente al DDL de MySQL, o mockear el controlador PDO para simular respuestas sin conexiones reales de red.
* Cada prueba de integración debe ejecutarse dentro de una transacción que aplique rollback automático al finalizar (`tearDown`), garantizando idempotencia absoluta y que no queden residuos entre ejecuciones.
* Escribir pruebas unitarias de cobertura mínima de 80% sobre las entidades base (`User`, `Role`, `Appointment`) y el caso de uso `CreateAppointmentUseCase`.

## Entregables Obligatorios
* Configuración de PHPUnit y scripts auxiliares de pruebas automatizadas.
* Suite de pruebas unitarias para Domain y Application.
* Test de integración `IAM_Login_Test.php` corregido y pasando con base de datos en memoria/mock.
* Lista de archivos modificados.

## Criterios de Aceptación
* El comando `vendor/bin/phpunit` se ejecuta con éxito desde la terminal y corre el 100% de los tests sin arrojar fallos de conexión a la base de datos real.
* Las pruebas unitarias validan caminos felices y flujos alternativos o excepciones de negocio ante entradas de datos corruptas.
* No quedan bases de datos temporales físicas creadas tras la ejecución del testing.

## Paralelización
Puede desarrollarse de forma independiente a la lógica de negocio pendiente.
