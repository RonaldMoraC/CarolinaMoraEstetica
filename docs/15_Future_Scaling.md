Este documento establece la planeaciÃ³n estratÃ©gica y las directrices de ingenierÃ­a para la evoluciÃ³n arquitectÃ³nica a largo plazo del ecosistema digital de "EstÃ©tica Carolina Mora". Aunque el esquema relacional de 32 entidades actual estÃ¡ optimizado para operar en un entorno transaccional estructurado (OLTP) monolÃ­tico y desacoplado, este plan define la ruta tÃ©cnica para transformar la plataforma en un sistema altamente disponible, distribuido, elÃ¡stico y preparado para el modelo de software como servicio (SaaS) global.

1. RefactorizaciÃ³n a Framework Enterprise: MigraciÃ³n EstratÃ©gica a Laravel
Para absorber el crecimiento de la operaciÃ³n sin perder el aislamiento de las reglas de negocio, se migrarÃ¡ la estructura actual de PHP puro hacia el framework Laravel 11+.

1.1 Mapeo de Capas y PreservaciÃ³n del Dominio
PreservaciÃ³n del Core Inmutable: Las carpetas src/Domain (Entidades puras e Interfaces de Repositorio) y src/Application (Casos de uso y DTOs) se trasladarÃ¡n Ã­ntegramente al nuevo directorio de Laravel bajo el namespace App\Core. El framework no permearÃ¡ las capas internas.

SustituciÃ³n de la Infraestructura: Los controladores nativos de src/Infrastructure/Shared/Routing e Http se sustituirÃ¡n por el motor de enrutamiento y los Form Requests de Laravel.

AdaptaciÃ³n de Persistencia: Las clases Pdo[Contexto]Repository se adaptarÃ¡n para encapsular el Query Builder de Laravel o Eloquent (Ãºnicamente como un driver de persistencia externo), manteniendo el contrato de las interfaces del dominio.

1.2 TransiciÃ³n del Motor de Colas e InyecciÃ³n de Dependencias
Service Container Nativo: El archivo bootstrap.php (contenedor manual) se eliminarÃ¡, delegando el registro automÃ¡tico de interfaces y dependencias al AppServiceProvider mediante mecanismos de Contextual Binding.

AbstracciÃ³n de Workers asÃ­ncronos: La tabla fÃ­sica wa_notification_queue y los scripts manejados por Cron se migrarÃ¡n a Laravel Queues. El despachador se conectarÃ¡ a un driver de alto rendimiento en memoria (Redis) administrado a travÃ©s de horizontes de ejecuciÃ³n (Laravel Horizon), reduciendo las consultas de lectura (SELECT) concurrentes en MySQL.

2. ContenerizaciÃ³n y OrquestaciÃ³n: Docker & Kubernetes (K8s)
El paso hacia la escalabilidad horizontal requiere el empaquetado del software en contenedores inmutables y la automatizaciÃ³n de su ciclo de vida para soportar picos de trÃ¡fico concurrentes durante campaÃ±as de marketing masivas.

2.1 EspecificaciÃ³n de Contenedores (DockerizaciÃ³n)
Se dividirÃ¡ la aplicaciÃ³n en imÃ¡genes optimizadas basadas en distribuciones Alpine de Linux:

Imagen App-Engine: Contenedor especializado en PHP-FPM con las extensiones requeridas (pdo_mysql, redis, opcache), montado con las capas lÃ³gicas en modo de solo lectura en producciÃ³n.

Imagen Web-Proxy: Servidor Nginx ligero configurado para servir los recursos estÃ¡ticos de la PWA (public/assets, manifest.json, sw.js) y actuar como terminador de conexiones de la API hacia el pool de PHP-FPM.

2.2 TopologÃ­a de OrquestaciÃ³n en Kubernetes
El despliegue transitarÃ¡ de Hostinger a un proveedor Cloud administrado (ej. AWS EKS o Google GKE) estructurado bajo los siguientes manifiestos:

