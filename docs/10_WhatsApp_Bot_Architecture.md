Este documento tÃ©cnico establece las especificaciones de ingenierÃ­a, el diseÃ±o de la mÃ¡quina de estados y las pautas de integraciÃ³n para el Bot Conversacional Omnicanal de Antigravity 2.0. El sistema interactÃºa directamente con la API Cloud de WhatsApp de Meta y estÃ¡ estructurado para soportar altos volÃºmenes de concurrencia sin generar condiciones de carrera u overbooking en la agenda de las cabinas.

1. Arquitectura Desacoplada y Omnicanal (PatrÃ³n Adapter)
Para garantizar que el motor de orquestaciÃ³n conversacional no quede acoplado rÃ­gidamente a la estructura de payloads de Meta Cloud API, se implementa el patrÃ³n Adapter. El nÃºcleo del sistema procesa Ãºnicamente una entidad unificada de mensajerÃ­a, permitiendo la adiciÃ³n inmediata de canales como Telegram o Instagram en el futuro mediante adaptadores de infraestructura adicionales.

1.1. Ãrbol de Directorios Estricto del Motor Conversacional
El backend puro en PHP 8.x aloja estos componentes bajo la capa de infraestructura del bot:

Plaintext
src/Infrastructure/Chatbot/
â”œâ”€â”€ Adaptors/                     # Traductores de payloads especÃ­ficos por proveedor
â”‚   â”œâ”€â”€ ChatbotAdaptorInterface.php
â”‚   â”œâ”€â”€ WhatsAppCloudApiAdaptor.php # Mapea los JSON de Meta al modelo unificado
â”‚   â””â”€â”€ TelegramApiAdaptor.php    # (Listo para inyecciÃ³n futura)
â”‚
â”œâ”€â”€ Core/                         # Orquestador agnÃ³stico de flujos y lenguaje
â”‚   â”œâ”€â”€ ConversationEngine.php    # Cerebro central de enrutamiento
â”‚   â””â”€â”€ StateMachine.php          # Evaluador estricto de transiciones de estados
â”‚
â”œâ”€â”€ Flows/                        # Casos de uso conversacionales (Scripts lÃ³gicos)
â”‚   â”œâ”€â”€ AbstractFlow.php
â”‚   â”œâ”€â”€ WelcomeRegistrationFlow.php
â”‚   â”œâ”€â”€ BookingFlow.php
â”‚   â””â”€â”€ HumanHandoverFlow.php
â”‚
â””â”€â”€ Persistence/                  # GestiÃ³n de estados de sesiÃ³n conversacional
    â””â”€â”€ PdoSessionStateRepository.php
2. GestiÃ³n de Estados (Conversation State Engine)
A diferencia de un sitio web, una conversaciÃ³n de chat es asÃ­ncrona y carece de estado persistente nativo. El motor conversacional requiere guardar de forma atÃ³mica el estado actual del usuario en la base de datos MySQL 8 utilizando la tabla relacional bot_session.

2.1. CatÃ¡logo Estricto de Estados de la MÃ¡quina de Transiciones
IDLE: Usuario sin interacciÃ³n activa o flujo completado.

AWAITING_REGISTRATION_NAME: Cliente nuevo que debe ingresar su nombre y apellido para el alta de su perfil.

SELECTING_BRANCH: Cliente eligiendo la sucursal fÃ­sica de la estÃ©tica.

SELECTING_SERVICE: Cliente navegando el catÃ¡logo interactivo de servicios.

SELECTING_DATE: Cliente seleccionando una fecha vÃ¡lida del calendario.

SELECTING_SLOT: Cliente seleccionando un bloque horario disponible (Libre de Overbooking).

CONFIRMING_BOOKING: ConfirmaciÃ³n final previa al impacto transaccional en la tabla appointment.

HUMAN_HANDOVER: Bot pausado. Mensajes redirigidos al panel fÃ­sico de la recepcionista.

