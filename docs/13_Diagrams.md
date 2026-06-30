Este documento traduce la topología operativa, los patrones de diseño arquitectónicos y los flujos transaccionales del ecosistema digital de "Estética Carolina Mora" a representaciones visuales estructuradas mediante código legible de Mermaid. Estos diagramas sirven de base de ingeniería para el desarrollo automatizado, garantizando el cumplimiento de los principios de desacoplamiento, inmutabilidad y seguridad perimetral.

1. Arquitectura General del Sistema
Este diagrama de bloques macro representa la segregación de responsabilidades de las tres capas del sistema (Hexagonal/Clean Architecture), los límites lógicos de los contextos delimitados (Bounded Contexts) y las integraciones asíncronas con servicios externos (Meta Cloud API para WhatsApp).

Fragmento de código
graph TB
    %% Definición de Nodos de Capas Externas (Infraestructura / Clientes)
    subgraph Capa_Cliente [Capa de Presentación y Canales]
        PWA[PWA Cliente / Admin <br> HTML5 / JS / Service Worker]
        WA_Bot[Usuario Final <br> Chatbot WhatsApp]
        Meta_API[Meta Cloud API <br> Servidores de WhatsApp]
    end

    subgraph Capa_Infraestructura [Capa de Infraestructura - src/Infrastructure]
        direction TB
        Http_Conn[Controladores HTTP <br> REST Controllers]
        WA_Webhook[WhatsApp Webhook <br> API Parser]
        Router[Router Engine <br> Shared/Routing]
        PDO_Conn[Conexión Central PDO <br> Database/ConnectionFactory]
        Queue_Worker[Notification Queue Worker <br> Tareas de Consola CLI / Cron]
    end

    %% Definición de la Capa de Aplicación (Casos de Uso)
    subgraph Capa_Aplicacion [Capa de Aplicación - src/Application]
        direction LR
        UC_IAM[Casos de Uso: IAM <br> Registro / Autenticación]
        UC_Booking[Casos de Uso: Booking <br> Citas / Overbooking / Check-In]
        UC_Catalog[Casos de Uso: Catalog <br> Catálogo / Promociones]
        UC_Staffing[Casos de Uso: Staffing <br> Disponibilidad / Agendas]
        UC_Billing[Casos de Uso: Billing <br> Pasarela / POS Facturación]
    end

    %% Definición del Núcleo del Dominio (Entidades e Interfaces)
    subgraph Capa_Dominio [Capa del Dominio Core - src/Domain]
        direction LR
        Dom_Entities[Entidades Puras de Negocio <br> User, Appointment, Service...]
        Dom_VO[Objetos de Valor - Value Objects <br> Money, TimeRange, Phone]
        Dom_Events[Despachador de Eventos <br> EventDispatcher]
        Dom_Interfaces[Interfaces de Repositorios <br> UserRepositoryInterface...]
    end

    subgraph Capa_Datos [Capa de Persistencia Física]
        MySQL_DB[(MySQL 8.0 Engine <br> InnoDB / 32 Entidades)]
    end

    %% Relaciones y Flujos de Control (Inversión de Dependencias)
    PWA -->|Peticiones HTTPS JSON| Router
    Router --> Http_Conn
    WA_Bot -->|Mensajes de Texto / Opciones| Meta_API
    Meta_API -->|Eventos Webhook HTTPS| WA_Webhook
    
    Http_Conn --> UC_IAM & UC_Booking & UC_Catalog & UC_Staffing & UC_Billing
    WA_Webhook --> UC_Booking & UC_IAM
    Queue_Worker --> UC_Booking
    
    %% La aplicación orquesta el dominio
    UC_IAM & UC_Booking & UC_Catalog & UC_Staffing & UC_Billing --> Dom_Entities
    UC_Booking -.->|Dispara| Dom_Events
    
    %% Inversión de Dependencias: Infraestructura implementa Interfaces del Dominio
    Http_Conn & WA_Webhook & Queue_Worker --> Dom_Interfaces
    PDO_Conn -->|Ejecuta SQL Nativos| MySQL_DB
    Http_Conn & WA_Webhook & Queue_Worker -.-> PDO_Conn

    %% Estilos Visuales
    style PWA fill:#0ACFF7,stroke:#000,stroke-width:2px,color:#000
    style WA_Bot fill:#25D366,stroke:#000,stroke-width:2px,color:#fff
    style MySQL_DB fill:#F7F7F7,stroke:#000,stroke-width:3px,color:#000
    style Capa_Dominio fill:#fff,stroke:#F7F7F7,stroke-width:2px