Horizontal Pod Autoscaler (HPA): Configurado para escalar dinÃ¡micamente los pods de la API de un mÃ­nimo de 3 a un mÃ¡ximo de 30 instancias cuando el consumo de CPU global supere el 70% o el trÃ¡fico HTTP de Meta Webhooks sature los hilos de ejecuciÃ³n.

Pod Disruption Budgets (PDB): Garantiza la disponibilidad continua del servicio impidiendo que los pods que atienden la mÃ¡quina de estados del Bot de WhatsApp se destruyan simultÃ¡neamente durante los procesos de actualizaciÃ³n de cÃ³digo (Rolling Updates).

3. DescomposiciÃ³n a Arquitectura de Microservicios
A medida que el volumen transaccional de las 32 entidades aumente, el monolito se dividirÃ¡ en microservicios autÃ³nomos basados en los contextos delimitados analizados previamente.

+-----------------------------------------------------------------------------------+
|                                 API GATEWAY / K8S                                 |
+--------------------------+-----------------------+--------------------------------+
                           |                       |
                           v                       v
               +-----------------------+   +-----------------------+
               |     MICROSERVICIO     |   |     MICROSERVICIO     |
               |      DE BOOKING       |   |       DE NOTIF.       |
               +-----------+-----------+   +-----------+-----------+
                           |                           |
                           | Eventos AsÃ­ncronos        | Consume
                           v                           v
                     +-----+---------------------------+-----+
                     |    MESSAGE BROKER (APACHE KAFKA)      |
                     +---------------------------------------+
3.1 IdentificaciÃ³n y Desacoplamiento de Servicios
Microservicio de IAM & Clientes: Responsable exclusivo de la autenticaciÃ³n de usuarios, perfiles y emisiÃ³n de tokens criptogrÃ¡ficos.

Microservicio de Booking & Disponibilidad (Core): Encargado de la lÃ³gica de mallas horarias, excepciones y el algoritmo crÃ­tico de prevenciÃ³n de sobreventa.

Microservicio de Integraciones & Notificaciones: Orquestador de la comunicaciÃ³n interactiva y descolamiento con la API Cloud de WhatsApp.

Microservicio Financiero & FacturaciÃ³n: Procesamiento de facturas, cierres de caja (POS) y webhooks de pasarelas de pago.

3.2 ComunicaciÃ³n Inter-servicio y Persistencia Distribuida
Base de Datos por Servicio (Database-per-Service): Se dividirÃ¡ el esquema fÃ­sico en instancias de bases de datos aisladas. La consistencia eventual entre microservicios (por ejemplo, actualizar el estado de una factura y ligarla al estado de la cita) se resolverÃ¡ mediante patrones transaccionales distribuidos como Saga Pattern (Orquestado).

Capa de MensajerÃ­a (Event-Driven): El sistema utilizarÃ¡ un Message Broker como Apache Kafka o RabbitMQ. El microservicio de Booking publicarÃ¡ el evento AppointmentCreated; el microservicio de Notificaciones escucharÃ¡ reactivamente dicho evento para encolar el mensaje de WhatsApp sin bloquear la experiencia del usuario en la PWA.

4. EvoluciÃ³n de Mercado: Soporte Multiempresa (SaaS) y Multiidioma
El sistema se transformarÃ¡ en una plataforma multi-inquilino (Multi-tenant SaaS) capaz de ser comercializada a marcas externas de estÃ©tica bajo la misma infraestructura base.

4.1 Estrategia de Aislamiento de Datos (Multi-tenancy)
Para mitigar riesgos de fugas de datos financieros u operativos entre diferentes empresas inquilinas (tenants), se optarÃ¡ por un enfoque hÃ­brido:

Enfoque de Base de Datos Compartida con Discriminador (Filtrado LÃ³gico): Para PYMES o cuentas estÃ¡ndar, se agregarÃ¡ una columna indexada tenant_id (Llave forÃ¡nea global) en las 32 entidades del esquema. A nivel de infraestructura (aprovechando los Global Scopes de Laravel), todas las consultas SQL inyectarÃ¡n automÃ¡ticamente la restricciÃ³n WHERE tenant_id = X para impedir que un comercio acceda a registros ajenos.