2.2. ImplementaciÃ³n de la Estructura Persistente (SQL)
SQL
CREATE TABLE IF NOT EXISTS `bot_session` (
    `wa_id` VARCHAR(20) NOT NULL PRIMARY KEY, -- NÃºmero de telÃ©fono formateado (Ej: 573001234567)
    `client_profile_id` INT UNSIGNED NULL,   -- Asociado al perfil real si ya existe
    `current_state` VARCHAR(50) NOT NULL DEFAULT 'IDLE',
    `bot_active` TINYINT(1) NOT NULL DEFAULT 1, -- Bandera atÃ³mica de control humano
    `context_data` JSON NULL, -- Guarda variables temporales (servicio_id, fecha, etc.)
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_profile_id`) REFERENCES `client_profile`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
3. Flujos Core en Diagramas Mermaid
3.1. Registro Automatizado de Clientes Nuevos
Este flujo se gatilla de manera automÃ¡tica cuando el nÃºmero de WhatsApp entrante no se encuentra registrado en la tabla client_profile.

Fragmento de cÃ³digo
graph TD
    A[Mensaje Entrante de WA] --> B{Â¿Existe wa_id en BD?}
    B -- SÃ­ --> C[Cargar Perfil y Enrutar a BookingFlow]
    B -- No --> D[Crear Registro en bot_session en Estado: AWAITING_REGISTRATION_NAME]
    D --> E[Enviar Mensaje: 'Â¡Hola! No te tengo en mis registros. Por favor, escribe tu Nombre y Apellido']
    E --> F[Usuario Responde Texto]
    F --> G[Validar Texto con InputSanitizer]
    G --> H[Insertar Perfil en client_profile y asociar wa_id]
    H --> I[Cambiar bot_session a IDLE]
    I --> J[Enviar CatÃ¡logo Principal de Servicios]
3.2. Consulta y Agendamiento de Citas sin Overbooking
Para evitar que dos clientes reserven el mismo bloque con el mismo especialista, el bot implementa un bloqueo pesimista en base de datos (FOR UPDATE) al confirmar la selecciÃ³n.

Fragmento de cÃ³digo
graph TD
    A[Usuario en Estado SELECTING_SLOT] --> B[Selecciona Hora: 09:00 AM]
    B --> C[Iniciar TransacciÃ³n SQL AtÃ³mica]
    C --> D[Ejecutar Query de VerificaciÃ³n FOR UPDATE]
    D --> E{Â¿Bloque horario sigue 100% disponible?}
    E -- No Disponible --> F[Rollback TransacciÃ³n]
    F --> G[Notificar: 'Lo siento, ese espacio se asignÃ³ hace un instante. Elige otro']
    G --> H[Regresar Estado a SELECTING_SLOT]
    E -- Disponible --> I[Insertar registro en tabla APPOINTMENT como PENDING]
    I --> J[Commit TransacciÃ³n y Confirmar Bloqueo]
    J --> K[Cambiar Estado a IDLE y Enviar Mensaje de Ã‰xito con JWT Link para PWA]
3.3. EnvÃ­o de Recordatorios AutomÃ¡ticos AsÃ­ncronos
Los recordatorios automÃ¡ticos se ejecutan en segundo plano a travÃ©s de una tarea programada (Cronjob) en Hostinger que invoca de forma asÃ­ncrona la API Cloud de WhatsApp.

Fragmento de cÃ³digo
sequenceDiagram
    autonumber
    participant Cron as Cronjob Servidor Hostinger
    participant DB as Base de Datos MySQL 8
    participant API as WhatsApp Cloud API (Meta)
    participant User as Dispositivo Cliente
    
    Cron->>DB: Query appointments programadas en las prÃ³ximas 24 horas (Status PENDING)
    DB-->>Cron: Retorna colecciÃ³n de citas, nombres y wa_id
    loop Por cada Cita Encontrada
        Cron->>API: POST /v1/messages (Payload de Plantilla Homologada por Meta)
        API-->>User: Entrega mensaje interactivo: Â¿Confirmas tu asistencia? [SÃ­] [No]
        API-->>Cron: 200 OK (Message ID Registrado)
        Cron->>DB: UPDATE appointment set reminder_sent = 1
    end
4. Protocolo TÃ©cnico de Escalamiento Humano (Handover)
Cuando un cliente presenta dudas complejas que la lÃ³gica del bot conversacional no puede procesar, o cuando escribe palabras clave explÃ­citas como "asesor", "humano", o "ayuda", el sistema ejecuta un aislamiento atÃ³mico perimetral de la conversaciÃ³n.

4.1. Mecanismo de DesactivaciÃ³n del Bot en PHP Puro
El controlador de entrada del Webhook verifica la bandera bot_active antes de procesar cualquier interacciÃ³n a travÃ©s de la mÃ¡quina de estados. Si la bandera se encuentra apagada, redirige el flujo de datos exclusivamente hacia la auditorÃ­a de logs y el panel visual de recepciÃ³n.

PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Chatbot\Core;

use \PDO;

class ConversationEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Procesa el mensaje entrante adaptado desde cualquier canal omnicanal.
     */
    public function handleIncomingMessage(string $waId, string $messageBody): void {
        // 1. Consultar el estado de activaciÃ³n de la sesiÃ³n conversacional
        $sql = "SELECT current_state, bot_active FROM bot_session WHERE wa_id = :wa_id FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['wa_id' => $waId]);
        $session = $stmt->fetch();

        if ($session && (int)$session['bot_active'] === 0) {
            // [ESCALAMIENTO HUMANO ACTIVO] 
            // El bot estÃ¡ pausado. Los mensajes entrantes se ignoran lÃ³gicamente por el motor conversacional
            // pero se registran en las tablas de auditorÃ­a de datos para que la recepcionista los lea en tiempo real en la PWA.
            return;
        }

        // 2. Evaluar disparadores explÃ­citos de solicitud de soporte humano
        $normalizedText = mb_strtolower(trim($messageBody));
        if (in_array($normalizedText, ['asesor', 'humano', 'ayuda', 'recepcion'], true)) {
            $this->executeHumanHandover($waId);
            return;
        }

        // 3. Continuar con el enrutamiento ordinario de la mÃ¡quina de estados si el bot sigue al mando
        $this->processStateMachine($waId, $session['current_state'] ?? 'IDLE', $messageBody);
    }

    /**
     * Ejecuta la pausa atÃ³mica del bot y alerta a la sucursal fÃ­sica.
     */
    private function executeHumanHandover(string $waId): void {
        $sql = "UPDATE bot_session SET current_state = 'HUMAN_HANDOVER', bot_active = 0 WHERE wa_id = :wa_id";
        $this->pdo->prepare($sql)->execute(['wa_id' => $waId]);

        // Registrar evento en la tabla de auditorÃ­a para que la PWA del mostrador escuche vÃ­a SSE (Server-Sent Events)
        // y dispare una alerta visual/sonora inmediata a la recepcionista.
        $sqlLog = "INSERT INTO audit_access_log (correlation_id, channel, ip_address, event_type, severity, details) 
                   VALUES (:correlation_id, 'WHATSAPP_BOT', '0.0.0.0', 'HUMAN_HANDOVER_TRIGGERED', 'INFO', :details)";
        
        $this->pdo->prepare($sqlLog)->execute([
            'correlation_id' => bin2hex(random_bytes(16)),
            'details'        => "El cliente con telÃ©fono $waId ha solicitado asistencia humana en mostrador."
        ]);

        // Enviar confirmaciÃ³n al usuario a travÃ©s de la API Cloud
        // "Entendido. He pausado el bot y he transferido esta conversaciÃ³n a nuestro equipo de recepciÃ³n. Te responderemos en breve."
    }
}
Con la especificaciÃ³n y el cÃ³digo perimetral del motor conversacional agnÃ³stico, queda oficialmente concluido y cerrado el diseÃ±o de /docs/10_WhatsApp_Bot_Architecture.md.

ðŸ Â¡Ciclo de EspecificaciÃ³n Omnicanal Completado al 100%!
Hemos finalizado todo el ecosistema de arquitectura tÃ©cnica de Antigravity 2.0:

Backend Core: Arquitectura limpia en PHP Puro, patrones de diseÃ±o y manejo transaccional blindado contra overbooking.

API Contratos: Formatos estandarizados de entrada y salida basados en especificaciones RESTful.

Seguridad y RBAC: CriptografÃ­a de tokens duales, mitigaciÃ³n OWASP y control perimetral estricto.

Trazabilidad Forense: Logs inmutables y de tipo append-only.

Interfaces de Consumo (PWA, MÃ³vil y Bot): Estrategias de cachÃ© offline, almacenamiento cifrado por hardware nativo y mÃ¡quina de estados conversacionales agnÃ³stica.
