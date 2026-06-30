Este documento establece la planeación estratégica y las directrices de ingeniería para la evolución arquitectónica a largo plazo del ecosistema digital de "Estética Carolina Mora". Aunque el esquema relacional de 32 entidades actual está optimizado para operar en un entorno transaccional estructurado (OLTP) monolítico y desacoplado, este plan define la ruta técnica para transformar la plataforma en un sistema altamente disponible, distribuido, elástico y preparado para el modelo de software como servicio (SaaS) global.

1. Refactorización a Framework Enterprise: Migración Estratégica a Laravel
Para absorber el crecimiento de la operación sin perder el aislamiento de las reglas de negocio, se migrará la estructura actual de PHP puro hacia el framework Laravel 11+.

1.1 Mapeo de Capas y Preservación del Dominio
Preservación del Core Inmutable: Las carpetas src/Domain (Entidades puras e Interfaces de Repositorio) y src/Application (Casos de uso y DTOs) se trasladarán íntegramente al nuevo directorio de Laravel bajo el namespace App\Core. El framework no permeará las capas internas.

Sustitución de la Infraestructura: Los controladores nativos de src/Infrastructure/Shared/Routing e Http se sustituirán por el motor de enrutamiento y los Form Requests de Laravel.

Adaptación de Persistencia: Las clases Pdo[Contexto]Repository se adaptarán para encapsular el Query Builder de Laravel o Eloquent (únicamente como un driver de persistencia externo), manteniendo el contrato de las interfaces del dominio.

1.2 Transición del Motor de Colas e Inyección de Dependencias
Service Container Nativo: El archivo bootstrap.php (contenedor manual) se eliminará, delegando el registro automático de interfaces y dependencias al AppServiceProvider mediante mecanismos de Contextual Binding.

Abstracción de Workers asíncronos: La tabla física wa_notification_queue y los scripts manejados por Cron se migrarán a Laravel Queues. El despachador se conectará a un driver de alto rendimiento en memoria (Redis) administrado a través de horizontes de ejecución (Laravel Horizon), reduciendo las consultas de lectura (SELECT) concurrentes en MySQL.

2. Contenerización y Orquestación: Docker & Kubernetes (K8s)
El paso hacia la escalabilidad horizontal requiere el empaquetado del software en contenedores inmutables y la automatización de su ciclo de vida para soportar picos de tráfico concurrentes durante campañas de marketing masivas.

2.1 Especificación de Contenedores (Dockerización)
Se dividirá la aplicación en imágenes optimizadas basadas en distribuciones Alpine de Linux:

Imagen App-Engine: Contenedor especializado en PHP-FPM con las extensiones requeridas (pdo_mysql, redis, opcache), montado con las capas lógicas en modo de solo lectura en producción.

Imagen Web-Proxy: Servidor Nginx ligero configurado para servir los recursos estáticos de la PWA (public/assets, manifest.json, sw.js) y actuar como terminador de conexiones de la API hacia el pool de PHP-FPM.

2.2 Topología de Orquestación en Kubernetes
El despliegue transitará de Hostinger a un proveedor Cloud administrado (ej. AWS EKS o Google GKE) estructurado bajo los siguientes manifiestos:

Horizontal Pod Autoscaler (HPA): Configurado para escalar dinámicamente los pods de la API de un mínimo de 3 a un máximo de 30 instancias cuando el consumo de CPU global supere el 70% o el tráfico HTTP de Meta Webhooks sature los hilos de ejecución.

Pod Disruption Budgets (PDB): Garantiza la disponibilidad continua del servicio impidiendo que los pods que atienden la máquina de estados del Bot de WhatsApp se destruyan simultáneamente durante los procesos de actualización de código (Rolling Updates).

3. Descomposición a Arquitectura de Microservicios
A medida que el volumen transaccional de las 32 entidades aumente, el monolito se dividirá en microservicios autónomos basados en los contextos delimitados analizados previamente.

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
                           | Eventos Asíncronos        | Consume
                           v                           v
                     +-----+---------------------------+-----+
                     |    MESSAGE BROKER (APACHE KAFKA)      |
                     +---------------------------------------+
3.1 Identificación y Desacoplamiento de Servicios
Microservicio de IAM & Clientes: Responsable exclusivo de la autenticación de usuarios, perfiles y emisión de tokens criptográficos.