2. Flujo Completo de la API REST
Este diagrama secuencial describe el ciclo de vida de una solicitud HTTP entrante desde que es emitida por la PWA, pasando por los filtros perimetrales de infraestructura (.htaccess, Router), la sanitización de datos de entrada vía Data Transfer Objects (DTOs), la ejecución transaccional aislada del Caso de Uso, hasta el formateo estandarizado de la respuesta según la norma RFC 7807.

Fragmento de código
sequenceDiagram
    autonumber
    actor Cliente as PWA / Admin Dashboard
    participant Htaccess as Web Server (.htaccess)
    participant Index as Front Controller (index.php)
    participant Router as Router Engine
    participant Ctrl as Controller (Infrastructure)
    participant DTO as Request DTO (Application)
    participant UC as Use Case (Application)
    participant Repo as PDO Repository (Infrastructure)
    participant DB as MySQL 8 Instance (Database)

    Cliente->>Htaccess: POST /api/v1/booking/create (JSON Payload + JWT Bearer Token)
    Note over Htaccess: Evalúa políticas de seguridad,<br/>fuerza HTTPS y reescribe URL.
    Htaccess->>Index: Enruta hacia index.php
    Index->>Router: Despacha URI y Método HTTP
    
    activate Router
    Note over Router: Valida firma del JWT Token<br/>y extrae identidad del usuario.
    Router->>Ctrl: Invoca método del Controlador Asociado
    deactivate Router
    
    activate Ctrl
    Ctrl->>DTO: Instancia y pasa arreglo $_POST crudo
    activate DTO
    Note over DTO: Ejecuta InputSanitizer,<br/>valida tipos estrictos (strict_types)<br/>y formatea estructuras.
    DTO-->>Ctrl: Retorna Objeto de Datos Seguro e Inmutable
    deactivate DTO
    
    Ctrl->>UC: Ejecuta caso de uso pasando el DTO (Inyección de Dependencias)
    activate UC
    Note over UC: Inicializa Transacción de Base de Datos.<br/>Valida Reglas de Negocio (Overbooking/Fechas).
    
    UC->>Repo: Solicita persistencia o consulta (ej: save/find)
    activate Repo
    Repo->>DB: Ejecuta Sentencia SQL Preparada (PDO Statement)
    activate DB
    DB-->>Repo: Retorna Filas Afectadas / Cursor de Datos
    deactivate DB
    Repo-->>UC: Retorna Entidades de Dominio
    deactivate Repo
    
    Note over UC: Confirma Transacción (Commit) si todo es correcto.
    UC-->>Ctrl: Retorna DTO de Respuesta (Success / Created)
    deactivate UC
    
    Note over Ctrl: Atrapa cualquier excepción global<br/>y la mapea a un formato RFC 7807 si hay errores.
    Ctrl->>Cliente: Envía HTTP 201 Created (JSON estructurado vía ResponseHelper)
    deactivate Ctrl
3. Arquitectura y Ciclo de Vida de la PWA
Este diagrama describe el modelo de ejecución fuera de línea (Offline-First) de la Progressive Web Application (PWA). Muestra el comportamiento del hilo principal frente al Service Worker, el almacenamiento en caché local estático (Cache Storage) y el enrutamiento inteligente de consultas dinámicas hacia la API REST en la nube.

