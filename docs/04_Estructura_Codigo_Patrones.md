Este manual define la arquitectura física del código fuente y las directrices de programación obligatorias para el desarrollo de la estética. Al ejecutarse sobre entornos estándar (XAMPP en desarrollo / Hostinger en producción), el sistema dependerá de un diseño desacoplado en PHP Puro (v8.x+) utilizando un autoloader PSR-4 para garantizar rendimiento y mantenibilidad extrema.

1. Patrones de Programación y Reglas de Escritura Obligatorias
Antigravity 2.0 deberá rechazar cualquier lrujo de código que viole las siguientes cuatro directivas de ingeniería:

1.1. Declaración de Tipado Estricto (strict_types)
Absolutamente todos los archivos con extensión .php en el proyecto deben iniciar exactamente con la siguiente línea de código, sin excepciones:

PHP
<?php
declare(strict_types=1);
Esto prohíbe las coerciones implícitas de tipos de PHP (por ejemplo, que un string "5" sea aceptado automáticamente en un parámetro que exige int), forzando fallos en tiempo de ejecución si la data no es perfectamente limpia.

1.2. Inyección de Dependencias Manual por Constructor
Queda estrictamente prohibido el uso de la palabra clave new dentro de los Casos de Uso o Controladores para instanciar dependencias o servicios.

Todas las clases deben recibir sus dependencias de forma pasiva a través del método __construct apuntando a interfaces (abstracciones), nunca a clases concretas.

Ejemplo de diseño requerido:

PHP
namespace App\Application\Booking;

use App\Domain\Booking\AppointmentRepositoryInterface;

class CreateAppointmentUseCase {
    private AppointmentRepositoryInterface $repository;

    public function __construct(AppointmentRepositoryInterface $repository) {
        $this->repository = $repository;
    }
}
1.3. Sentencias Preparadas PDO Obligatorias (Anti-SQL Injection)
Queda terminantemente prohibido concatenar variables directas en strings de consultas SQL o usar funciones obsoletas. Toda interacción de escritura o lectura con MySQL debe utilizar marcadores de posición (:parametro) y vinculación explícita mediante execute().

PHP
$stmt = $this->pdo->prepare("SELECT * FROM user WHERE email = :email AND is_active = 1");
$stmt->execute(['email' => $email]);
1.4. Manejo Global de Excepciones y Errores
Ningún controlador de la API debe envolver su lógica en bloques try-catch genéricos para ocultar fallos.

Si ocurre un error de negocio, se debe lanzar una excepción semántica personalizada que herede de DomainException.

Un componente centralizado de infraestructura (GlobalExceptionHandler) capturará de forma perimetral cualquier excepción no controlada, registrará el incidente de forma segura y devolverá la respuesta JSON formateada bajo el estándar RFC 7807 definido en el contrato de la API.

2. Árbol de Directorios Estricto: Capas Internas (Domain y Application)
A continuación se detalla la anatomía exacta del directorio src/ para las capas del Dominio (Reglas puras de negocio) y de Aplicación (Casos de uso/Orquestación). Esta estructura es agnóstica a la infraestructura; no sabe nada de bases de datos, ni de servidores, ni de HTTP.

