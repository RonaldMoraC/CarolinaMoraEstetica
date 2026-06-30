14_Roadmap.md
Este documento define la planificación estratégica, el orden de ejecución secuencial y la mitigación de riesgos técnicos para la implementación del ecosistema digital de "Estética Carolina Mora". La estrategia se segmenta en tres fases evolutivas diseñadas para garantizar la estabilidad del núcleo transaccional antes de expandir los canales de interacción con el cliente.

Fase 1: Backend API + PWA (Fundación del Ecosistema)
Orden Secuencial de Implementación:

Hito 1.1: Inicialización del contenedor manual de inyección de dependencias (bootstrap.php), el motor del enrutador (Router.php) y la fábrica de conexiones (ConnectionFactory.php).

Hito 1.2: Implementación del Contexto de Identidad y Accesos (Tablas user, user_session, client_profile, professional_profile) e infraestructura de persistencia PDO con cifrado BCrypt.

Hito 1.3: Desarrollo de la lógica de mallas horarias ordinarias y excepciones (Tablas work_schedule, schedule_exception).

Hito 1.4: Caso de uso core de reservas (CreateAppointmentUseCase) incorporando el algoritmo de validación de concurrencia en milisegundos mediante el índice indexado idx_appointment_concurrency.

Hito 1.5: Módulos de facturación (invoice, payment) e integración de la API para pagos digitales.

Hito 1.6: Frontend de la PWA (vistas del cliente y del administrador) e inyección del Service Worker (sw.js) para persistencia local de activos y soporte offline básico.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): Autenticación por JWT, validación de bloqueo estricto contra overbooking a nivel transaccional en el backend, y el Front Controller seguro.

Should (Deseable): Estrategias de caché Stale-While-Revalidate en el Service Worker de la PWA para pantallas del catálogo de servicios.

Could (Podría): Integración inmediata de pasarela de pago en línea (se puede iniciar con transacciones en sucursal / POS).

Won't (No se incluirá en esta fase): Automatizaciones complejas de marketing o recordatorios salientes automáticos.

Dependencias Bloqueantes: * Aprobación e instalación física del script DDL de 32 entidades con sus respectivas restricciones CHECK en el servidor de base de datos.

Disponibilidad de certificados SSL TLS 1.3 emitidos correctamente para habilitar el registro de Service Workers (exigencia estricta de seguridad de los navegadores modernos).

Riesgos Técnicos y Mitigación:

Riesgo: Inconsistencias de cálculo en zonas horarias entre el servidor local de desarrollo y el hosting compartido en producción (Hostinger), afectando las citas concertadas.

Mitigación: Forzar por directiva estricta en bootstrap.php y en las sesiones PDO el huso horario estándar configurado en el archivo .env (America/Bogota).

Tiempo Estimado: 6 Semanas.

Fase 2: Aplicación Móvil Nativa/Híbrida (Expansión de Canales)
Orden Secuencial de Implementación:

Hito 2.1: Adaptación y exposición de nuevos endpoints REST optimizados para transferencia móvil de datos ligeros (Payloads JSON reducidos).

Hito 2.2: Integración con los SDKs nativos de Apple Push Notification service (APNs) y Firebase Cloud Messaging (FCM).

Hito 2.3: Desarrollo del contenedor móvil multiplataforma reutilizando la lógica de consumo HTTP desarrollada para la PWA.

Hito 2.4: Implementación de la persistencia local avanzada en el dispositivo mediante bases de datos integradas (SQLite) para asegurar la consulta del historial de citas previas sin conexión de red.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): Sincronización transparente de la cuenta de usuario existente en la PWA (compartiendo el mismo backend e infraestructura de seguridad IAM).

Should (Deseable): Notificaciones push nativas para recordar citas en lugar de depender únicamente de canales externos.

Could (Podría): Almacenamiento biométrico de credenciales (FaceID / Huella Dactilar) para acelerar el inicio de sesión.

Won't (No se incluirá en esta fase): Agendamiento en modo 100% offline (las mutaciones de reservas requieren conexión en vivo con el motor InnoDB para prevenir colisiones).

Dependencias Bloqueantes:

Estabilidad absoluta de los endpoints de la API del Backend (Fase 1 terminada y auditada).

Configuración y alta de las cuentas de desarrollador en las tiendas oficiales (Google Play Store y Apple App Store).

Riesgos Técnicos y Mitigación:

Riesgo: Latencia elevada en la renderización de calendarios interactivos debido a la carga masiva de ranuras horarias en la red móvil.

Mitigación: Paginación profunda de horarios hábiles y uso de estructuras de almacenamiento en caché móvil eficientes con expiración controlada por el cliente.

Tiempo Estimado: 4 Semanas.

Fase 3: Ecosistema WhatsApp Bot (Automatización y Omnicanalidad)
Orden Secuencial de Implementación:

Hito 3.1: Habilitación del endpoint público WhatsAppWebhookController.php bajo apretones de manos (handshakes) de seguridad validados por el token del archivo .env.

Hito 3.2: Construcción del procesador lógico y parser de la estructura JSON entrante desde los servidores de Meta Cloud API.

Hito 3.3: Implementación de la máquina de estados conversacional interactuando con la tabla física wa_chat_session para retener el contexto del cliente (Sucursal -> Servicio -> Profesional -> Hora).

Hito 3.4: Configuración y activación de las tareas programadas de Linux (Cron Jobs) de alta frecuencia en Hostinger para barrer wa_notification_queue y despachar flujos asíncronos pendientes.