Fragmento de código
graph TD
    %% Componentes del Cliente PWA
    subgraph Dispositivo_Cliente [Ámbito de la PWA en el Navegador]
        UI[Capa de Interfaz de Usuario <br> PWA Vistas DOM]
        App_Logic[Lógica de la Aplicación <br> Controladores JS / State]
        
        subgraph SW_Scope [Entorno Aislado del Service Worker]
            SW[Service Worker Core <br> sw.js]
            Cache_Strat{Estrategia de Caché <br> Network First / Stale While Revalidate}
        end

        subgraph Almacenamiento_Local [Almacenamiento del Dispositivo]
            Static_Cache[(Cache Storage <br> Assets, CSS, JS, HTML Base)]
            Indexed_DB[(IndexedDB <br> Borradores de Citas Offline)]
        end
    end

    subgraph Servidor_Cloud [Infraestructura Hostinger Cloud]
        Web_Server[Servidor Apache / Hostinger]
        API_Rest[API REST en PHP]
    end

    %% Flujos de Inicialización e Instalación
    UI -->|1. Solicita Registro| SW
    SW -->|2. Evento Install / Activate| Static_Cache

    %% Flujo de Operación de Recursos Estáticos
    App_Logic -->|3. Petición de Recurso Asset/Imagen| SW
    SW -->|4. Intercepta Fetch Event| Cache_Strat
    Cache_Strat -->|Opción A: Hit de Caché| Static_Cache
    Cache_Strat -->|Opción B: Miss de Caché| Web_Server

    %% Flujo de Operación Transaccional de Citas
    App_Logic -->|5. Guardar Reserva de Cita| SW
    SW -->|6. Validar Estado de Conexión| Conn_Check{¿Dispositivo Online?}
    
    Conn_Check -->|Sí| API_Rest
    Conn_Check -->|No| Indexed_DB
    
    Indexed_DB -.->|7. Sincronización en Background al recuperar red| SW
    SW -.->|8. Background Sync Payload| API_Rest

    %% Estilos
    style SW_Scope fill:#fff,stroke:#0ACFF7,stroke-width:2px
    style SW fill:#0ACFF7,stroke:#000,stroke-width:2px,color:#000
    style Static_Cache fill:#F7F7F7,stroke:#000,stroke-width:1px,color:#000
    style Indexed_DB fill:#F7F7F7,stroke:#000,stroke-width:1px,color:#000

    13_Diagrams.md (Parte 2 de 2)
4. Flujo del Chatbot de WhatsApp (Máquina de Estados conversacional)
Este diagrama de estados modela el flujo lógico e interactivo del asistente conversacional de la estética a través de la API de Meta. Cada nodo representa un estado guardado en la base de datos (wa_chat_session.current_state) y las flechas indican las transiciones validadas mediante la recepción de mensajes del usuario.

Fragmento de código
stateDiagram-v2
    [*] --> ESTADO_IDLE : El cliente saluda o envía un mensaje inicial

    state ESTADO_IDLE {
        [*] --> VerificarUrgencia
        VerificarUrgencia --> MenuPrincipal : No es una urgencia / Flujo Ordinario
        VerificarUrgencia --> AlertaAdmin : Es un caso crítico o reclamo grave
    }

    ESTADO_IDLE --> SELECCION_SUCURSAL : Opción "1. Reservar Cita"
    ESTADO_IDLE --> CONSULTA_HISTORIAL : Opción "2. Mis Citas"
    ESTADO_IDLE --> CANCELACION_CITA : Opción "3. Cancelar / Reprogramar"

    SELECCION_SUCURSAL --> SELECCION_SERVICIO : Cliente elige una sucursal física válida
    SELECCION_SUCURSAL --> ESTADO_IDLE : Opción "Regresar" / Timeout (15 min)

    SELECCION_SERVICIO --> SELECCION_ESPECIALISTA : Cliente elige un servicio o promoción
    
    SELECCION_ESPECIALISTA --> SELECCION_FECHA_HORA : Cliente elige especialista (o "Cualquiera")
    
    SELECCION_FECHA_HORA --> CONFIRMACION_RESERVA : Cliente selecciona un bloque de tiempo disponible (B-Tree Match)
    Note over SELECCION_FECHA_HORA: El bot consulta la matriz 'work_schedule' <br/> cruzando con excepciones e índices de concurrencia.

    CONFIRMACION_RESERVA --> PROCESO_PAGO_ONLINE : Elige "Pago en Línea" (Genera link externo)
    CONFIRMACION_RESERVA --> RESERVA_FINALIZADA : Elige "Pago en Sucursal" (Cita queda PENDING)

    PROCESO_PAGO_ONLINE --> RESERVA_FINALIZADA : Webhook confirma pago exitoso (Cita CONFIRMED)
    PROCESO_PAGO_ONLINE --> CONFIRMACION_RESERVA : Pago rechazado o cancelado por el cliente

    CONSULTA_HISTORIAL --> ESTADO_IDLE : Muestra las últimas 3 citas y regresa al inicio
    CANCELACION_CITA --> ESTADO_IDLE : Remueve o altera la tupla (ON DELETE RESTRICT activo) y notifica

    RESERVA_FINALIZADA --> [*] : El bot envía confirmación de ticket final y cierra sesión
