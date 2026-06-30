1. Mecanismo de Autenticación y Gestión de Contexto
La API opera bajo una arquitectura Stateless (Sin estado). Toda solicitud que requiera control de acceso debe suministrar credenciales válidas en las cabeceras HTTP.

A. Canal PWA y Aplicación Móvil (JWT Standard)
Las interfaces gráficas enviarán un token de portador (Bearer Token) en la cabecera estándar de autorización:

HTTP
Authorization: Bearer <JWT_TOKEN>
Estructura del Payload del JWT (Claims):

JSON
{
  "iss": "https://api.esteticacarolinamora.com",
  "sub": "10482394", 
  "username": "caro_mora89",
  "role": "BRANCH_ADMIN",
  "branch_id": 1,
  "iat": 1780754400,
  "exp": 1780783200
}
Procesamiento en PHP: El middleware de infraestructura interceptará el token, validará la firma criptográfica (utilizando una llave secreta segura del servidor) y poblará un objeto global UserContext inyectado en el controlador correspondiente.

B. Canal Bot de WhatsApp (Autenticación Transparente por MSISDN)
Dado que el bot de WhatsApp interactúa de forma automatizada mediante el Webhook de Meta, el cliente no puede enviar una cabecera de autenticación interactiva ni almacenar un JWT tradicional.

Mecanismo: La pasarela del Webhook de WhatsApp se autentica ante nuestra API usando un Token estático de Sistema (X-WhatsApp-System-Token) en las cabeceras.

Resolución de Contexto de Cliente: Para cada mensaje entrante, el controlador del webhook extraerá el número de teléfono del remitente en formato internacional E.164 (ejemplo: 573001234567), conocido técnicamente como MSISDN.

Flujo en PHP: El caso de uso invocará al repositorio (UserRepository->findByPhone($msisdn)) para resolver si el número está asociado a un client_profile_id activo. Si existe, se inicializa el contexto de ese cliente de manera transparente; si no existe, el bot iniciará automáticamente el flujo guiado de registro (RF-01).

2. Estructura Unificada de Respuestas de Error (RFC 7807)
Para evitar inconsistencias, absolutamente todos los errores devueltos por la API (desde excepciones de base de datos hasta fallos de validación de negocio) utilizarán el tipo de medio application/problem+json:

A. Formato General de Error (Ejemplo 400 / 401 / 500)
JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/unauthorized",
  "title": "No autorizado",
  "status": 401,
  "detail": "El token JWT suministrado ha expirado o la firma es inválida.",
  "instance": "/api/v1/catalog/services"
}
B. Formato de Error de Validación de Datos (Código HTTP 422 - Unprocessable Entity)
Cuando las reglas de negocio o los tipos de datos del JSON de entrada no se cumplan, se listarán los campos específicos afectados:

JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/validation-failed",
  "title": "Error de validación en la entidad",
  "status": 422,
  "detail": "Los datos enviados contienen errores sintácticos o violan reglas de negocio.",
  "instance": "/api/v1/iam/register",
  "invalid_params": [
    {
      "name": "email",
      "reason": "El formato del correo electrónico no es válido."
    },
    {
      "name": "username",
      "reason": "El nombre de usuario ya se encuentra registrado en el sistema."
    }
  ]
}
3. Especificación de Endpoints: Módulo IAM
Endpoint: Autenticación de Usuarios (PWA / App)
Ruta: /api/v1/iam/login

Método: POST

Cabeceras: Content-Type: application/json

Cuerpo de la Petición (Request Body):

JSON
{
  "username": "clientex_99",
  "password": "SecurePassword123!"
}
Respuestas:

200 OK (Credenciales Válidas):

JSON
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 28800,
  "user": {
    "user_id": 45,
    "username": "clientex_99",
    "role": "CLIENT",
    "client_profile_id": 12
  }
}
401 Unauthorized (Credenciales Incorrectas o Usuario Inactivo): De acuerdo al formato unificado de error.