Hito 3.5: Despliegue de scripts de mantenimiento nocturno automáticos para la rotación estructural de logs y purga de colas enviadas con éxito.

Prioridades (Matriz MoSCoW):

Must (Obligatorio): Control de estados persistente para impedir que el bot pierda el hilo de la conversación al recibir respuestas tardías del cliente; despacho prioritario de mensajes de confirmación de citas de la cola.

Should (Deseable): Gestión inteligente de excepciones conversacionales (cuando el usuario escribe texto libre inválido no numérico en los menús de selección).

Could (Podría): Envío automático de encuestas de satisfacción o recolección de reseñas en estrellas (review) directamente desde el chat de WhatsApp al finalizar un servicio.

Won't (No se incluirá en esta fase): Procesamiento de lenguaje natural avanzado (NLP) mediante modelos masivos de IA generativa en tiempo real (se priorizan menús guiados deterministas por velocidad y bajo costo de infraestructura).

Dependencias Bloqueantes:

Aprobación de la línea comercial telefónica por parte de Meta y verificación de la cuenta comercial (Meta Business Suite).

Funcionamiento de los trabajadores en background (Workers) del servidor mediante la ejecución programada por CLI.

Riesgos Técnicos y Mitigación:

Riesgo: Bloqueo perimetral o denegación de servicio en la base de datos debido a un bucle infinito de mensajes de error generados entre el webhook y el usuario (Message Loop Vulnerability).

Mitigación: Implementar un mecanismo de cortocircuito lógico (Circuit Breaker) a nivel de base de datos que marque temporalmente la sesión de chat como suspendida si se detectan más de 10 peticiones del mismo número telefónico en menos de 5 segundos.
4. Consolidado de Esfuerzos y Cronograma GlobalPara la ejecución exitosa de Antigravity 2.0, el panel de expertos determina la siguiente distribución temporal no concurrente para salvaguardar la integridad de los datos de la Estética Carolina Mora:FaseEnfoque ArquitectónicoDuración EstimadaEntregable PrincipalFase 1Backend Central (API) + PWA Offline-First6 SemanasNúcleo transaccional operativo y administración web.Fase 2App Móvil Híbrida (Consumo de API REST) 
4 SemanasCanal nativo móvil con notificaciones push.
Fase 3Ecosistema de Automatización WhatsApp4 SemanasChatbot automatizado y motor de colas asíncronas por Cron.TotalDespliegue del Ecosistema Omnicanal14 SemanasEstructura robusta de 32 entidades en producción.5. Matriz de Dependencias Cruzadas y Puntos Críticos de Falla (SPOF)A nivel de Arquitectura de Escalabilidad y DevOps, se definen los siguientes tres puntos de control donde el proyecto podría bloquearse si no se gestionan en paralelo:
La API y Base de Datos (Fase 1) como Bloqueante Absoluto:Ningún canal (ni la App de la Fase 2, ni el Bot de la Fase 3) puede alterar tablas o consultar horarios si el backend con strict_types=1 y las restricciones de concurrencia pesimista (FOR UPDATE) no han sido probados intensivamente en condiciones de estrés simulado.

Dependencias de Terceros (Meta Cloud API):
La Fase 3 está sujeta a los tiempos de verificación de la identidad comercial de la Estética por parte de Meta. Si este paso se retrasa, el equipo de desarrollo debe simular el webhook localmente usando herramientas de túnel seguro (como Ngrok o Localtunnel) apuntando a src/Infrastructure/Integration/WhatsApp/Http/ para no congelar el avance del roadmap.
Persistencia Compartida vs. Aislamiento:
Las 32 entidades comparten el mismo motor de base de datos MySQL en Hostinger.
Las consultas de reportes de facturación pesados de la PWA (Administrador) no deben bloquear las escrituras rápidas de citas desde el Bot de WhatsApp. 
Por ello, el índice idx_appointment_concurrency es obligatorio antes de conectar cualquier canal público al entorno de producción.
6. Criterios Arquitectónicos de Aceptación para Paso a Producción (Go-Live Checklist)El sistema solo se considerará oficialmente terminado y listo para operar con clientes reales en Hostinger cuando cumpla estrictamente con las siguientes métricas de infraestructura:
[ ] Cero Errores de Tipado: El 100% de los archivos PHP deben compilar sin advertencias bajo la directiva declare(strict_types=1);.
[ ] Prueba Antihackeo Exitosa: Ningún parámetro HTTP crudo debe tocar una sentencia SQL; toda mutación debe pasar obligatoriamente por un objeto DTO.php y sentencias preparadas de PDO.
[ ] Protección de Concurrencia Validada: Al simular 5 peticiones simultáneas idénticas de reserva para el mismo especialista y hora exacta, el sistema debe registrar exitosamente una sola cita y rechazar las otras 4 devolviendo un código JSON formateado bajo la norma RFC 7807 con código de estado HTTP 422.
[ ] Persistencia PWA Verificada: Al desconectar la red en el dispositivo cliente, la interfaz gráfica debe seguir mostrando el catálogo precargado desde el Cache Storage sin romper la aplicación.
[ ] Estabilidad del Worker: El script NotificationQueueWorker.php debe ser capaz de procesar 100 mensajes encolados consecutivamente a través de la CLI sin desbordar el límite de memoria asignado en el servidor compartido de Hostinger.