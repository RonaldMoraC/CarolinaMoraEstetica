14_Roadmap.md
Este documento define la planificaciÃ³n estratÃ©gica, el orden de ejecuciÃ³n secuencial y la mitigaciÃ³n de riesgos tÃ©cnicos para la implementaciÃ³n del ecosistema digital de "EstÃ©tica Carolina Mora". La estrategia se segmenta en tres fases evolutivas diseÃ±adas para garantizar la estabilidad del nÃºcleo transaccional antes de expandir los canales de interacciÃ³n con el cliente.

Fase 1: Backend API + PWA (FundaciÃ³n del Ecosistema)
Orden Secuencial de ImplementaciÃ³n:

Hito 1.1: InicializaciÃ³n del contenedor manual de inyecciÃ³n de dependencias (bootstrap.php), el motor del enrutador (Router.php) y la fÃ¡brica de conexiones (ConnectionFactory.php).

Hito 1.2: ImplementaciÃ³n del Contexto de Identidad y Accesos (Tablas user, user_session, client_profile, professional_profile) e infraestructura de persistencia PDO con cifrado BCrypt.

Hito 1.3: Desarrollo de la lÃ³gica de mallas horarias ordinarias y excepciones (Tablas work_schedule, schedule_exception).

Hito 1.4: Caso de uso core de reservas (CreateAppointmentUseCase) incorporando el algoritmo de validaciÃ³n de concurrencia en milisegundos mediante el Ã­ndice indexado idx_appointment_concurrency.

Hito 1.5: MÃ³dulos de facturaciÃ³n (invoice, payment) e integraciÃ³n de la API para pagos digitales.

Hito 1.6: Frontend de la PWA (vistas del cliente y del administrador) e inyecciÃ³n del Service Worker (sw.js) para persistencia local de activos y soporte offline bÃ¡sico.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): AutenticaciÃ³n por JWT, validaciÃ³n de bloqueo estricto contra overbooking a nivel transaccional en el backend, y el Front Controller seguro.

Should (Deseable): Estrategias de cachÃ© Stale-While-Revalidate en el Service Worker de la PWA para pantallas del catÃ¡logo de servicios.

Could (PodrÃ­a): IntegraciÃ³n inmediata de pasarela de pago en lÃ­nea (se puede iniciar con transacciones en sucursal / POS).

Won't (No se incluirÃ¡ en esta fase): Automatizaciones complejas de marketing o recordatorios salientes automÃ¡ticos.

Dependencias Bloqueantes: * AprobaciÃ³n e instalaciÃ³n fÃ­sica del script DDL de 32 entidades con sus respectivas restricciones CHECK en el servidor de base de datos.

Disponibilidad de certificados SSL TLS 1.3 emitidos correctamente para habilitar el registro de Service Workers (exigencia estricta de seguridad de los navegadores modernos).

Riesgos TÃ©cnicos y MitigaciÃ³n:

Riesgo: Inconsistencias de cÃ¡lculo en zonas horarias entre el servidor local de desarrollo y el hosting compartido en producciÃ³n (Hostinger), afectando las citas concertadas.

MitigaciÃ³n: Forzar por directiva estricta en bootstrap.php y en las sesiones PDO el huso horario estÃ¡ndar configurado en el archivo .env (America/Bogota).

Tiempo Estimado: 6 Semanas.

Fase 2: AplicaciÃ³n MÃ³vil Nativa/HÃ­brida (ExpansiÃ³n de Canales)
Orden Secuencial de ImplementaciÃ³n:

Hito 2.1: AdaptaciÃ³n y exposiciÃ³n de nuevos endpoints REST optimizados para transferencia mÃ³vil de datos ligeros (Payloads JSON reducidos).

Hito 2.2: IntegraciÃ³n con los SDKs nativos de Apple Push Notification service (APNs) y Firebase Cloud Messaging (FCM).

Hito 2.3: Desarrollo del contenedor mÃ³vil multiplataforma reutilizando la lÃ³gica de consumo HTTP desarrollada para la PWA.

Hito 2.4: ImplementaciÃ³n de la persistencia local avanzada en el dispositivo mediante bases de datos integradas (SQLite) para asegurar la consulta del historial de citas previas sin conexiÃ³n de red.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): SincronizaciÃ³n transparente de la cuenta de usuario existente en la PWA (compartiendo el mismo backend e infraestructura de seguridad IAM).

