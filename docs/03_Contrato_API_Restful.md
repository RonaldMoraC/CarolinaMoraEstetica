1. Mecanismo de AutenticaciÃ³n y GestiÃ³n de Contexto
La API opera bajo una arquitectura Stateless (Sin estado). Toda solicitud que requiera control de acceso debe suministrar credenciales vÃ¡lidas en las cabeceras HTTP.

A. Canal PWA y AplicaciÃ³n MÃ³vil (JWT Standard)
Las interfaces grÃ¡ficas enviarÃ¡n un token de portador (Bearer Token) en la cabecera estÃ¡ndar de autorizaciÃ³n:

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
Procesamiento en PHP: El middleware de infraestructura interceptarÃ¡ el token, validarÃ¡ la firma criptogrÃ¡fica (utilizando una llave secreta segura del servidor) y poblarÃ¡ un objeto global UserContext inyectado en el controlador correspondiente.

B. Canal Bot de WhatsApp (AutenticaciÃ³n Transparente por MSISDN)
Dado que el bot de WhatsApp interactÃºa de forma automatizada mediante el Webhook de Meta, el cliente no puede enviar una cabecera de autenticaciÃ³n interactiva ni almacenar un JWT tradicional.

Mecanismo: La pasarela del Webhook de WhatsApp se autentica ante nuestra API usando un Token estÃ¡tico de Sistema (X-WhatsApp-System-Token) en las cabeceras.

ResoluciÃ³n de Contexto de Cliente: Para cada mensaje entrante, el controlador del webhook extraerÃ¡ el nÃºmero de telÃ©fono del remitente en formato internacional E.164 (ejemplo: 573001234567), conocido tÃ©cnicamente como MSISDN.

Flujo en PHP: El caso de uso invocarÃ¡ al repositorio (UserRepository->findByPhone($msisdn)) para resolver si el nÃºmero estÃ¡ asociado a un client_profile_id activo. Si existe, se inicializa el contexto de ese cliente de manera transparente; si no existe, el bot iniciarÃ¡ automÃ¡ticamente el flujo guiado de registro (RF-01).

2. Estructura Unificada de Respuestas de Error (RFC 7807)
Para evitar inconsistencias, absolutamente todos los errores devueltos por la API (desde excepciones de base de datos hasta fallos de validaciÃ³n de negocio) utilizarÃ¡n el tipo de medio application/problem+json:

A. Formato General de Error (Ejemplo 400 / 401 / 500)
JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/unauthorized",
  "title": "No autorizado",
  "status": 401,
  "detail": "El token JWT suministrado ha expirado o la firma es invÃ¡lida.",
  "instance": "/api/v1/catalog/services"
}
B. Formato de Error de ValidaciÃ³n de Datos (CÃ³digo HTTP 422 - Unprocessable Entity)
Cuando las reglas de negocio o los tipos de datos del JSON de entrada no se cumplan, se listarÃ¡n los campos especÃ­ficos afectados:

JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/validation-failed",
  "title": "Error de validaciÃ³n en la entidad",
  "status": 422,
  "detail": "Los datos enviados contienen errores sintÃ¡cticos o violan reglas de negocio.",
  "instance": "/api/v1/iam/register",
  "invalid_params": [
    {
      "name": "email",
      "reason": "El formato del correo electrÃ³nico no es vÃ¡lido."
    },
    {
      "name": "username",
      "reason": "El nombre de usuario ya se encuentra registrado en el sistema."
    }
  ]
}
3. EspecificaciÃ³n de Endpoints: MÃ³dulo IAM
Endpoint: AutenticaciÃ³n de Usuarios (PWA / App)
Ruta: /api/v1/iam/login

MÃ©todo: POST

Cabeceras: Content-Type: application/json

Cuerpo de la PeticiÃ³n (Request Body):

JSON
{
  "username": "clientex_99",
  "password": "SecurePassword123!"
}
Respuestas:

200 OK (Credenciales VÃ¡lidas):

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

Endpoint: Registro AutÃ³nomo de Clientes (RF-01)
Ruta: /api/v1/iam/register

MÃ©todo: POST

Cuerpo de la PeticiÃ³n:

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
422 Unprocessable Entity: Si el correo o el usuario ya existen, o el telÃ©fono no sigue el patrÃ³n E.164.

4. EspecificaciÃ³n de Endpoints: MÃ³dulo Catalog & Pricing
Endpoint: ExploraciÃ³n Filtrada del CatÃ¡logo (RF-04)
Ruta: /api/v1/catalog/services

MÃ©todo: GET