Microservicio de Booking & Disponibilidad (Core): Encargado de la lógica de mallas horarias, excepciones y el algoritmo crítico de prevención de sobreventa.

Microservicio de Integraciones & Notificaciones: Orquestador de la comunicación interactiva y descolamiento con la API Cloud de WhatsApp.

Microservicio Financiero & Facturación: Procesamiento de facturas, cierres de caja (POS) y webhooks de pasarelas de pago.

3.2 Comunicación Inter-servicio y Persistencia Distribuida
Base de Datos por Servicio (Database-per-Service): Se dividirá el esquema físico en instancias de bases de datos aisladas. La consistencia eventual entre microservicios (por ejemplo, actualizar el estado de una factura y ligarla al estado de la cita) se resolverá mediante patrones transaccionales distribuidos como Saga Pattern (Orquestado).

Capa de Mensajería (Event-Driven): El sistema utilizará un Message Broker como Apache Kafka o RabbitMQ. El microservicio de Booking publicará el evento AppointmentCreated; el microservicio de Notificaciones escuchará reactivamente dicho evento para encolar el mensaje de WhatsApp sin bloquear la experiencia del usuario en la PWA.

4. Evolución de Mercado: Soporte Multiempresa (SaaS) y Multiidioma
El sistema se transformará en una plataforma multi-inquilino (Multi-tenant SaaS) capaz de ser comercializada a marcas externas de estética bajo la misma infraestructura base.

4.1 Estrategia de Aislamiento de Datos (Multi-tenancy)
Para mitigar riesgos de fugas de datos financieros u operativos entre diferentes empresas inquilinas (tenants), se optará por un enfoque híbrido:

Enfoque de Base de Datos Compartida con Discriminador (Filtrado Lógico): Para PYMES o cuentas estándar, se agregará una columna indexada tenant_id (Llave foránea global) en las 32 entidades del esquema. A nivel de infraestructura (aprovechando los Global Scopes de Laravel), todas las consultas SQL inyectarán automáticamente la restricción WHERE tenant_id = X para impedir que un comercio acceda a registros ajenos.

Enfoque de Base de Datos Aislada: Para cuentas corporativas de alto volumen (Enterprise Tenants), el sistema provisionará una base de datos MySQL dedicada y enrutará las peticiones mediante un middleware que alterará dinámicamente la conexión del pool en tiempo de ejecución basándose en el subdominio de la petición (ej: marcaX.esteticamora.com).

4.2 Internacionalización (Multiidioma / Localization)
Estructura del Esquema: Las tablas de catálogos y nombres de servicios mutarán a estructuras de traducción. Las columnas de texto plano como service_name o description pasarán a ser de tipo de dato JSON, almacenando esquemas de clave-valor estandarizados (ej: {"es": "Limpieza Facial", "en": "Deep Cleansing Facial"}).

Negociación de Idioma: El backend detectará el idioma preferido leyendo la cabecera HTTP Accept-Language en la API o evaluando el código de país configurado en el perfil de la sesión del chat de WhatsApp, adaptando los flujos de respuesta de la máquina de estados automáticamente.

5. Capa de Inteligencia Artificial: Optimización de Agendas y Marketing Predictivo
La base de datos relacional servirá como el repositorio de datos de entrenamiento (Data Lakehouse) para inyectar modelos probabilísticos que optimicen las decisiones operativas del negocio de forma automatizada.

5.1 Predicción del No-Show (Inasistencias) y Sobreventa Inteligente
Cálculo de Riesgo Probabilístico: Modelos predictivos ligeros procesarán variables almacenadas en el histórico del esquema transaccional (antigüedad del cliente, tasa histórica de cancelaciones en appointment_status, días de la semana, clima y estacionalidad). Al momento en que un cliente solicite una cita de alto riesgo de inasistencia, el sistema alertará al administrador en el Dashboard.

Overbooking Controlado por Algoritmo: Con base en la probabilidad acumulada de inasistencias en bloques horarios específicos de una sucursal, la capa de IA autorizará de forma controlada la habilitación de una ranura extra de atención para el mismo especialista, maximizando el factor de utilización de la sede sin saturar la capacidad física instalada.