Should (Deseable): Notificaciones push nativas para recordar citas en lugar de depender Ãºnicamente de canales externos.

Could (PodrÃ­a): Almacenamiento biomÃ©trico de credenciales (FaceID / Huella Dactilar) para acelerar el inicio de sesiÃ³n.

Won't (No se incluirÃ¡ en esta fase): Agendamiento en modo 100% offline (las mutaciones de reservas requieren conexiÃ³n en vivo con el motor InnoDB para prevenir colisiones).

Dependencias Bloqueantes:

Estabilidad absoluta de los endpoints de la API del Backend (Fase 1 terminada y auditada).

ConfiguraciÃ³n y alta de las cuentas de desarrollador en las tiendas oficiales (Google Play Store y Apple App Store).

Riesgos TÃ©cnicos y MitigaciÃ³n:

Riesgo: Latencia elevada en la renderizaciÃ³n de calendarios interactivos debido a la carga masiva de ranuras horarias en la red mÃ³vil.

MitigaciÃ³n: PaginaciÃ³n profunda de horarios hÃ¡biles y uso de estructuras de almacenamiento en cachÃ© mÃ³vil eficientes con expiraciÃ³n controlada por el cliente.

Tiempo Estimado: 4 Semanas.

Fase 3: Ecosistema WhatsApp Bot (AutomatizaciÃ³n y Omnicanalidad)
Orden Secuencial de ImplementaciÃ³n:

Hito 3.1: HabilitaciÃ³n del endpoint pÃºblico WhatsAppWebhookController.php bajo apretones de manos (handshakes) de seguridad validados por el token del archivo .env.

Hito 3.2: ConstrucciÃ³n del procesador lÃ³gico y parser de la estructura JSON entrante desde los servidores de Meta Cloud API.

Hito 3.3: ImplementaciÃ³n de la mÃ¡quina de estados conversacional interactuando con la tabla fÃ­sica wa_chat_session para retener el contexto del cliente (Sucursal -> Servicio -> Profesional -> Hora).

Hito 3.4: ConfiguraciÃ³n y activaciÃ³n de las tareas programadas de Linux (Cron Jobs) de alta frecuencia en Hostinger para barrer wa_notification_queue y despachar flujos asÃ­ncronos pendientes.

Hito 3.5: Despliegue de scripts de mantenimiento nocturno automÃ¡ticos para la rotaciÃ³n estructural de logs y purga de colas enviadas con Ã©xito.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): Control de estados persistente para impedir que el bot pierda el hilo de la conversaciÃ³n al recibir respuestas tardÃ­as del cliente; despacho prioritario de mensajes de confirmaciÃ³n de citas de la cola.

Should (Deseable): GestiÃ³n inteligente de excepciones conversacionales (cuando el usuario escribe texto libre invÃ¡lido no numÃ©rico en los menÃºs de selecciÃ³n).

Could (PodrÃ­a): EnvÃ­o automÃ¡tico de encuestas de satisfacciÃ³n o recolecciÃ³n de reseÃ±as en estrellas (review) directamente desde el chat de WhatsApp al finalizar un servicio.

Won't (No se incluirÃ¡ en esta fase): Procesamiento de lenguaje natural avanzado (NLP) mediante modelos masivos de IA generativa en tiempo real (se priorizan menÃºs guiados deterministas por velocidad y bajo costo de infraestructura).

Dependencias Bloqueantes:

AprobaciÃ³n de la lÃ­nea comercial telefÃ³nica por parte de Meta y verificaciÃ³n de la cuenta comercial (Meta Business Suite).

Funcionamiento de los trabajadores en background (Workers) del servidor mediante la ejecuciÃ³n programada por CLI.

Riesgos TÃ©cnicos y MitigaciÃ³n:

Riesgo: Bloqueo perimetral o denegaciÃ³n de servicio en la base de datos debido a un bucle infinito de mensajes de error generados entre el webhook y el usuario (Message Loop Vulnerability).

