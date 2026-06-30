1. Estructura de Despliegue Físico y Arquitectura de Carpetas
Para garantizar la paridad absoluta entre el entorno de desarrollo y producción (evitando errores de rutas e interpretaciones erróneas por parte de la IA de generación de código), el despliegue en el servidor respeta de forma idéntica la arquitectura de software de capas definida en el proyecto.

1.1 Aislamiento de Seguridad en Hostinger Business
En el entorno de hosting compartido, la carpeta public/ de nuestro proyecto se fusiona/asocia conceptualmente con el directorio raíz de cara al público del servidor web, mientras que las capas lógicas y de datos (src/, bootstrap.php) quedan alojadas de forma segura un nivel arriba, totalmente fuera del alcance de peticiones HTTP directas.

1.2 Arquitectura de Carpetas Unificada en el Servidor
Plaintext
/home/u123456789/                    <-- Raíz del usuario en el Servidor (Zona Privada)
├── raiz-del-proyecto/                <-- Tu contenedor principal idéntico a Desarrollo
│   ├── src/
│   │   ├── Domain/                   # Capa 1: Lógica Pura del Negocio
│   │   │   ├── Shared/
│   │   │   ├── IAM/
│   │   │   ├── Catalog/
│   │   │   ├── Staffing/
│   │   │   └── Booking/
│   │   ├── Application/              # Capa 2: Casos de Uso (Orquestadores)
│   │   │   ├── IAM/
│   │   │   ├── Catalog/
│   │   │   ├── Staffing/
│   │   │   └── Booking/
│   │   └── Infrastructure/           # Capa 3: Adaptadores de Tecnología e Http
│   │       ├── Shared/ (Database, Security, Errors, Helpers)
│   │       ├── IAM/
│   │       ├── Catalog/
│   │       ├── Staffing/
│   │       ├── Booking/
│   │       └── Integration/ (WhatsApp/)
│   │
│   ├── bootstrap.php                 # Inicializador central de dependencias (Container)
│   ├── vendor/                       # Librerías de terceros (Generadas por Composer)
│   └── logs/                         # Archivos de registro del sistema (.log)
│
└── public_html/                      <-- Equivale a tu carpeta "public/" en Desarrollo
    ├── index.php                     # Punto de entrada único (Front Controller)
    ├── .htaccess                     # Reglas de enrutamiento y Directivas Apache
    ├── assets/                       # Recursos estáticos (Imágenes, CSS, JS de la PWA)
    ├── manifests/                    # Configuración PWA (manifest.json, workers)
    └── storage/                      # Almacenamiento dinámico público (Vouchers, PDFs)
1.3 Mapeo del Front Controller (index.php) en Producción
Para que esta estructura exacta funcione en Hostinger manteniendo las capas protegidas, la primera línea del archivo public_html/index.php invoca al inicializador subiendo un nivel en la jerarquía de discos del servidor de la siguiente manera:

PHP
<?php
// public_html/index.php - Punto de entrada único
require_once __DIR__ . '/../raiz-del-proyecto/bootstrap.php';

2.1 Configuración del Servidor Web Apache (httpd-vhosts.conf)
Se debe configurar un Virtual Host apuntando exclusivamente a la carpeta pública, restringiendo el acceso al núcleo:

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
2.2 Ajustes Críticos en php.ini (XAMPP)
Para garantizar la compatibilidad con el esquema de datos y el manejo de flujos asíncronos (Bot de WhatsApp y Colas):

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

3. Entorno de Producción: Configuración para Hostinger Business
El despliegue en Hostinger Business requiere exprimir las capacidades de un entorno compartido premium mediante optimizaciones a nivel de Apache y aislamiento de procesos.

3.1 Directivas de Seguridad y Enrutamiento (.htaccess en public_html)
El archivo .htaccess actúa como firewall perimetral de la aplicación y gestor de enrutamiento atómico:

Apache
# 1. Desactivar listado de directorios
Options -Indexes

# 2. Forzar codificación UTF-8
AddDefaultCharset UTF-8

# 3. Motor de Reescritura para Front Controller (API / PWA)
RewriteEngine On
RewriteBase /

# Redirección estricta a HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Enrutar todas las peticiones que no sean archivos físicos a index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?_url=$1 [QSA,L]

# 4. Cabeceras de Seguridad Perimetral (Harding HTTP)
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; connect-src 'self' https://api.whatsapp.com;"
3.2 Versión de PHP y Módulos en Panel de Hostinger (hPanel)
Versión Requerida: PHP 8.2 o superior.

Extensiones Activas: pdo_mysql, opcache (crítico para rendimiento), mbstring, curl, gd (procesamiento de imágenes de perfil), zip.

4. Variables de Entorno Requeridas (.env)
El archivo .env se ubica estrictamente en el directorio /home/u123456789/app_core/, quedando totalmente inaccesible desde la web.