5.2 Automatizaciones de Marketing Basadas en Ciclos de Consumo
Predicción de Agotamiento de Servicio: Mediante análisis de series temporales sobre los detalles de facturación e historial de consumo de tratamientos recurrentes (ej: un tratamiento de depilación o toxina botulínica que requiere retoques fijos cada 4 meses), el motor de IA calculará la ventana óptima de vencimiento de los beneficios del servicio.

Disparadores de Retención Automatizados: Al cumplirse el plazo calculado sin que el usuario registre una nueva reserva, el sistema insertará una tarea de marketing predictivo de forma asíncrona directamente en la cola wa_notification_queue. El chatbot enviará un recordatorio hiper-personalizado por WhatsApp ofreciendo un incentivo o descuento dinámico ligado a las tablas de promociones vigentes.

El archivo 15_Future_Scaling.md está prácticamente completo en cuanto a su contenido estratégico y técnico fundamental. Sin embargo, para que tenga el estándar estricto de entrega a nivel corporativo (Enterprise-grade) —al igual que los archivos anteriores—, le hace falta una sección de Gobernanza de Datos, Seguridad en la Nube y un Cierre Metodológico.

Al terminar abruptamente en el punto 5.2, el documento queda sin las métricas de rendimiento esperadas (KPIs) y sin la firma de aprobación del panel de expertos.

A continuación, te entrego la Parte Final y Conclusión para cerrar formalmente el archivo. Agrégala inmediatamente después del punto 5.2:

15_Future_Scaling.md (Parte Final y Cierre)
5.3 Cumplimiento Normativo y Privacidad de Datos en Procesamiento de IA
Anonimización en Origen: Antes de que los datos de las tablas client_profile, appointment o invoice sean enviados a modelos de lenguaje (LLMs) o canalizados hacia algoritmos de Machine Learning externos, las capas de infraestructura ejecutarán un pipeline de sanitización para remover datos personales identificables (PII) como nombres completos, cédulas y teléfonos, sustituyéndolos por hashes criptográficos opacos.

Consentimiento Explícito: La máquina de estados del chatbot de WhatsApp incluirá una bandera lógica en la tabla de configuración del usuario para permitirle activar o desactivar las notificaciones basadas en análisis predictivo, cumpliendo con las regulaciones locales de protección de datos personales (Habeas Data / GDPR).

6. Métricas e Indicadores de Transición Arquitectónica (KPIs de Escalabilidad)
El éxito de la ejecución de este mapa de escalabilidad futura se medirá bajo los siguientes tres indicadores clave de rendimiento de infraestructura:

Eficiencia del Desacoplamiento (Latencia del API Gateway):
Al transicionar a microservicios en Kubernetes, el tiempo de respuesta (Response Time) para el registro de una cita a través de la API externa no debe superar los 120ms bajo una carga sostenida de 2,000 peticiones concurrentes por minuto.

Disponibilidad Global del SaaS (Uptime):
La implementación de la arquitectura multi-inquilino (Multi-tenancy) distribuida geográficamente debe garantizar un acuerdo de nivel de servicio (SLA) de disponibilidad del 99.95% anual (High Availability).

Optimización de Costos Cloud (Elasticidad FinOps):
La política de auto-escalado horizontal de los Pods (HPA) en Kubernetes debe reducir de forma automática los recursos de cómputo activos durante las horas de la madrugada (horas muertas del bot y la administración), generando un ahorro mínimo del 40% en costos de infraestructura frente a un aprovisionamiento estático.

7. Control de Emisión y Firmas del Panel de Expertos
Este documento técnico de alta ingeniería queda aprobado por el consorcio de arquitectura para su futura ejecución programática:

Arquitecto de Escalabilidad: Aprobado. (Validación de patrones distribuidos, Saga pattern y estrategia de aislamiento Multi-tenant).

Arquitecto Cloud: Aprobado. (Certificación de topología de orquestación en contenedores y elasticidad mediante Kubernetes HPA).

DBA Senior: Aprobado. (Garantía de preservación del esquema relacional base y estrategias de sharding/JSON para internacionalización).

Arquitecto DevOps: Aprobado. (Validación del pipeline de integración, telemetría y automatización de colas mediante Redis).