Endpoint: Registro Autónomo de Clientes (RF-01)
Ruta: /api/v1/iam/register

Método: POST

Cuerpo de la Petición:

JSON
{
  "username": "caro_mora89",
  "email": "carolina@mail.com",
  "password": "PasswordStrict_9!",
  "first_name": "Carolina",
  "last_name": "Mora",
  "phone": "+573001234567"
}
Respuestas:

201 Created (Registro Exitoso):

JSON
{
  "success": true,
  "message": "Usuario y perfil de cliente creados exitosamente.",
  "data": {
    "user_id": 89,
    "client_profile_id": 34
  }
}
422 Unprocessable Entity: Si el correo o el usuario ya existen, o el teléfono no sigue el patrón E.164.

4. Especificación de Endpoints: Módulo Catalog & Pricing
Endpoint: Exploración Filtrada del Catálogo (RF-04)
Ruta: /api/v1/catalog/services

Método: GET

Parámetros de Consulta (Query Params): ?category_id=2&search=Manicure&limit=10&page=1

Respuestas:

200 OK:

JSON
{
  "success": true,
  "meta": {
    "total_records": 1,
    "current_page": 1,
    "total_pages": 1
  }
  "data": [
    {
      "service_id": 2,
      "name": "Manicure Semi技术",
      "description": "Tratamiento completo de uñas con esmaltado semipermanente.",
      "price": 15.00,
      "duration_minutes": 45,
      "is_active": 1
    }
  ]
}
Endpoint: Creación de Campaña de Promoción (Administrador - RF-13 / RF-21)
Ruta: /api/v1/catalog/promotions

Método: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Rol restringido a SUPER_ADMIN o BRANCH_ADMIN)

Cuerpo de la Petición:

JSON
{
  "name": "Descuento de Temporada Cian",
  "discount_percentage": 15.00,
  "start_date": "2026-06-01",
  "end_date": "2026-06-30",
  "associated_services": [1, 2]
}
Respuestas:

201 Created:

JSON
{
  "success": true,
  "promotion_id": 5,
  "message": "Campaña promocional creada y vinculada a los servicios seleccionados."
}
403 Forbidden: Si el usuario no tiene los privilegios adecuados en su JWT.

5. Especificación de Endpoints: Módulo Staffing & Availability
Endpoint: Consulta de Disponibilidad de un Especialista (RF-05, RF-14)
Ruta: /api/v1/staffing/professionals/{professional_profile_id}/slots

Método: GET

Parámetros de Consulta (Query Params): ?branch_id=1&date=2026-06-15&service_id=2

Descripción: Cruza la tabla work_schedule, las excepciones de agenda y las citas activas en appointment para devolver una matriz lineal de bloques horarios libres listos para ser reservados.

Respuestas:

200 OK:

JSON
{
  "success": true,
  "meta": {
    "professional_profile_id": 14,
    "date": "2026-06-15",
    "service_duration_minutes": 45
  },
  "data": [
    {
      "start_time": "09:00:00",
      "end_time": "09:45:00",
      "is_available": true
    },
    {
      "start_time": "09:45:00",
      "end_time": "10:30:00",
      "is_available": true
    },
    {
      "start_time": "10:30:00",
      "end_time": "11:15:00",
      "is_available": false,
      "reason": "COLLISION_RESERVED_APPOINTMENT"
    }
  ]
}
Endpoint: Configuración de la Malla Horaria Semanal (Administrador - RF-14)
Ruta: /api/v1/staffing/schedules

Método: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Roles: SUPER_ADMIN, BRANCH_ADMIN)

Cuerpo de la Petición (Request Body):

JSON
{
  "professional_profile_id": 14,
  "branch_id": 1,
  "day_of_week": 1, 
  "start_time": "08:00:00",
  "end_time": "17:00:00",
  "lunch_start_time": "12:00:00",
  "lunch_end_time": "13:00:00"
}
Respuestas:

201 Created:

JSON
{
  "success": true,
  "work_schedule_id": 402,
  "message": "Turno base registrado correctamente en la malla operativa."
}
422 Unprocessable Entity: Si end_time <= start_time o si viola los constraints chk_work_day o chk_lunch_hours de la base de datos.

6. Especificación de Endpoints: Módulo Transaccional de Agendamiento (Booking Core)
Endpoint: Creación Concurrente de Cita (PWA / App / WhatsApp - RF-06, RF-07)
Ruta: /api/v1/booking/appointments

Método: POST

Estrategia de Control: Este endpoint debe implementar el bloqueo pesimista (FOR UPDATE) detallado en el diseño modular para validar de manera atómica el solapamiento temporal y blindar el sistema contra overbooking ante ráfagas del Bot o la PWA.

Cuerpo de la Petición:

JSON
{
  "client_profile_id": 34,
  "professional_profile_id": 14,
  "branch_id": 1,
  "promotion_id": 5, 
  "scheduled_timestamp": "2026-06-15 09:00:00",
  "notes": "Cliente solicita cabina aislada por temas de privacidad."
}
Lógica del Controlador PHP: El sistema calculará dinámicamente la duración del servicio (obtenida del catálogo) para inyectar automáticamente el campo estimated_end_timestamp y procesar el cálculo financiero de total_price y final_price antes de la inserción física.

Respuestas:

201 Created (Reserva Exitosa):

JSON
{
  "success": true,
  "message": "Cita agendada y bloqueada con éxito.",
  "data": {
    "appointment_id": 50812,
    "appointment_status": "PENDING",
    "scheduled_timestamp": "2026-06-15 09:00:00",
    "estimated_end_timestamp": "2026-06-15 09:45:00",
    "total_price": 15.00,
    "final_price": 12.75
  }
}
422 Unprocessable Entity (Colisión de Horario / Bloqueo Anti-Overbooking):

JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/schedule-collision",
  "title": "Conflicto de disponibilidad de agenda",
  "status": 422,
  "detail": "El especialista seleccionado ya cuenta con un servicio agendado o en progreso en el rango horario solicitado.",
  "instance": "/api/v1/booking/appointments"
}
Endpoint: Reprogramación Autónoma de Citas (RF-08, RF-09)
Ruta: /api/v1/booking/appointments/{appointment_id}/reschedule

Método: PUT

Cuerpo de la Petición:

JSON
{
  "new_scheduled_timestamp": "2026-06-18 14:00:00"
}
Restricciones del Dominio en PHP: El controlador debe validar que la cita original no esté en estado IN_PROGRESS o COMPLETED. Adicionalmente, evaluará el límite de reprogramación autónoma parametrizado por la sucursal (autonomous_reschedule_limit) consultando la tabla branch.

Respuestas:

200 OK: Regresa el objeto mutado reflejando el nuevo scheduled_timestamp e incremental en el log inmutable.

400 Bad Request: Si el cliente superó el límite permitido de cambios automáticos.

Endpoint: Cancelación Lógica de Cita (RF-09)
Ruta: /api/v1/booking/appointments/{appointment_id}/cancel

Método: PATCH

Cuerpo de la Petición:

JSON
{
  "change_reason": "Cancelación solicitada por el usuario desde el bot de WhatsApp."
}
Validación de la Regla de Oro (24 Horas): El caso de uso restará scheduled_timestamp del tiempo actual del servidor. Si el delta es menor que las horas parametrizadas en branch.cancellation_hours_notice, denegará la cancelación autónoma o aplicará la penalización financiera configurada.

Respuestas:

200 OK: Muta appointment_status a 'CANCELLED' e inserta la trazabilidad en appointment_history.

Endpoints de Recepción: Gestión Operativa en Sucursal (RF-17, RF-18)
Para controlar el flujo de los clientes dentro de la sucursal, la recepcionista consume endpoints semánticos que guían las transiciones lógicas de la máquina de estados:

Check-In Presencial (Llegada del Cliente - RF-17):