5. Arquitectura de la Base de Datos (Topología del Esquema Relacional)
Debido a la magnitud del ecosistema de 32 entidades, este diagrama lógico agrupa las tablas físicas en Contextos Delimitados (Bounded Contexts) clave, mostrando las llaves foráneas primordiales (FK) y los motores de restricción e indexación que garantizan la integridad referencial y el rendimiento.

Fragmento de código
erDiagram
    %% CONTEXTO 1: IDENTITY & ACCESS MANAGEMENT (IAM)
    USER ||--o| CLIENT_PROFILE : "tiene un (1:1)"
    USER ||--o| PROFESSIONAL_PROFILE : "tiene un (1:1)"
    USER ||--oM USER_SESSION : "crea (1:N - CASCADE)"
    USER ||--oM SYSTEM_AUDIT_LOG : "genera (1:N - SET NULL)"

    %% CONTEXTO 2: CATALOG & PROMOTIONS
    BRANCH ||--oM SERVICE_CATALOG : "ofrece (1:N)"
    SERVICE ||--oM SERVICE_CATALOG : "está en (1:N)"
    SERVICE ||--oM PROMOTION_SERVICE : "aplica a (1:N)"
    PROMOTION ||--oM PROMOTION_SERVICE : "contiene (1:N)"

    %% CONTEXTO 3: STAFFING & AVAILABILITY
    BRANCH ||--oM WORK_SCHEDULE : "asigna (1:N)"
    PROFESSIONAL_PROFILE ||--oM WORK_SCHEDULE : "cumple (1:N - CASCADE)"
    PROFESSIONAL_PROFILE ||--oM SCHEDULE_EXCEPTION : "registra (1:N)"

    %% CONTEXTO 4: BOOKING & OPERATIONS (CORE)
    CLIENT_PROFILE ||--oM APPOINTMENT : "reserva (1:N)"
    PROFESSIONAL_PROFILE ||--oM APPOINTMENT : "atiende (1:N)"
    BRANCH ||--oM APPOINTMENT : "alberga (1:N)"
    PROMOTION ||--oM APPOINTMENT : "aplica (1:N - SET NULL)"
    APPOINTMENT ||--oM APPOINTMENT_DETAIL : "desglosa (1:N - CASCADE)"
    SERVICE ||--oM APPOINTMENT_DETAIL : "incluye (1:N)"

    %% CONTEXTO 5: BILLING & POS
    APPOINTMENT ||--oI INVOICE : "genera (1:1 - RESTRICT)"
    INVOICE ||--oM PAYMENT : "registra (1:N)"

    %% CONTEXTO 6: INTEGRATIONS (WHATSAPP BOT)
    USER ||--oM WA_CHAT_SESSION : "asocia (1:N)"
    WA_NOTIFICATION_QUEUE ||--oM WA_MESSAGE_LOG : "muta a (1:N)"

    %% Detalles de Atributos Críticos de Control e Índices
    USER {
        bigint_unsigned user_id PK
        varchar_191 email UK
        varchar_20 auth_phone UK
        enum_status account_status
    }
    WORK_SCHEDULE {
        bigint_unsigned work_schedule_id PK
        int_unsigned branch_id FK
        bigint_unsigned professional_profile_id FK
        tinyint day_of_week "INDEX (idx_schedule_matrix)"
        time start_time
        time end_time
    }
    APPOINTMENT {
        bigint_unsigned appointment_id PK
        bigint_unsigned client_profile_id FK
        bigint_unsigned professional_profile_id FK
        timestamp scheduled_timestamp "INDEX (idx_appointment_concurrency)"
        timestamp estimated_end_timestamp
        enum_status appointment_status
    }
    INVOICE {
        bigint_unsigned invoice_id PK
        bigint_unsigned appointment_id FK
        varchar_50 invoice_number UK
        decimal_10_2 total_amount
    }