ParÃ¡metros de Consulta (Query Params): ?category_id=2&search=Manicure&limit=10&page=1

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
      "name": "Manicure SemiæŠ€æœ¯",
      "description": "Tratamiento completo de uÃ±as con esmaltado semipermanente.",
      "price": 15.00,
      "duration_minutes": 45,
      "is_active": 1
    }
  ]
}
Endpoint: CreaciÃ³n de CampaÃ±a de PromociÃ³n (Administrador - RF-13 / RF-21)
Ruta: /api/v1/catalog/promotions

MÃ©todo: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Rol restringido a SUPER_ADMIN o BRANCH_ADMIN)

Cuerpo de la PeticiÃ³n:

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
  "message": "CampaÃ±a promocional creada y vinculada a los servicios seleccionados."
}
403 Forbidden: Si el usuario no tiene los privilegios adecuados en su JWT.

5. EspecificaciÃ³n de Endpoints: MÃ³dulo Staffing & Availability
Endpoint: Consulta de Disponibilidad de un Especialista (RF-05, RF-14)
Ruta: /api/v1/staffing/professionals/{professional_profile_id}/slots

MÃ©todo: GET

ParÃ¡metros de Consulta (Query Params): ?branch_id=1&date=2026-06-15&service_id=2

DescripciÃ³n: Cruza la tabla work_schedule, las excepciones de agenda y las citas activas en appointment para devolver una matriz lineal de bloques horarios libres listos para ser reservados.

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
Endpoint: ConfiguraciÃ³n de la Malla Horaria Semanal (Administrador - RF-14)
Ruta: /api/v1/staffing/schedules

MÃ©todo: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Roles: SUPER_ADMIN, BRANCH_ADMIN)

Cuerpo de la PeticiÃ³n (Request Body):

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

6. EspecificaciÃ³n de Endpoints: MÃ³dulo Transaccional de Agendamiento (Booking Core)
Endpoint: CreaciÃ³n Concurrente de Cita (PWA / App / WhatsApp - RF-06, RF-07)
Ruta: /api/v1/booking/appointments

MÃ©todo: POST

Estrategia de Control: Este endpoint debe implementar el bloqueo pesimista (FOR UPDATE) detallado en el diseÃ±o modular para validar de manera atÃ³mica el solapamiento temporal y blindar el sistema contra overbooking ante rÃ¡fagas del Bot o la PWA.

Cuerpo de la PeticiÃ³n:

JSON
{
  "client_profile_id": 34,
  "professional_profile_id": 14,
  "branch_id": 1,
  "promotion_id": 5, 
  "scheduled_timestamp": "2026-06-15 09:00:00",
  "notes": "Cliente solicita cabina aislada por temas de privacidad."
}
LÃ³gica del Controlador PHP: El sistema calcularÃ¡ dinÃ¡micamente la duraciÃ³n del servicio (obtenida del catÃ¡logo) para inyectar automÃ¡ticamente el campo estimated_end_timestamp y procesar el cÃ¡lculo financiero de total_price y final_price antes de la inserciÃ³n fÃ­sica.

Respuestas:

201 Created (Reserva Exitosa):

JSON
{
  "success": true,
  "message": "Cita agendada y bloqueada con Ã©xito.",
  "data": {
    "appointment_id": 50812,
    "appointment_status": "PENDING",
    "scheduled_timestamp": "2026-06-15 09:00:00",
    "estimated_end_timestamp": "2026-06-15 09:45:00",
    "total_price": 15.00,
    "final_price": 12.75
  }
}
422 Unprocessable Entity (ColisiÃ³n de Horario / Bloqueo Anti-Overbooking):

JSON
{
  "type": "https://api.esteticacarolinamora.com/errors/schedule-collision",
  "title": "Conflicto de disponibilidad de agenda",
  "status": 422,
  "detail": "El especialista seleccionado ya cuenta con un servicio agendado o en progreso en el rango horario solicitado.",
  "instance": "/api/v1/booking/appointments"
}
Endpoint: ReprogramaciÃ³n AutÃ³noma de Citas (RF-08, RF-09)
Ruta: /api/v1/booking/appointments/{appointment_id}/reschedule

MÃ©todo: PUT

Cuerpo de la PeticiÃ³n:

JSON
{
  "new_scheduled_timestamp": "2026-06-18 14:00:00"
}
Restricciones del Dominio en PHP: El controlador debe validar que la cita original no estÃ© en estado IN_PROGRESS o COMPLETED. Adicionalmente, evaluarÃ¡ el lÃ­mite de reprogramaciÃ³n autÃ³noma parametrizado por la sucursal (autonomous_reschedule_limit) consultando la tabla branch.