raiz-del-proyecto/
├── logs/                         # Registros de errores y auditoría del sistema (.log)
├── vendor/                       # Dependencias externas gestionadas por Composer
├── public/                       # Único directorio accesible desde la web (Raíz Pública)
│   ├── index.php                 # Front Controller: Punto de entrada único de la API
│   ├── .htaccess                 # Directivas Apache, HTTPS estricto y reescritura de URLs
│   ├── manifest.json             # Manifiesto de la PWA para instalación en dispositivos
│   ├── sw.js                     # Service Worker de la PWA (Estrategias de caché offline)
│   ├── assets/                   # Recursos estáticos (CSS, JS compilado, fuentes, logos)
│   └── storage/                  # Archivos dinámicos públicos (Vouchers de pago, PDFs)
│
└── src/
    ├── Domain/                   # CAPA 1: LOGICA PURA DEL NEGOCIO (Sin SQL ni Frameworks)
    │   ├── Shared/
    │   │   ├── ValueObjects/     # Objetos inmutables (Money.php, TimeRange.php)
    │   │   ├── Exceptions/       # Excepciones de negocio (DomainException.php)
    │   │   └── Events/           # Despachador y Contratos (DomainEventInterface.php)
    │   ├── IAM/                  # Contexto: Control de Accesos e Identidades
    │   │   ├── Entities/         # User.php, Role.php
    │   │   └── Repositories/     # UserRepositoryInterface.php
    │   ├── Catalog/              # Contexto: Catálogo de Servicios y Promociones
    │   │   ├── Entities/         # Service.php, Promotion.php
    │   │   └── Repositories/     # ServiceRepositoryInterface.php
    │   ├── Staffing/             # Contexto: Horarios y Disponibilidad de Especialistas
    │   │   ├── Entities/         # ProfessionalProfile.php, WorkSchedule.php
    │   │   └── Repositories/     # StaffingRepositoryInterface.php
    │   ├── Booking/              # Contexto Core: Gestión y Flujo de Citas
    │   │   ├── Entities/         # Appointment.php, ClientProfile.php
    │   │   └── Repositories/     # AppointmentRepositoryInterface.php
    │   └── Billing/              # Contexto Financiero: Facturación, Caja y Pagos
    │       ├── Entities/         # Payment.php, Invoice.php
    │       └── Repositories/     # PaymentRepositoryInterface.php
    │
    ├── Application/              # CAPA 2: CASOS DE USO (Orquestadores de Acciones)
    │   ├── Shared/
    │   │   └── Validators/       # Sanitizadores y validadores de DTOs (InputSanitizer.php)
    │   ├── IAM/                  # Casos de Uso de Autenticación y Registro
    │   │   ├── RegisterClient/   # RegisterNewClientUseCase.php, RegisterClientDTO.php
    │   │   └── Authenticate/     # AuthenticateUserUseCase.php
    │   ├── Catalog/              # Casos de Uso de Catálogo
    │   │   ├── BrowseCatalog/    # BrowseServiceCatalogUseCase.php
    │   │   └── ManageServices/   # ManageServiceInventoryUseCase.php
    │   ├── Staffing/             # Casos de Uso de Mallas Horarias
    │   │   ├── GetAvailableSlots/# GetAvailableSlotsUseCase.php
    │   │   └── SaveSchedule/     # ConfigureScheduleUseCase.php
    │   ├── Booking/              # Casos de Uso de Reservas (Cruciales)
    │   │   ├── CreateBooking/    # CreateAppointmentUseCase.php, CreateAppointmentDTO.php
    │   │   ├── CancelBooking/    # CancelAppointmentUseCase.php
    │   │   └── Operation/        # ExecuteCheckInUseCase.php, CompleteServiceUseCase.php
    │   └── Billing/              # Casos de Uso Financieros
    │       ├── ProcessWebhook/   # ProcessOnlinePaymentWebhookUseCase.php
    │       └── PosPayment/       # RegisterPosPaymentUseCase.php
    │
    ├── Infrastructure/           # CAPA 3: ADAPTADORES TECNOLÓGICOS (Conexiones reales)
    │   ├── Shared/
    │   │   ├── Database/         # ConnectionFactory.php (Manejador de la conexión PDO)
    │   │   ├── Security/         # JwtTokenManager.php, EncryptionService.php
    │   │   ├── Errors/           # GlobalExceptionHandler.php (Formateador RFC 7807)
    │   │   ├── Routing/          # NUEVO: Router.php y archivo routes.php de la API
    │   │   └── Helpers/          # Utilitarios (DateTimeHelper.php, ResponseHelper.php)
    │   ├── IAM/                  # Implementación Física de IAM
    │   │   ├── Persistence/      # PdoUserRepository.php (Consultas SQL nativas)
    │   │   └── Http/             # LoginController.php, RegisterController.php
    │   ├── Catalog/              # Implementación Física de Catálogo
    │   │   ├── Persistence/      # PdoServiceRepository.php
    │   │   └── Http/             # BrowseCatalogController.php, CreatePromotionController.php
    │   ├── Staffing/             # Implementación Física de Staffing
    │   │   ├── Persistence/      # PdoStaffingRepository.php
    │   │   └── Http/             # GetSlotsController.php
    │   ├── Booking/              # Implementación Física de Reservas
    │   │   ├── Persistence/      # PdoAppointmentRepository.php
    │   │   └── Http/             # CreateAppointmentController.php, OperationsController.php
    │   ├── Billing/              # Implementación Física de Finanzas
    │   │   ├── Persistence/      # PdoPaymentRepository.php
    │   │   └── Http/             # PosPaymentController.php, PaymentWebhookController.php
    │   └── Integration/          # Integraciones Externas y Tareas Cron
    │       └── WhatsApp/
    │           ├── Http/         # WhatsAppWebhookController.php (Escucha mensajes de Meta)
    │           └── Workers/      # NUEVO: NotificationQueueWorker.php (Consola / CLI para Cron)
    │
    └── bootstrap.php             # Contenedor de Inyección de Dependencias Manual