Enfoque de Base de Datos Aislada: Para cuentas corporativas de alto volumen (Enterprise Tenants), el sistema provisionarÃ¡ una base de datos MySQL dedicada y enrutarÃ¡ las peticiones mediante un middleware que alterarÃ¡ dinÃ¡micamente la conexiÃ³n del pool en tiempo de ejecuciÃ³n basÃ¡ndose en el subdominio de la peticiÃ³n (ej: marcaX.esteticamora.com).

4.2 InternacionalizaciÃ³n (Multiidioma / Localization)
Estructura del Esquema: Las tablas de catÃ¡logos y nombres de servicios mutarÃ¡n a estructuras de traducciÃ³n. Las columnas de texto plano como service_name o description pasarÃ¡n a ser de tipo de dato JSON, almacenando esquemas de clave-valor estandarizados (ej: {"es": "Limpieza Facial", "en": "Deep Cleansing Facial"}).

NegociaciÃ³n de Idioma: El backend detectarÃ¡ el idioma preferido leyendo la cabecera HTTP Accept-Language en la API o evaluando el cÃ³digo de paÃ­s configurado en el perfil de la sesiÃ³n del chat de WhatsApp, adaptando los flujos de respuesta de la mÃ¡quina de estados automÃ¡ticamente.

5. Capa de Inteligencia Artificial: OptimizaciÃ³n de Agendas y Marketing Predictivo
La base de datos relacional servirÃ¡ como el repositorio de datos de entrenamiento (Data Lakehouse) para inyectar modelos probabilÃ­sticos que optimicen las decisiones operativas del negocio de forma automatizada.

5.1 PredicciÃ³n del No-Show (Inasistencias) y Sobreventa Inteligente
CÃ¡lculo de Riesgo ProbabilÃ­stico: Modelos predictivos ligeros procesarÃ¡n variables almacenadas en el histÃ³rico del esquema transaccional (antigÃ¼edad del cliente, tasa histÃ³rica de cancelaciones en appointment_status, dÃ­as de la semana, clima y estacionalidad). Al momento en que un cliente solicite una cita de alto riesgo de inasistencia, el sistema alertarÃ¡ al administrador en el Dashboard.

Overbooking Controlado por Algoritmo: Con base en la probabilidad acumulada de inasistencias en bloques horarios especÃ­ficos de una sucursal, la capa de IA autorizarÃ¡ de forma controlada la habilitaciÃ³n de una ranura extra de atenciÃ³n para el mismo especialista, maximizando el factor de utilizaciÃ³n de la sede sin saturar la capacidad fÃ­sica instalada.

5.2 Automatizaciones de Marketing Basadas en Ciclos de Consumo
PredicciÃ³n de Agotamiento de Servicio: Mediante anÃ¡lisis de series temporales sobre los detalles de facturaciÃ³n e historial de consumo de tratamientos recurrentes (ej: un tratamiento de depilaciÃ³n o toxina botulÃ­nica que requiere retoques fijos cada 4 meses), el motor de IA calcularÃ¡ la ventana Ã³ptima de vencimiento de los beneficios del servicio.

Disparadores de RetenciÃ³n Automatizados: Al cumplirse el plazo calculado sin que el usuario registre una nueva reserva, el sistema insertarÃ¡ una tarea de marketing predictivo de forma asÃ­ncrona directamente en la cola wa_notification_queue. El chatbot enviarÃ¡ un recordatorio hiper-personalizado por WhatsApp ofreciendo un incentivo o descuento dinÃ¡mico ligado a las tablas de promociones vigentes.

El archivo 15_Future_Scaling.md estÃ¡ prÃ¡cticamente completo en cuanto a su contenido estratÃ©gico y tÃ©cnico fundamental. Sin embargo, para que tenga el estÃ¡ndar estricto de entrega a nivel corporativo (Enterprise-grade) â€”al igual que los archivos anterioresâ€”, le hace falta una secciÃ³n de Gobernanza de Datos, Seguridad en la Nube y un Cierre MetodolÃ³gico.

