# Carolina Mora Estética PWA

Plataforma Web Progresiva (PWA) diseñada bajo los principios de Arquitectura Limpia (Clean Architecture) para la gestión operativa y administrativa de un centro de estética. El sistema permite la gestión integral de citas, usuarios, roles (RBAC) y servicios.

🚀 Descripción del Proyecto
Este software ha sido desarrollado para automatizar los procesos de "Carolina Mora Estética", permitiendo una separación clara entre la lógica de dominio, la capa de aplicación y la infraestructura. Es un sistema orientado a servicios (API-First) que garantiza escalabilidad, seguridad y una experiencia de usuario fluida.

🛠 Stack Tecnológico
Backend: PHP 8.2+ (Nativo, PSR-4 Autoloading)

Base de Datos: MySQL (InnoDB)

Seguridad: JWT (JSON Web Tokens), BCrypt (Hashing), RBAC (Control de acceso basado en roles)

Arquitectura: Clean Architecture (DDD principles)

Frontend: JavaScript Nativo, CSS Modular, Service Workers (PWA)

📋 Estructura de Módulos (RBAC)
El sistema gestiona accesos mediante roles definidos para garantizar el principio de menor privilegio:

SUPER_ADMIN: Acceso total a analíticas, gestión de usuarios, horarios y configuración.

CLIENT: Acceso a perfil personal, historial de citas y reserva de servicios.

📁 Estructura del Proyecto
Plaintext
/src                # Lógica de negocio (Core, Aplicación, Infraestructura)
/public             # Punto de entrada y Vistas (HTML/PHP)
/database           # Migraciones y Seeders
/docs               # Documentación técnica detallada
/vendor             # Dependencias de Composer
📦 Instalación y Configuración
Clonar el repositorio:
git clone https://github.com/tu-usuario/nombre-del-repo.git

Instalar dependencias:
composer install

Configurar entorno:
Copia el archivo .env.example a .env y ajusta tus credenciales de base de datos.

Base de datos:
Importa el archivo ubicado en /database/bd_estetica_carolinamora.sql en tu servidor MySQL local.

🔒 Seguridad
Este proyecto implementa capas de seguridad robustas:

Protección de rutas: Middleware de autenticación mediante JWT.

Privacidad: Archivos sensibles y variables de entorno protegidos mediante .gitignore.

Validación: Uso de tipos estrictos en PHP (strict_types=1).

📝 Licencia
Este proyecto es propiedad intelectual para fines académicos y profesionales. Todos los derechos reservados.

Desarrollado como parte del ciclo de formación técnica en la Tecnologia en analisis y desarrollo de software (ADSO) en el SENA.