Ruta: /api/v1/booking/appointments/{appointment_id}/check-in

Método: PATCH

Acción: Cambia appointment_status de CONFIRMED o PENDING hacia IN_PROGRESS. Registra al operador en appointment_history.

Respuesta 200 OK: {"success": true, "new_status": "IN_PROGRESS"}

Finalización Técnica del Servicio (Cierre de Cabina - RF-18):

Ruta: /api/v1/booking/appointments/{appointment_id}/complete

Método: PATCH

Acción: Cambia appointment_status hacia COMPLETED. Esta transición dispara de manera automática el evento de dominio AppointmentCompletedEvent, inhabilitando cualquier modificación posterior sobre los tiempos de la cita y habilitando la facturación en el POS.

Respuesta 200 OK: {"success": true, "new_status": "COMPLETED"}

Especificación de Endpoints: Módulo Financiero y Caja (Billing & POS)
Endpoint: Registro de Pago Presencial en Punto de Venta (POS - RF-12, RF-18)
Ruta: /api/v1/billing/cash-desk/payments

Método: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Roles: SUPER_ADMIN, BRANCH_ADMIN, RECEPCIONIST)

Descripción: Registra el cobro en el mostrador físico de la sucursal para una cita que ha transicionado al estado COMPLETED.

Cuerpo de la Petición (Request Body):

JSON
{
  "appointment_id": 50812,
  "amount": 12.75,
  "payment_method": "CASH", 
  "transaction_reference": "POS-001-50812",
  "notes": "Pago exacto recibido en efectivo. Se entrega tirilla física."
}
Respuestas:

201 Created:

JSON
{
  "success": true,
  "payment_id": 98412,
  "payment_status": "COMPLETED",
  "registered_at": "2026-06-06 14:05:22",
  "message": "Transacción contable POS asentada exitosamente."
}
422 Unprocessable Entity: Si el monto no coincide con el balance calculado para la cita o si la referencia de transacción ya existe (UNIQUE constraint violation).

Endpoint: Webhook de la Pasarela de Pagos Online (RF-12)
Ruta: /api/v1/billing/payments/webhook

Método: POST

Cabeceras: X-Gateway-Signature: <SHA256_HASH_VERIFICATION>

Descripción: Punto de entrada asíncrono e impersonal consumido por la pasarela externa (ej. Stripe o pasarela local). No requiere JWT de usuario, pero valida la autenticidad mediante la firma criptográfica en las cabeceras.

Cuerpo de la Petición:

JSON
{
  "event": "payment.succeeded",
  "transaction_id": "ch_3Mv8x1LkdIwHu7ix2",
  "amount_captured": 12.75,
  "currency": "usd",
  "metadata": {
    "appointment_id": "50812"
  }
}
Respuestas:

200 OK: ```json
{
"status": "acknowledged",
"processed_at": "2026-06-06 14:06:00"
}


Endpoint: Consolidado de Cierre de Caja Diario (RF-19)
Ruta: /api/v1/billing/cash-desk/closing

Método: GET

Parámetros de Consulta: ?branch_id=1&date=2026-06-06

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Roles: SUPER_ADMIN, BRANCH_ADMIN)

Respuestas:

200 OK:

JSON
{
  "success": true,
  "meta": {
    "branch_id": 1,
    "date": "2026-06-06"
  },
  "summary": {
    "gross_revenue": 1450.00,
    "transactions_count": 48,
    "breakdown_by_method": [
      { "payment_method": "CASH", "total": 450.00 },
      { "payment_method": "CREDIT_CARD", "total": 800.00 },
      { "payment_method": "ONLINE_GATEWAY", "total": 200.00 }
    ]
  }
}
8. Especificación de Endpoints: Módulo Analítico y CRM (CRM & Analytics)
Endpoint: Registro de Calificación de Servicio (RF-11, RF-20)
Ruta: /api/v1/crm/ratings

Método: POST

Descripción: Consumido directamente desde la encuesta asíncrona enviada al cliente.

Cuerpo de la Petición:

JSON
{
  "appointment_id": 50812,
  "score": 5,
  "comments": "Excelente atención en la manicura. El personal fue muy detallista."
}
Validación de Dominio: El controlador PHP validará obligatoriamente que score esté en el rango integro del backend [1, 5] y que la cita referenciada se encuentre en estado COMPLETED.

Respuestas:

201 Created: {"success": true, "rating_id": 4321}

9. Contrato de Entrada: Webhook de Meta Cloud API (WhatsApp Omnicanal - RF-07)
Este endpoint es el punto de enlace crítico para el procesamiento conversacional. Recibe las notificaciones crudas desde los servidores de Meta cuando un cliente envía un mensaje de texto o interactúa con un botón estructurado del bot.

Ruta: /api/v1/webhooks/whatsapp

Método: POST

Cabeceras Obligatorias: X-Hub-Signature-256: sha256=<HEX_DIGEST> (Validada internamente usando el App Secret de Meta para asegurar el origen).

Payload Estándar de Entrada (Payload Enviado por Meta):
JSON
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "109283746564738",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "15550123456",
              "phone_number_id": "104958273645"
            },
            "contacts": [
              {
                "profile": { "name": "Camila Gomez" },
                "wa_id": "573001234567"
              }
            ],
            "messages": [
              {
                "from": "573001234567",
                "id": "wamid.HBgLNTczMDAxMjM0NTY3FQIAERgE...",
                "timestamp": "1780754400",
                "text": {
                  "body": "Quiero reservar una cita para manicure mañana"
                },
                "type": "text"
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}
Protocolo de Extracción e Inyección en el Backend en PHP:
Para que Antigravity 2.0 procese el flujo sin romper capas, el controlador del webhook ejecutará secuencialmente los siguientes pasos técnicos:

Aislamiento del MSISDN: Extraerá el valor de entry[0].changes[0].value.messages[0].from ("573001234567"). Este string representa el PhoneNumber del Value Object del dominio.

Resolución Transparente de Perfil: Consultará de manera atómica el repositorio de clientes. Si el número telefónico existe, inyectará el client_profile_id resultante en el contenedor de dependencias del caso de uso.

Análisis de Intención (NLP / Keyword Parsing): Extraerá el string del campo text.body. Si la intención coincide con un flujo estructurado (ej. "reservar", "cancelar"), enrutará la lógica de negocio directamente hacia los mismos casos de uso consumidos por la PWA (vía CreateAppointmentUseCase o CancelAppointmentUseCase), garantizando que las validaciones contra condiciones de carrera (FOR UPDATE) y límites horarios sean idénticas y centralizadas.

10. Códigos de Estado HTTP y Semántica Global de la API
Para garantizar la homogeneidad de la integración, la API se compromete a responder usando estrictamente los siguientes códigos del estándar HTTP:

200 OK: Solicitud procesada correctamente con retorno de datos (Consultas de catálogo, listados de citas, cierres de caja).

201 Created: Inserción física exitosa de un recurso inmutable en la base de datos (Creación de citas, registros de usuarios, pagos POS).

400 Bad Request: Error sintáctico en el JSON o violación directa de rangos lógicos ordinarios en los parámetros.

401 Unauthorized: Ausencia de token de autorización, expiración de credenciales JWT o firma inválida.

403 Forbidden: Token válido pero el rol asignado no cuenta con los privilegios suficientes en la matriz RBAC para acceder al recurso.

422 Unprocessable Entity: Solicitud sintácticamente correcta pero inválida a nivel semántico de negocio (Colisiones de disponibilidad, bloqueos por sobre-reserva concurrentes, violaciones a la regla de cancelación de 24 horas).

500 Internal Server Error: Excepciones de infraestructura no controladas (Pérdida de conectividad con el servidor MySQL de Hostinger, desbordamiento de memoria). El payload devuelto ocultará el Stack-Trace técnico en producción por motivos de seguridad perimetral, registrándolo únicamente de forma interna.