6. Diagrama de Despliegue Físico (Infraestructura Homologada)
Este diagrama representa la distribución arquitectónica del sistema en el entorno de producción de Hostinger Business, visualizando los límites perimetrales de seguridad, las zonas privadas del servidor aisladas del alcance web, y las dependencias de red de APIs externas.

Fragmento de código
graph TL
    %% Entidades de Red Externa (Clientes e Internet)
    subgraph Red_Publica [Acceso Público - Internet]
        User_Device[PWA Cliente / Administrador <br> Dispositivo Móvil o Desktop]
        Meta_Servers[Servidores de Meta Cloud <br> API de WhatsApp]
    end

    %% Perímetro de Seguridad en la Frontera del Servidor
    subgraph Cloud_Hostinger [Hostinger Premium Shared Environment]
        
        subgraph Capa_Perimetral [Frontera de Seguridad Apache]
            FW[.htaccess Firewall]
            SSL[Certificado SSL Let's Encrypt <br> TLS 1.3 Forzado]
        end

        subgraph Public_Zone [Directorio Público: public_html/]
            FC[Front Controller <br> index.php]
            Router[Router Engine <br> Shared/Routing]
            Assets[Recursos Estáticos <br> PWA Manifest / CSS / JS]
        end

        subgraph Private_Zone [Directorio Privado: Fuera de Alcance Web]
            bootstrap[bootstrap.php <br> Container Manual DI]
            
            subgraph Backend_Layers [Estructura src/ de Capas]
                Domain[src/Domain <br> Entidades / Interfaces]
                Application[src/Application <br> Casos de Uso]
                Infrastructure[src/Infrastructure <br> Repositorios PDO / HTTP]
            end

            Env_File[[Archivo .env <br> Credenciales Encriptadas]]
            Logs_Dir[(Logs del Sistema <br> PSR-3 Files)]
            Cron_Engine[ hPanel Cron Jobs <br> 1 Min / 1 Hora / Diarios]
        end

        subgraph Storage_Database [Capa de Datos Protegida]
            Socket_Conn(Socket Local Unix <br> localhost:3306)
            MySQL_DB[(MySQL 8.0 Engine <br> u123456789_estetica_db)]
        end
    end

    %% Trazabilidad de Flujos de Red e Interconexiones
    User_Device -->|1. Petición HTTPS Dinámica| SSL
    SSL --> FW
    FW --> FC
    FC -->|2. Invoca subiendo un nivel| bootstrap
    bootstrap --> Router
    Router --> Backend_Layers

    Meta_Servers -->|Sincronización Webhook| SSL

    %% Inyección de variables y dependencias
    Env_File -.->|Lectura de Configuración| bootstrap
    Backend_Layers -.->|Escritura de Errores| Logs_Dir
    Cron_Engine -->|Ejecución CLI por Consola PHP| Backend_Layers

    %% Conexión de Persistencia Segura
    Backend_Layers --> Socket_Conn
    Socket_Conn --> MySQL_DB

    %% Estilos de los Componentes de Despliegue
    style User_Device fill:#0ACFF7,stroke:#000,stroke-width:1px,color:#000
    style Meta_Servers fill:#25D366,stroke:#000,stroke-width:1px,color:#fff
    style Public_Zone fill:#FFFDE7,stroke:#FFB300,stroke-width:2px
    style Private_Zone fill:#E8EAF6,stroke:#3F51B5,stroke-width:2px
    style Storage_Database fill:#F5F5F5,stroke:#000,stroke-width:2px
    style Env_File fill:#FFEB3B,stroke:#000,stroke-width:1px
    style MySQL_DB fill:#fff,stroke:#000,stroke-width:2px
Especificaciones Finales de Renderizado en Antigravity 2.0
Validación de Sintaxis: Todos los diagramas utilizan las declaraciones estándar de Mermaid (graph TB/TL, sequenceDiagram, stateDiagram-v2, erDiagram).

Mantenimiento: Al no incluir código duro de programación sino mapas de arquitectura relacional, este archivo permanecerá inmutable durante los sprints de codificación de lógica de negocio, sirviendo como mapa de referencia permanente para validar que la IA no modifique la topología aprobada.