Ini, TOML
# ==============================================================================
# CONFIGURACIÓN GENERAL DE LA APLICACIÓN
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
# INTEGRACIÓN BOT DE WHATSAPP (API CLOUD / PROVIDER)
# ==============================================================================
WA_API_URL=https://graph.facebook.com/v18.0
WA_PHONE_NUMBER_ID=109876543210
WA_PERMANENT_TOKEN=EAAW...[TOKEN_ENCRIPTADO]...XYZ
WA_WEBHOOK_VERIFY_TOKEN=MoraEsteticaVerifySecureToken2026

# ==============================================================================
# SEGURIDAD Y TOKENIZACIÓN
# ==============================================================================
JWT_SECRET=JWT_Secr3t_Stet1ca_M0ra_UltimateKey_#2026
ENCRYPTION_KEY=AES-256-CBC-Key-For-SensitiveData-Mora
5. Políticas de Seguridad Perimetral, SSL y Backups
5.1 Seguridad Perimetral
Hostinger Business incluye una capa preimetral basada en Cloudflare integrada. Se configuran las siguientes reglas adicionales a través de la consola de administración:

Bloqueo Geográfico (Geo-blocking): Restringir tráfico DDoS bloqueando peticiones de continentes fuera del radio de operación comercial del negocio (permitir únicamente tráfico regional del país de operación).

Rate Limiting: Máximo 60 peticiones por minuto por dirección IP para los Endpoints bajo /api/v1/auth/* y /api/v1/wa-webhook.

5.2 Configuración SSL
Certificado: SSL Let's Encrypt de por vida (Auto-renovable).

Configuración TLS: Forzar TLS 1.3 de manera exclusiva, deshabilitando TLS 1.0 y 1.1 para mitigar ataques de degradación de protocolo (Downgrade Attacks).

5.3 Estrategia de Backups (Base de Datos y Archivos)
Backups Automatizados (Hostinger): Copias de seguridad diarias de archivos y bases de datos con retención de 30 días.

Estrategia DBA de Contingencia (Off-site): Exportación lógica automatizada de la base de datos (Estructura y Datos) almacenada en un contenedor externo cifrado.

6. Manejo de Logs del Sistema
Para evitar la degradación del almacenamiento y asegurar la trazabilidad forense ante fallos, los logs se gestionan bajo la norma del componente PSR-3 Loggers.

Ubicación: /home/u123456789/app_core/logs/

Niveles de Log:

production.log: Captura únicamente niveles ERROR, CRITICAL y EMERGENCY.

whatsapp_gateway.log: Registra fallos de comunicación con la API de Meta y descolas de mensajes de la tabla wa_notification_queue.

Política de Rotación: Al no contar con utilidades Linux nativas como logrotate en entornos compartidos, el propio núcleo de la aplicación ejecuta un script de saneamiento al final de cada semana: los logs que superen los 10MB son comprimidos en formato .gz y los que superen los 90 días de antigüedad son eliminados definitivamente de forma automática.

7. Cron Jobs Exactos Requeridos por el Sistema
Dado que el entorno de Hostinger Business restringe el uso de daemons persistentes ejecutándose continuamente en background, los procesos asíncronos críticos del Bot de WhatsApp, las colas de notificaciones y la auditoría del esquema se resuelven mediante el Administrador de Tareas Cron del hPanel.

Aquí se definen las tareas programadas con su sintaxis exacta de ejecución de comandos en servidores Linux:

7.1 Despacho de la Cola de Mensajes de WhatsApp (Alta Frecuencia)
Frecuencia: Cada minuto (* * * * *)

Objetivo: Lee la tabla wa_notification_queue, procesa los envíos pendientes mediante el worker y consume los mensajes prioritarios de confirmación de citas.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Workers/NotificationQueueWorker.php > /dev/null 2>&1
7.2 Alertas de Recordatorio de Citas Próximas (Media Frecuencia)
Frecuencia: Cada hora (0 * * * *)

Objetivo: Escanea la tabla appointment buscando citas agendadas para las próximas 24 horas e inserta las tareas de notificación en la cola de salida de WhatsApp.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Tasks/AppointmentReminderTask.php > /dev/null 2>&1
7.3 Verificación de Expiración de Promociones e Inactivación de Sesiones
Frecuencia: Diariamente a la medianoche (0 0 * * *)

Objetivo: Ejecuta lógicas de actualización masiva sobre promotion (marcando como inactivas las que superaron end_date) y limpia tokens expirados de user_session.

Comando Executable:

Bash
/usr/local/bin/php /home/u123456789/app_core/src/Tasks/DailySystemMaintenance.php > /dev/null 2>&1
7.4 Backup Automatizado de Seguridad de la Base de Datos (DBA Off-site)
Frecuencia: Diariamente a las 02:00 AM (0 2 * * *)

Objetivo: Realiza un volcado lógico comprimido de las 32 entidades del esquema y lo almacena con marca de tiempo en la sección privada del almacenamiento aislado.

Comando Executable:

Bash
/usr/bin/mysqldump -u u123456789_db_user -p'M4st3r_DB_P4ssw0rd_#2026' u123456789_estetica_db | gzip > /home/u123456789/app_core/backups/db_backup_$(date +\%F).sql.gz