MitigaciÃ³n: Implementar un mecanismo de cortocircuito lÃ³gico (Circuit Breaker) a nivel de base de datos que marque temporalmente la sesiÃ³n de chat como suspendida si se detectan mÃ¡s de 10 peticiones del mismo nÃºmero telefÃ³nico en menos de 5 segundos.
4. Consolidado de Esfuerzos y Cronograma GlobalPara la ejecuciÃ³n exitosa de Antigravity 2.0, el panel de expertos determina la siguiente distribuciÃ³n temporal no concurrente para salvaguardar la integridad de los datos de la EstÃ©tica Carolina Mora:FaseEnfoque ArquitectÃ³nicoDuraciÃ³n EstimadaEntregable PrincipalFase 1Backend Central (API) + PWA Offline-First6 SemanasNÃºcleo transaccional operativo y administraciÃ³n web.Fase 2App MÃ³vil HÃ­brida (Consumo de API REST) 
4 SemanasCanal nativo mÃ³vil con notificaciones push.
Fase 3Ecosistema de AutomatizaciÃ³n WhatsApp4 SemanasChatbot automatizado y motor de colas asÃ­ncronas por Cron.TotalDespliegue del Ecosistema Omnicanal14 SemanasEstructura robusta de 32 entidades en producciÃ³n.5. Matriz de Dependencias Cruzadas y Puntos CrÃ­ticos de Falla (SPOF)A nivel de Arquitectura de Escalabilidad y DevOps, se definen los siguientes tres puntos de control donde el proyecto podrÃ­a bloquearse si no se gestionan en paralelo:
La API y Base de Datos (Fase 1) como Bloqueante Absoluto:NingÃºn canal (ni la App de la Fase 2, ni el Bot de la Fase 3) puede alterar tablas o consultar horarios si el backend con strict_types=1 y las restricciones de concurrencia pesimista (FOR UPDATE) no han sido probados intensivamente en condiciones de estrÃ©s simulado.

Dependencias de Terceros (Meta Cloud API):
La Fase 3 estÃ¡ sujeta a los tiempos de verificaciÃ³n de la identidad comercial de la EstÃ©tica por parte de Meta. Si este paso se retrasa, el equipo de desarrollo debe simular el webhook localmente usando herramientas de tÃºnel seguro (como Ngrok o Localtunnel) apuntando a src/Infrastructure/Integration/WhatsApp/Http/ para no congelar el avance del roadmap.
Persistencia Compartida vs. Aislamiento:
Las 32 entidades comparten el mismo motor de base de datos MySQL en Hostinger.
Las consultas de reportes de facturaciÃ³n pesados de la PWA (Administrador) no deben bloquear las escrituras rÃ¡pidas de citas desde el Bot de WhatsApp. 
Por ello, el Ã­ndice idx_appointment_concurrency es obligatorio antes de conectar cualquier canal pÃºblico al entorno de producciÃ³n.
6. Criterios ArquitectÃ³nicos de AceptaciÃ³n para Paso a ProducciÃ³n (Go-Live Checklist)El sistema solo se considerarÃ¡ oficialmente terminado y listo para operar con clientes reales en Hostinger cuando cumpla estrictamente con las siguientes mÃ©tricas de infraestructura:
[ ] Cero Errores de Tipado: El 100% de los archivos PHP deben compilar sin advertencias bajo la directiva declare(strict_types=1);.
[ ] Prueba Antihackeo Exitosa: NingÃºn parÃ¡metro HTTP crudo debe tocar una sentencia SQL; toda mutaciÃ³n debe pasar obligatoriamente por un objeto DTO.php y sentencias preparadas de PDO.
[ ] ProtecciÃ³n de Concurrencia Validada: Al simular 5 peticiones simultÃ¡neas idÃ©nticas de reserva para el mismo especialista y hora exacta, el sistema debe registrar exitosamente una sola cita y rechazar las otras 4 devolviendo un cÃ³digo JSON formateado bajo la norma RFC 7807 con cÃ³digo de estado HTTP 422.
[ ] Persistencia PWA Verificada: Al desconectar la red en el dispositivo cliente, la interfaz grÃ¡fica debe seguir mostrando el catÃ¡logo precargado desde el Cache Storage sin romper la aplicaciÃ³n.
[ ] Estabilidad del Worker: El script NotificationQueueWorker.php debe ser capaz de procesar 100 mensajes encolados consecutivamente a travÃ©s de la CLI sin desbordar el lÃ­mite de memoria asignado en el servidor compartido de Hostinger.
