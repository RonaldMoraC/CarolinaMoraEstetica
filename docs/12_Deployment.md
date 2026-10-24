1. Estructura de Despliegue FÃ­sico y Arquitectura de Carpetas
Para garantizar la paridad absoluta entre el entorno de desarrollo y producciÃ³n (evitando errores de rutas e interpretaciones errÃ³neas por parte de la IA de generaciÃ³n de cÃ³digo), el despliegue en el servidor respeta de forma idÃ©ntica la arquitectura de software de capas definida en el proyecto.

1.1 Aislamiento de Seguridad en Hostinger Business
En el entorno de hosting compartido, la carpeta public/ de nuestro proyecto se fusiona/asocia conceptualmente con el directorio raÃ­z de cara al pÃºblico del servidor web, mientras que las capas lÃ³gicas y de datos (src/, bootstrap.php) quedan alojadas de forma segura un nivel arriba, totalmente fuera del alcance de peticiones HTTP directas.

1.2 Arquitectura de Carpetas Unificada en el Servidor
Plaintext
/home/u123456789/                    <-- RaÃ­z del usuario en el Servidor (Zona Privada)
â”œâ”€â”€ raiz-del-proyecto/                <-- Tu contenedor principal idÃ©ntico a Desarrollo
â”‚   â”œâ”€â”€ src/
â”‚   â”‚   â”œâ”€â”€ Domain/                   # Capa 1: LÃ³gica Pura del Negocio
â”‚   â”‚   â”‚   â”œâ”€â”€ Shared/
â”‚   â”‚   â”‚   â”œâ”€â”€ IAM/
â”‚   â”‚   â”‚   â”œâ”€â”€ Catalog/
â”‚   â”‚   â”‚   â”œâ”€â”€ Staffing/
â”‚   â”‚   â”‚   â””â”€â”€ Booking/
â”‚   â”‚   â”œâ”€â”€ Application/              # Capa 2: Casos de Uso (Orquestadores)
â”‚   â”‚   â”‚   â”œâ”€â”€ IAM/
â”‚   â”‚   â”‚   â”œâ”€â”€ Catalog/
â”‚   â”‚   â”‚   â”œâ”€â”€ Staffing/
â”‚   â”‚   â”‚   â””â”€â”€ Booking/
â”‚   â”‚   â””â”€â”€ Infrastructure/           # Capa 3: Adaptadores de TecnologÃ­a e Http
â”‚   â”‚       â”œâ”€â”€ Shared/ (Database, Security, Errors, Helpers)
â”‚   â”‚       â”œâ”€â”€ IAM/
â”‚   â”‚       â”œâ”€â”€ Catalog/
â”‚   â”‚       â”œâ”€â”€ Staffing/
â”‚   â”‚       â”œâ”€â”€ Booking/
â”‚   â”‚       â””â”€â”€ Integration/ (WhatsApp/)
â”‚   â”‚
â”‚   â”œâ”€â”€ bootstrap.php                 # Inicializador central de dependencias (Container)
â”‚   â”œâ”€â”€ vendor/                       # LibrerÃ­as de terceros (Generadas por Composer)
â”‚   â””â”€â”€ logs/                         # Archivos de registro del sistema (.log)
â”‚
â””â”€â”€ public_html/                      <-- Equivale a tu carpeta "public/" en Desarrollo
    â”œâ”€â”€ index.php                     # Punto de entrada Ãºnico (Front Controller)
    â”œâ”€â”€ .htaccess                     # Reglas de enrutamiento y Directivas Apache
    â”œâ”€â”€ assets/                       # Recursos estÃ¡ticos (ImÃ¡genes, CSS, JS de la PWA)
    â”œâ”€â”€ manifests/                    # ConfiguraciÃ³n PWA (manifest.json, workers)
    â””â”€â”€ storage/                      # Almacenamiento dinÃ¡mico pÃºblico (Vouchers, PDFs)
1.3 Mapeo del Front Controller (index.php) en ProducciÃ³n
Para que esta estructura exacta funcione en Hostinger manteniendo las capas protegidas, la primera lÃ­nea del archivo public_html/index.php invoca al inicializador subiendo un nivel en la jerarquÃ­a de discos del servidor de la siguiente manera:

PHP
<?php
// public_html/index.php - Punto de entrada Ãºnico
require_once __DIR__ . '/../raiz-del-proyecto/bootstrap.php';

2.1 ConfiguraciÃ³n del Servidor Web Apache (httpd-vhosts.conf)
Se debe configurar un Virtual Host apuntando exclusivamente a la carpeta pÃºblica, restringiendo el acceso al nÃºcleo:

Apache
<VirtualHost *:80>
    ServerAdmin admin@esteticamora.local
    DocumentRoot "C:/xampp/htdocs/estetica-carolina-mora/public_html"
    ServerName esteticamora.local
    ServerAlias www.esteticamora.local
    
    <Directory "C:/xampp/htdocs/estetica-carolina-mora/public_html">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "logs/esteticamora-error.log"
    CustomLog "logs/esteticamora-access.log" combined
</VirtualHost>
2.2 Ajustes CrÃ­ticos en php.ini (XAMPP)
Para garantizar la compatibilidad con el esquema de datos y el manejo de flujos asÃ­ncronos (Bot de WhatsApp y Colas):

Ini, TOML
max_execution_time = 120
memory_limit = 256M
post_max_size = 20M
upload_max_filesize = 10M
date.timezone = "America/Bogota" ;; Ajustar a la zona horaria del negocio
extension=pdo_mysql
extension=openssl
extension=json
extension=curl
2.3 Base de Datos Local
Motor: MySQL 8.x o MariaDB equivalente provisto por XAMPP.

Cotejo: utf8mb4_unicode_ci de manera obligatoria para soportar caracteres especiales y emojis provenientes de los chats de WhatsApp.

3. Entorno de ProducciÃ³n: ConfiguraciÃ³n para Hostinger Business
El despliegue en Hostinger Business requiere exprimir las capacidades de un entorno compartido premium mediante optimizaciones a nivel de Apache y aislamiento de procesos.

3.1 Directivas de Seguridad y Enrutamiento (.htaccess en public_html)
El archivo .htaccess actÃºa como firewall perimetral de la aplicaciÃ³n y gestor de enrutamiento atÃ³mico:

Apache
# 1. Desactivar listado de directorios
Options -Indexes

# 2. Forzar codificaciÃ³n UTF-8
AddDefaultCharset UTF-8

# 3. Motor de Reescritura para Front Controller (API / PWA)
RewriteEngine On
RewriteBase /

# RedirecciÃ³n estricta a HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Enrutar todas las peticiones que no sean archivos fÃ­sicos a index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?_url=$1 [QSA,L]

# 4. Cabeceras de Seguridad Perimetral (Harding HTTP)
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; connect-src 'self' https://api.whatsapp.com;"
3.2 VersiÃ³n de PHP y MÃ³dulos en Panel de Hostinger (hPanel)
VersiÃ³n Requerida: PHP 8.2 o superior.

Extensiones Activas: pdo_mysql, opcache (crÃ­tico para rendimiento), mbstring, curl, gd (procesamiento de imÃ¡genes de perfil), zip.

4. Variables de Entorno Requeridas (.env)
El archivo .env se ubica estrictamente en el directorio /home/u123456789/app_core/, quedando totalmente inaccesible desde la web.

Ini, TOML
# ==============================================================================
# CONFIGURACIÃ“N GENERAL DE LA APLICACIÃ“N
# ==============================================================================
APP_ENV=production
APP_DEBUG=false
APP_URL=https://esteticacarolinamora.com
APP_TIMEZONE=America/Bogota

# ==============================================================================
# BASE DE DATOS (MIME TRANSACCIONAL)
# ==============================================================================
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_estetica_db
DB_USERNAME=u123456789_db_user
DB_PASSWORD=M4st3r_DB_P4ssw0rd_#2026
DB_CHARSET=utf8mb4

# ==============================================================================
# INTEGRACIÃ“N BOT DE WHATSAPP (API CLOUD / PROVIDER)
# ==============================================================================
WA_API_URL=https://graph.facebook.com/v18.0
WA_PHONE_NUMBER_ID=109876543210
WA_PERMANENT_TOKEN=EAAW...[TOKEN_ENCRIPTADO]...XYZ
WA_WEBHOOK_VERIFY_TOKEN=MoraEsteticaVerifySecureToken2026

# ==============================================================================
# SEGURIDAD Y TOKENIZACIÃ“N
# ==============================================================================
JWT_SECRET=JWT_Secr3t_Stet1ca_M0ra_UltimateKey_#2026
ENCRYPTION_KEY=AES-256-CBC-Key-For-SensitiveData-Mora
5. PolÃ­ticas de Seguridad Perimetral, SSL y Backups
5.1 Seguridad Perimetral
Hostinger Business incluye una capa preimetral basada en Cloudflare integrada. Se configuran las siguientes reglas adicionales a travÃ©s de la consola de administraciÃ³n:

Bloqueo GeogrÃ¡fico (Geo-blocking): Restringir trÃ¡fico DDoS bloqueando peticiones de continentes fuera del radio de operaciÃ³n comercial del negocio (permitir Ãºnicamente trÃ¡fico regional del paÃ­s de operaciÃ³n).

Rate Limiting: MÃ¡ximo 60 peticiones por minuto por direcciÃ³n IP para los Endpoints bajo /api/v1/auth/* y /api/v1/wa-webhook.

5.2 ConfiguraciÃ³n SSL
Certificado: SSL Let's Encrypt de por vida (Auto-renovable).

ConfiguraciÃ³n TLS: Forzar TLS 1.3 de manera exclusiva, deshabilitando TLS 1.0 y 1.1 para mitigar ataques de degradaciÃ³n de protocolo (Downgrade Attacks).

5.3 Estrategia de Backups (Base de Datos y Archivos)
Backups Automatizados (Hostinger): Copias de seguridad diarias de archivos y bases de datos con retenciÃ³n de 30 dÃ­as.

Estrategia DBA de Contingencia (Off-site): ExportaciÃ³n lÃ³gica automatizada de la base de datos (Estructura y Datos) almacenada en un contenedor externo cifrado.

6. Manejo de Logs del Sistema
Para evitar la degradaciÃ³n del almacenamiento y asegurar la trazabilidad forense ante fallos, los logs se gestionan bajo la norma del componente PSR-3 Loggers.

UbicaciÃ³n: /home/u123456789/app_core/logs/

Niveles de Log:

production.log: Captura Ãºnicamente niveles ERROR, CRITICAL y EMERGENCY.

whatsapp_gateway.log: Registra fallos de comunicaciÃ³n con la API de Meta y descolas de mensajes de la tabla wa_notification_queue.

PolÃ­tica de RotaciÃ³n: Al no contar con utilidades Linux nativas como logrotate en entornos compartidos, el propio nÃºcleo de la aplicaciÃ³n ejecuta un script de saneamiento al final de cada semana: los logs que superen los 10MB son comprimidos en formato .gz y los que superen los 90 dÃ­as de antigÃ¼edad son eliminados definitivamente de forma automÃ¡tica.

7. Cron Jobs Exactos Requeridos por el Sistema
Dado que el entorno de Hostinger Business restringe el uso de daemons persistentes ejecutÃ¡ndose continuamente en background, los procesos asÃ­ncronos crÃ­ticos del Bot de WhatsApp, las colas de notificaciones y la auditorÃ­a del esquema se resuelven mediante el Administrador de Tareas Cron del hPanel.

AquÃ­ se definen las tareas programadas con su sintaxis exacta de ejecuciÃ³n de comandos en servidores Linux:

7.1 Despacho de la Cola de Mensajes de WhatsApp (Alta Frecuencia)
Frecuencia: Cada minuto (* * * * *)

Objetivo: Lee la tabla wa_notification_queue, procesa los envÃ­os pendientes mediante el worker y consume los mensajes prioritarios de confirmaciÃ³n de citas.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Workers/NotificationQueueWorker.php > /dev/null 2>&1
7.2 Alertas de Recordatorio de Citas PrÃ³ximas (Media Frecuencia)
Frecuencia: Cada hora (0 * * * *)

Objetivo: Escanea la tabla appointment buscando citas agendadas para las prÃ³ximas 24 horas e inserta las tareas de notificaciÃ³n en la cola de salida de WhatsApp.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Tasks/AppointmentReminderTask.php > /dev/null 2>&1
7.3 VerificaciÃ³n de ExpiraciÃ³n de Promociones e InactivaciÃ³n de Sesiones
Frecuencia: Diariamente a la medianoche (0 0 * * *)

Objetivo: Ejecuta lÃ³gicas de actualizaciÃ³n masiva sobre promotion (marcando como inactivas las que superaron end_date) y limpia tokens expirados de user_session.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Tasks/DailySystemMaintenance.php > /dev/null 2>&1
7.4 Backup Automatizado de Seguridad de la Base de Datos (DBA Off-site)
Frecuencia: Diariamente a las 02:00 AM (0 2 * * *)

Objetivo: Realiza un volcado lÃ³gico comprimido de las 32 entidades del esquema y lo almacena con marca de tiempo en la secciÃ³n privada del almacenamiento aislado.

Comando Executable:

Bash
/usr/bin/mysqldump -u u123456789_db_user -p'M4st3r_DB_P4ssw0rd_#2026' u123456789_estetica_db | gzip > /home/u123456789/app_core/backups/db_backup_$(date +\%F).sql.gz