4. Archivo de Arranque Central (src/bootstrap.php)
Para evitar el uso ilícito de acoplamientos ocultos o el uso de Service Locators, bootstrap.php actúa como el Contenedor de Inyección de Dependencias Manual. Centraliza la instanciación de clases de la infraestructura a la superficie, ordenadas de forma secuencial.

PHP
<?php
declare(strict_types=1);

// 1. Cargar el Autoloader estándar PSR-4
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Inicializar la Conexión Física PDO (Hostinger / XAMPP)
$pdo = \App\Infrastructure\Shared\Database\ConnectionFactory::createFromEnv();

// 3. Instanciar Repositorios de Infraestructura (Implementaciones de BD)
$pdoUserRepository        = new \App\Infrastructure\IAM\Persistence\PdoUserRepository($pdo);
$pdoAppointmentRepository = new \App\Infrastructure\Booking\Persistence\PdoAppointmentRepository($pdo);
$pdoServiceRepository     = new \App\Infrastructure\Catalog\Persistence\PdoServiceRepository($pdo);

// 4. Instanciar Casos de Uso de la Capa de Aplicación inyectando sus dependencias
$createAppointmentUseCase = new \App\Application\Booking\CreateBooking\CreateAppointmentUseCase(
    $pdoAppointmentRepository,
    $pdoServiceRepository
);

// 5. Instanciar Controladores HTTP de Infraestructura pasándoles los Casos de Uso
$createAppointmentController = new \App\Infrastructure\Booking\Http\CreateAppointmentController($createAppointmentUseCase);
$whatsAppWebhookController   = new \App\Infrastructure\Integration\WhatsApp\WhatsAppWebhookController($createAppointmentUseCase, $pdoUserRepository);
5. Plantilla de Código Patrón Integrador (Boilerplate de Referencia)
A continuación se muestra cómo interactúan las tres capas de manera desacoplada para el caso crítico de Creación de Cita con Bloqueo Anti-Overbooking. Este fragmento de código rige como ley de diseño de software para Antigravity 2.0.

Capa 1: Dominio (Definición abstracta libre de SQL)
PHP
<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

use App\Domain\Booking\Entities\Appointment;

interface AppointmentRepositoryInterface {
    /**
     * Verifica de forma atómica si el profesional tiene un choque de horarios en el servidor.
     */
    public function hasCollision(int $professionalId, string $start, string $end): bool;
    
    public function save(Appointment $appointment): void;
}
Capa 2: Aplicación (Orquestador del caso de uso)
PHP
<?php
declare(strict_types=1);

namespace App\Application\Booking\CreateBooking;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Entities\Appointment;

class CreateAppointmentUseCase {
    private AppointmentRepositoryInterface $repository;

    public function __construct(AppointmentRepositoryInterface $repository) {
        $this->repository = $repository;
    }

    public function execute(CreateAppointmentDTO $dto): array {
        // Regla de Negocio: Bloqueo de Concurrencia coordinado por el repositorio
        $hasCollision = $this->repository->hasCollision(
            $dto->professionalProfileId,
            $dto->scheduledTimestamp,
            $dto->estimatedEndTimestamp
        );

        if ($hasCollision) {
            throw new \DomainException("El horario seleccionado ya está reservado.", 422);
        }

        // Creación del objeto de negocio y persistencia
        $appointment = new Appointment($dto->clientProfileId, $dto->professionalProfileId, $dto->scheduledTimestamp);
        $this->repository->save($appointment);

        return [
            'success' => true,
            'appointment_id' => $appointment->getId()
        ];
    }
}
Capa 3: Infraestructura (Implementación real de persistencia con PDO)
PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Booking\Persistence;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Entities\Appointment;
use \PDO;