Respuestas:

200 OK: Regresa el objeto mutado reflejando el nuevo scheduled_timestamp e incremental en el log inmutable.

400 Bad Request: Si el cliente superÃ³ el lÃ­mite permitido de cambios automÃ¡ticos.

Endpoint: CancelaciÃ³n LÃ³gica de Cita (RF-09)
Ruta: /api/v1/booking/appointments/{appointment_id}/cancel

MÃ©todo: PATCH

Cuerpo de la PeticiÃ³n:

JSON
{
  "change_reason": "CancelaciÃ³n solicitada por el usuario desde el bot de WhatsApp."
}
ValidaciÃ³n de la Regla de Oro (24 Horas): El caso de uso restarÃ¡ scheduled_timestamp del tiempo actual del servidor. Si el delta es menor que las horas parametrizadas en branch.cancellation_hours_notice, denegarÃ¡ la cancelaciÃ³n autÃ³noma o aplicarÃ¡ la penalizaciÃ³n financiera configurada.

Respuestas:

200 OK: Muta appointment_status a 'CANCELLED' e inserta la trazabilidad en appointment_history.

Endpoints de RecepciÃ³n: GestiÃ³n Operativa en Sucursal (RF-17, RF-18)
Para controlar el flujo de los clientes dentro de la sucursal, la recepcionista consume endpoints semÃ¡nticos que guÃ­an las transiciones lÃ³gicas de la mÃ¡quina de estados:

Check-In Presencial (Llegada del Cliente - RF-17):

Ruta: /api/v1/booking/appointments/{appointment_id}/check-in

MÃ©todo: PATCH

AcciÃ³n: Cambia appointment_status de CONFIRMED o PENDING hacia IN_PROGRESS. Registra al operador en appointment_history.

Respuesta 200 OK: {"success": true, "new_status": "IN_PROGRESS"}

FinalizaciÃ³n TÃ©cnica del Servicio (Cierre de Cabina - RF-18):

Ruta: /api/v1/booking/appointments/{appointment_id}/complete

MÃ©todo: PATCH

AcciÃ³n: Cambia appointment_status hacia COMPLETED. Esta transiciÃ³n dispara de manera automÃ¡tica el evento de dominio AppointmentCompletedEvent, inhabilitando cualquier modificaciÃ³n posterior sobre los tiempos de la cita y habilitando la facturaciÃ³n en el POS.

Respuesta 200 OK: {"success": true, "new_status": "COMPLETED"}

EspecificaciÃ³n de Endpoints: MÃ³dulo Financiero y Caja (Billing & POS)
Endpoint: Registro de Pago Presencial en Punto de Venta (POS - RF-12, RF-18)
Ruta: /api/v1/billing/cash-desk/payments

MÃ©todo: POST

Cabeceras: Authorization: Bearer <JWT_TOKEN> (Roles: SUPER_ADMIN, BRANCH_ADMIN, RECEPCIONIST)

DescripciÃ³n: Registra el cobro en el mostrador fÃ­sico de la sucursal para una cita que ha transicionado al estado COMPLETED.

Cuerpo de la PeticiÃ³n (Request Body):

JSON
{
  "appointment_id": 50812,
  "amount": 12.75,
  "payment_method": "CASH", 
  "transaction_reference": "POS-001-50812",
  "notes": "Pago exacto recibido en efectivo. Se entrega tirilla fÃ­sica."
}
Respuestas:

201 Created:

JSON
{
  "success": true,
  "payment_id": 98412,
  "payment_status": "COMPLETED",
  "registered_at": "2026-06-06 14:05:22",
  "message": "TransacciÃ³n contable POS asentada exitosamente."
}
422 Unprocessable Entity: Si el monto no coincide con el balance calculado para la cita o si la referencia de transacciÃ³n ya existe (UNIQUE constraint violation).

Endpoint: Webhook de la Pasarela de Pagos Online (RF-12)
Ruta: /api/v1/billing/payments/webhook

MÃ©todo: POST

Cabeceras: X-Gateway-Signature: <SHA256_HASH_VERIFICATION>

DescripciÃ³n: Punto de entrada asÃ­ncrono e impersonal consumido por la pasarela externa (ej. Stripe o pasarela local). No requiere JWT de usuario, pero valida la autenticidad mediante la firma criptogrÃ¡fica en las cabeceras.

Cuerpo de la PeticiÃ³n:

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

MÃ©todo: GET

ParÃ¡metros de Consulta: ?branch_id=1&date=2026-06-06

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
8. EspecificaciÃ³n de Endpoints: MÃ³dulo AnalÃ­tico y CRM (CRM & Analytics)
Endpoint: Registro de CalificaciÃ³n de Servicio (RF-11, RF-20)
Ruta: /api/v1/crm/ratings