Al terminar abruptamente en el punto 5.2, el documento queda sin las mÃ©tricas de rendimiento esperadas (KPIs) y sin la firma de aprobaciÃ³n del panel de expertos.

A continuaciÃ³n, te entrego la Parte Final y ConclusiÃ³n para cerrar formalmente el archivo. AgrÃ©gala inmediatamente despuÃ©s del punto 5.2:

15_Future_Scaling.md (Parte Final y Cierre)
5.3 Cumplimiento Normativo y Privacidad de Datos en Procesamiento de IA
AnonimizaciÃ³n en Origen: Antes de que los datos de las tablas client_profile, appointment o invoice sean enviados a modelos de lenguaje (LLMs) o canalizados hacia algoritmos de Machine Learning externos, las capas de infraestructura ejecutarÃ¡n un pipeline de sanitizaciÃ³n para remover datos personales identificables (PII) como nombres completos, cÃ©dulas y telÃ©fonos, sustituyÃ©ndolos por hashes criptogrÃ¡ficos opacos.

Consentimiento ExplÃ­cito: La mÃ¡quina de estados del chatbot de WhatsApp incluirÃ¡ una bandera lÃ³gica en la tabla de configuraciÃ³n del usuario para permitirle activar o desactivar las notificaciones basadas en anÃ¡lisis predictivo, cumpliendo con las regulaciones locales de protecciÃ³n de datos personales (Habeas Data / GDPR).

6. MÃ©tricas e Indicadores de TransiciÃ³n ArquitectÃ³nica (KPIs de Escalabilidad)
El Ã©xito de la ejecuciÃ³n de este mapa de escalabilidad futura se medirÃ¡ bajo los siguientes tres indicadores clave de rendimiento de infraestructura:

Eficiencia del Desacoplamiento (Latencia del API Gateway):
Al transicionar a microservicios en Kubernetes, el tiempo de respuesta (Response Time) para el registro de una cita a travÃ©s de la API externa no debe superar los 120ms bajo una carga sostenida de 2,000 peticiones concurrentes por minuto.

Disponibilidad Global del SaaS (Uptime):
La implementaciÃ³n de la arquitectura multi-inquilino (Multi-tenancy) distribuida geogrÃ¡ficamente debe garantizar un acuerdo de nivel de servicio (SLA) de disponibilidad del 99.95% anual (High Availability).

OptimizaciÃ³n de Costos Cloud (Elasticidad FinOps):
La polÃ­tica de auto-escalado horizontal de los Pods (HPA) en Kubernetes debe reducir de forma automÃ¡tica los recursos de cÃ³mputo activos durante las horas de la madrugada (horas muertas del bot y la administraciÃ³n), generando un ahorro mÃ­nimo del 40% en costos de infraestructura frente a un aprovisionamiento estÃ¡tico.

7. Control de EmisiÃ³n y Firmas del Panel de Expertos
Este documento tÃ©cnico de alta ingenierÃ­a queda aprobado por el consorcio de arquitectura para su futura ejecuciÃ³n programÃ¡tica:

Arquitecto de Escalabilidad: Aprobado. (ValidaciÃ³n de patrones distribuidos, Saga pattern y estrategia de aislamiento Multi-tenant).

Arquitecto Cloud: Aprobado. (CertificaciÃ³n de topologÃ­a de orquestaciÃ³n en contenedores y elasticidad mediante Kubernetes HPA).

DBA Senior: Aprobado. (GarantÃ­a de preservaciÃ³n del esquema relacional base y estrategias de sharding/JSON para internacionalizaciÃ³n).

Arquitecto DevOps: Aprobado. (ValidaciÃ³n del pipeline de integraciÃ³n, telemetrÃ­a y automatizaciÃ³n de colas mediante Redis).