class PdoAppointmentRepository implements AppointmentRepositoryInterface {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function hasCollision(int $professionalId, string $start, string $end): bool {
        // Control riguroso de condiciones de carrera mediante bloqueo pesimista nativo MySQL
        $sql = "SELECT appointment_id FROM appointment 
                WHERE professional_profile_id = :prof_id 
                  AND status NOT IN ('CANCELLED', 'NOSHOW')
                  AND (:start_time < end_time AND :end_time > start_time) 
                FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'prof_id'    => $professionalId,
            'start_time' => $start,
            'end_time'   => $end
        ]);

        return $stmt->fetch() !== false;
    }

    public function save(Appointment $appointment): void {
        $sql = "INSERT INTO appointment (client_profile_id, professional_profile_id, service_id, branch_id, start_time, end_time, status) 
                VALUES (:client, :prof, :service, :branch, :start, :end, :status)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'client'  => $appointment->getClientId(),
            'prof'    => $appointment->getProfessionalId(),
            'service' => $appointment->getServiceId(),
            'branch'  => $appointment->getBranchId(),
            'start'   => $appointment->getStartTime(),
            'end'     => $appointment->getEndTime(),
            'status'  => $appointment->getStatus()
        ]);
    }
}


Plantillas de Código Patrón
Puedes pegar el texto de las plantillas de código al final del documento completo, creando una nueva sección dedicada: "6. Ampliación de la Arquitectura: Validadores, Eventos y Helpers", o bien anexarlo directamente al final de la sección 5 (la plantilla del Boilerplate).

Al formatearlo dentro de tu archivo .md, asegúrate de usar los bloques de código limpios con triple comilla invertida (```) seguidos de la palabra php para que mantenga el resaltado de sintaxis correcto:

Markdown
## 6. Ampliación de Arquitectura: Validadores, Eventos y Helpers

### A. Capa de Aplicación: Validadores de Entrada
Las validaciones de formato deben ocurrir en la capa de aplicación usando clases dedicadas antes de que el DTO toque el Caso de Uso.

```php
<?php
declare(strict_types=1);

namespace App\Application\Shared\Validators;

interface ValidatorInterface {
    public function validate(array $data): void;
}

class CreateAppointmentValidator implements ValidatorInterface {
    public function validate(array $data): void {
        if (empty($data['client_profile_id']) || !is_int($data['client_profile_id'])) {
            throw new \InvalidArgumentException("El ID del cliente es obligatorio.", 400);
        }
        if (empty($data['scheduled_timestamp']) || !strtotime($data['scheduled_timestamp'])) {
            throw new \InvalidArgumentException("La fecha no tiene un formato válido.", 400);
        }
        if (strtotime($data['scheduled_timestamp']) < time()) {
            throw new \InvalidArgumentException("No es posible agendar en el pasado.", 400);
        }
    }
}
B. Capa de Dominio: Arquitectura de Eventos
Permite desacoplar las acciones del sistema para que la PWA o el Bot de WhatsApp ejecuten procesos secundarios de forma limpia.

PHP
<?php
declare(strict_types=1);

namespace App\Domain\Shared\Events;

interface DomainEventInterface {
    public function getOccurredAt(): \DateTimeImmutable;
}

class EventDispatcher {
    private array $listeners = [];

    public function subscribe(string $eventClassName, callable $listener): void {
        $this->listeners[$eventClassName][] = $listener;
    }

    public function dispatch(DomainEventInterface $event): void {
        $eventClass = get_class($event);
        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $listener) {
                $listener($event);
            }
        }
    }
}
C. Capa de Infraestructura: Helpers (Utilidades del sistema)
Métodos estáticos puros para resolver problemas repetitivos de infraestructura, como formatear respuestas HTTP JSON estándar.

PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Helpers;

class ResponseHelper {
    public static function json(int $statusCode, bool $success, string $message, array $data = []): void {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode([
            'success'   => $success,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time()
        ]);
        exit;
    }
}