MÃ©todo: POST

DescripciÃ³n: Consumido directamente desde la encuesta asÃ­ncrona enviada al cliente.

Cuerpo de la PeticiÃ³n:

JSON
{
  "appointment_id": 50812,
  "score": 5,
  "comments": "Excelente atenciÃ³n en la manicura. El personal fue muy detallista."
}
ValidaciÃ³n de Dominio: El controlador PHP validarÃ¡ obligatoriamente que score estÃ© en el rango integro del backend [1, 5] y que la cita referenciada se encuentre en estado COMPLETED.

Respuestas:

201 Created: {"success": true, "rating_id": 4321}

9. Contrato de Entrada: Webhook de Meta Cloud API (WhatsApp Omnicanal - RF-07)
Este endpoint es el punto de enlace crÃ­tico para el procesamiento conversacional. Recibe las notificaciones crudas desde los servidores de Meta cuando un cliente envÃ­a un mensaje de texto o interactÃºa con un botÃ³n estructurado del bot.

Ruta: /api/v1/webhooks/whatsapp

MÃ©todo: POST

Cabeceras Obligatorias: X-Hub-Signature-256: sha256=<HEX_DIGEST> (Validada internamente usando el App Secret de Meta para asegurar el origen).

Payload EstÃ¡ndar de Entrada (Payload Enviado por Meta):
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
                  "body": "Quiero reservar una cita para manicure maÃ±ana"
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
Protocolo de ExtracciÃ³n e InyecciÃ³n en el Backend en PHP:
Para que Antigravity 2.0 procese el flujo sin romper capas, el controlador del webhook ejecutarÃ¡ secuencialmente los siguientes pasos tÃ©cnicos:

Aislamiento del MSISDN: ExtraerÃ¡ el valor de entry[0].changes[0].value.messages[0].from ("573001234567"). Este string representa el PhoneNumber del Value Object del dominio.

ResoluciÃ³n Transparente de Perfil: ConsultarÃ¡ de manera atÃ³mica el repositorio de clientes. Si el nÃºmero telefÃ³nico existe, inyectarÃ¡ el client_profile_id resultante en el contenedor de dependencias del caso de uso.

AnÃ¡lisis de IntenciÃ³n (NLP / Keyword Parsing): ExtraerÃ¡ el string del campo text.body. Si la intenciÃ³n coincide con un flujo estructurado (ej. "reservar", "cancelar"), enrutarÃ¡ la lÃ³gica de negocio directamente hacia los mismos casos de uso consumidos por la PWA (vÃ­a CreateAppointmentUseCase o CancelAppointmentUseCase), garantizando que las validaciones contra condiciones de carrera (FOR UPDATE) y lÃ­mites horarios sean idÃ©nticas y centralizadas.

10. CÃ³digos de Estado HTTP y SemÃ¡ntica Global de la API
Para garantizar la homogeneidad de la integraciÃ³n, la API se compromete a responder usando estrictamente los siguientes cÃ³digos del estÃ¡ndar HTTP:

200 OK: Solicitud procesada correctamente con retorno de datos (Consultas de catÃ¡logo, listados de citas, cierres de caja).

201 Created: InserciÃ³n fÃ­sica exitosa de un recurso inmutable en la base de datos (CreaciÃ³n de citas, registros de usuarios, pagos POS).

400 Bad Request: Error sintÃ¡ctico en el JSON o violaciÃ³n directa de rangos lÃ³gicos ordinarios en los parÃ¡metros.

401 Unauthorized: Ausencia de token de autorizaciÃ³n, expiraciÃ³n de credenciales JWT o firma invÃ¡lida.

403 Forbidden: Token vÃ¡lido pero el rol asignado no cuenta con los privilegios suficientes en la matriz RBAC para acceder al recurso.

422 Unprocessable Entity: Solicitud sintÃ¡cticamente correcta pero invÃ¡lida a nivel semÃ¡ntico de negocio (Colisiones de disponibilidad, bloqueos por sobre-reserva concurrentes, violaciones a la regla de cancelaciÃ³n de 24 horas).

500 Internal Server Error: Excepciones de infraestructura no controladas (PÃ©rdida de conectividad con el servidor MySQL de Hostinger, desbordamiento de memoria). El payload devuelto ocultarÃ¡ el Stack-Trace tÃ©cnico en producciÃ³n por motivos de seguridad perimetral, registrÃ¡ndolo Ãºnicamente de forma interna.
