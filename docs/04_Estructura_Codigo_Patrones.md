Este manual define la arquitectura fÃ­sica del cÃ³digo fuente y las directrices de programaciÃ³n obligatorias para el desarrollo de la estÃ©tica. Al ejecutarse sobre entornos estÃ¡ndar (XAMPP en desarrollo / Hostinger en producciÃ³n), el sistema dependerÃ¡ de un diseÃ±o desacoplado en PHP Puro (v8.x+) utilizando un autoloader PSR-4 para garantizar rendimiento y mantenibilidad extrema.

1. Patrones de ProgramaciÃ³n y Reglas de Escritura Obligatorias
Antigravity 2.0 deberÃ¡ rechazar cualquier lrujo de cÃ³digo que viole las siguientes cuatro directivas de ingenierÃ­a:

1.1. DeclaraciÃ³n de Tipado Estricto (strict_types)
Absolutamente todos los archivos con extensiÃ³n .php en el proyecto deben iniciar exactamente con la siguiente lÃ­nea de cÃ³digo, sin excepciones:

PHP
<?php
declare(strict_types=1);
Esto prohÃ­be las coerciones implÃ­citas de tipos de PHP (por ejemplo, que un string "5" sea aceptado automÃ¡ticamente en un parÃ¡metro que exige int), forzando fallos en tiempo de ejecuciÃ³n si la data no es perfectamente limpia.

1.2. InyecciÃ³n de Dependencias Manual por Constructor
Queda estrictamente prohibido el uso de la palabra clave new dentro de los Casos de Uso o Controladores para instanciar dependencias o servicios.

Todas las clases deben recibir sus dependencias de forma pasiva a travÃ©s del mÃ©todo __construct apuntando a interfaces (abstracciones), nunca a clases concretas.

Ejemplo de diseÃ±o requerido:

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
Queda terminantemente prohibido concatenar variables directas en strings de consultas SQL o usar funciones obsoletas. Toda interacciÃ³n de escritura o lectura con MySQL debe utilizar marcadores de posiciÃ³n (:parametro) y vinculaciÃ³n explÃ­cita mediante execute().

PHP
$stmt = $this->pdo->prepare("SELECT * FROM user WHERE email = :email AND is_active = 1");
$stmt->execute(['email' => $email]);
1.4. Manejo Global de Excepciones y Errores
NingÃºn controlador de la API debe envolver su lÃ³gica en bloques try-catch genÃ©ricos para ocultar fallos.

Si ocurre un error de negocio, se debe lanzar una excepciÃ³n semÃ¡ntica personalizada que herede de DomainException.

Un componente centralizado de infraestructura (GlobalExceptionHandler) capturarÃ¡ de forma perimetral cualquier excepciÃ³n no controlada, registrarÃ¡ el incidente de forma segura y devolverÃ¡ la respuesta JSON formateada bajo el estÃ¡ndar RFC 7807 definido en el contrato de la API.

2. Ãrbol de Directorios Estricto: Capas Internas (Domain y Application)
A continuaciÃ³n se detalla la anatomÃ­a exacta del directorio src/ para las capas del Dominio (Reglas puras de negocio) y de AplicaciÃ³n (Casos de uso/OrquestaciÃ³n). Esta estructura es agnÃ³stica a la infraestructura; no sabe nada de bases de datos, ni de servidores, ni de HTTP.

raiz-del-proyecto/
â”œâ”€â”€ logs/                         # Registros de errores y auditorÃ­a del sistema (.log)
â”œâ”€â”€ vendor/                       # Dependencias externas gestionadas por Composer
â”œâ”€â”€ public/                       # Ãšnico directorio accesible desde la web (RaÃ­z PÃºblica)
â”‚   â”œâ”€â”€ index.php                 # Front Controller: Punto de entrada Ãºnico de la API
â”‚   â”œâ”€â”€ .htaccess                 # Directivas Apache, HTTPS estricto y reescritura de URLs
â”‚   â”œâ”€â”€ manifest.json             # Manifiesto de la PWA para instalaciÃ³n en dispositivos
â”‚   â”œâ”€â”€ sw.js                     # Service Worker de la PWA (Estrategias de cachÃ© offline)
â”‚   â”œâ”€â”€ assets/                   # Recursos estÃ¡ticos (CSS, JS compilado, fuentes, logos)
â”‚   â””â”€â”€ storage/                  # Archivos dinÃ¡micos pÃºblicos (Vouchers de pago, PDFs)
â”‚
â””â”€â”€ src/
    â”œâ”€â”€ Domain/                   # CAPA 1: LOGICA PURA DEL NEGOCIO (Sin SQL ni Frameworks)
    â”‚   â”œâ”€â”€ Shared/
    â”‚   â”‚   â”œâ”€â”€ ValueObjects/     # Objetos inmutables (Money.php, TimeRange.php)
    â”‚   â”‚   â”œâ”€â”€ Exceptions/       # Excepciones de negocio (DomainException.php)
    â”‚   â”‚   â””â”€â”€ Events/           # Despachador y Contratos (DomainEventInterface.php)
    â”‚   â”œâ”€â”€ IAM/                  # Contexto: Control de Accesos e Identidades
    â”‚   â”‚   â”œâ”€â”€ Entities/         # User.php, Role.php
    â”‚   â”‚   â””â”€â”€ Repositories/     # UserRepositoryInterface.php
    â”‚   â”œâ”€â”€ Catalog/              # Contexto: CatÃ¡logo de Servicios y Promociones
    â”‚   â”‚   â”œâ”€â”€ Entities/         # Service.php, Promotion.php
    â”‚   â”‚   â””â”€â”€ Repositories/     # ServiceRepositoryInterface.php
    â”‚   â”œâ”€â”€ Staffing/             # Contexto: Horarios y Disponibilidad de Especialistas
    â”‚   â”‚   â”œâ”€â”€ Entities/         # ProfessionalProfile.php, WorkSchedule.php
    â”‚   â”‚   â””â”€â”€ Repositories/     # StaffingRepositoryInterface.php
    â”‚   â”œâ”€â”€ Booking/              # Contexto Core: GestiÃ³n y Flujo de Citas
    â”‚   â”‚   â”œâ”€â”€ Entities/         # Appointment.php, ClientProfile.php
    â”‚   â”‚   â””â”€â”€ Repositories/     # AppointmentRepositoryInterface.php
    â”‚   â””â”€â”€ Billing/              # Contexto Financiero: FacturaciÃ³n, Caja y Pagos
    â”‚       â”œâ”€â”€ Entities/         # Payment.php, Invoice.php
    â”‚       â””â”€â”€ Repositories/     # PaymentRepositoryInterface.php
    â”‚
    â”œâ”€â”€ Application/              # CAPA 2: CASOS DE USO (Orquestadores de Acciones)
    â”‚   â”œâ”€â”€ Shared/
    â”‚   â”‚   â””â”€â”€ Validators/       # Sanitizadores y validadores de DTOs (InputSanitizer.php)
    â”‚   â”œâ”€â”€ IAM/                  # Casos de Uso de AutenticaciÃ³n y Registro
    â”‚   â”‚   â”œâ”€â”€ RegisterClient/   # RegisterNewClientUseCase.php, RegisterClientDTO.php
    â”‚   â”‚   â””â”€â”€ Authenticate/     # AuthenticateUserUseCase.php
    â”‚   â”œâ”€â”€ Catalog/              # Casos de Uso de CatÃ¡logo
    â”‚   â”‚   â”œâ”€â”€ BrowseCatalog/    # BrowseServiceCatalogUseCase.php
    â”‚   â”‚   â””â”€â”€ ManageServices/   # ManageServiceInventoryUseCase.php
    â”‚   â”œâ”€â”€ Staffing/             # Casos de Uso de Mallas Horarias
    â”‚   â”‚   â”œâ”€â”€ GetAvailableSlots/# GetAvailableSlotsUseCase.php
    â”‚   â”‚   â””â”€â”€ SaveSchedule/     # ConfigureScheduleUseCase.php
    â”‚   â”œâ”€â”€ Booking/              # Casos de Uso de Reservas (Cruciales)
    â”‚   â”‚   â”œâ”€â”€ CreateBooking/    # CreateAppointmentUseCase.php, CreateAppointmentDTO.php
    â”‚   â”‚   â”œâ”€â”€ CancelBooking/    # CancelAppointmentUseCase.php
    â”‚   â”‚   â””â”€â”€ Operation/        # ExecuteCheckInUseCase.php, CompleteServiceUseCase.php
    â”‚   â””â”€â”€ Billing/              # Casos de Uso Financieros
    â”‚       â”œâ”€â”€ ProcessWebhook/   # ProcessOnlinePaymentWebhookUseCase.php
    â”‚       â””â”€â”€ PosPayment/       # RegisterPosPaymentUseCase.php
    â”‚
    â”œâ”€â”€ Infrastructure/           # CAPA 3: ADAPTADORES TECNOLÃ“GICOS (Conexiones reales)
    â”‚   â”œâ”€â”€ Shared/
    â”‚   â”‚   â”œâ”€â”€ Database/         # ConnectionFactory.php (Manejador de la conexiÃ³n PDO)
    â”‚   â”‚   â”œâ”€â”€ Security/         # JwtTokenManager.php, EncryptionService.php
    â”‚   â”‚   â”œâ”€â”€ Errors/           # GlobalExceptionHandler.php (Formateador RFC 7807)
    â”‚   â”‚   â”œâ”€â”€ Routing/          # NUEVO: Router.php y archivo routes.php de la API
    â”‚   â”‚   â””â”€â”€ Helpers/          # Utilitarios (DateTimeHelper.php, ResponseHelper.php)
    â”‚   â”œâ”€â”€ IAM/                  # ImplementaciÃ³n FÃ­sica de IAM
    â”‚   â”‚   â”œâ”€â”€ Persistence/      # PdoUserRepository.php (Consultas SQL nativas)
    â”‚   â”‚   â””â”€â”€ Http/             # LoginController.php, RegisterController.php
    â”‚   â”œâ”€â”€ Catalog/              # ImplementaciÃ³n FÃ­sica de CatÃ¡logo
    â”‚   â”‚   â”œâ”€â”€ Persistence/      # PdoServiceRepository.php
    â”‚   â”‚   â””â”€â”€ Http/             # BrowseCatalogController.php, CreatePromotionController.php
    â”‚   â”œâ”€â”€ Staffing/             # ImplementaciÃ³n FÃ­sica de Staffing
    â”‚   â”‚   â”œâ”€â”€ Persistence/      # PdoStaffingRepository.php
    â”‚   â”‚   â””â”€â”€ Http/             # GetSlotsController.php
    â”‚   â”œâ”€â”€ Booking/              # ImplementaciÃ³n FÃ­sica de Reservas
    â”‚   â”‚   â”œâ”€â”€ Persistence/      # PdoAppointmentRepository.php
    â”‚   â”‚   â””â”€â”€ Http/             # CreateAppointmentController.php, OperationsController.php
    â”‚   â”œâ”€â”€ Billing/              # ImplementaciÃ³n FÃ­sica de Finanzas
    â”‚   â”‚   â”œâ”€â”€ Persistence/      # PdoPaymentRepository.php
    â”‚   â”‚   â””â”€â”€ Http/             # PosPaymentController.php, PaymentWebhookController.php
    â”‚   â””â”€â”€ Integration/          # Integraciones Externas y Tareas Cron
    â”‚       â””â”€â”€ WhatsApp/
    â”‚           â”œâ”€â”€ Http/         # WhatsAppWebhookController.php (Escucha mensajes de Meta)
    â”‚           â””â”€â”€ Workers/      # NUEVO: NotificationQueueWorker.php (Consola / CLI para Cron)
    â”‚
    â””â”€â”€ bootstrap.php             # Contenedor de InyecciÃ³n de Dependencias Manual


4. Archivo de Arranque Central (src/bootstrap.php)
Para evitar el uso ilÃ­cito de acoplamientos ocultos o el uso de Service Locators, bootstrap.php actÃºa como el Contenedor de InyecciÃ³n de Dependencias Manual. Centraliza la instanciaciÃ³n de clases de la infraestructura a la superficie, ordenadas de forma secuencial.

PHP
<?php
declare(strict_types=1);

// 1. Cargar el Autoloader estÃ¡ndar PSR-4
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Inicializar la ConexiÃ³n FÃ­sica PDO (Hostinger / XAMPP)
$pdo = \App\Infrastructure\Shared\Database\ConnectionFactory::createFromEnv();

// 3. Instanciar Repositorios de Infraestructura (Implementaciones de BD)
$pdoUserRepository        = new \App\Infrastructure\IAM\Persistence\PdoUserRepository($pdo);
$pdoAppointmentRepository = new \App\Infrastructure\Booking\Persistence\PdoAppointmentRepository($pdo);
$pdoServiceRepository     = new \App\Infrastructure\Catalog\Persistence\PdoServiceRepository($pdo);

// 4. Instanciar Casos de Uso de la Capa de AplicaciÃ³n inyectando sus dependencias
$createAppointmentUseCase = new \App\Application\Booking\CreateBooking\CreateAppointmentUseCase(
    $pdoAppointmentRepository,
    $pdoServiceRepository
);

// 5. Instanciar Controladores HTTP de Infraestructura pasÃ¡ndoles los Casos de Uso
$createAppointmentController = new \App\Infrastructure\Booking\Http\CreateAppointmentController($createAppointmentUseCase);
$whatsAppWebhookController   = new \App\Infrastructure\Integration\WhatsApp\WhatsAppWebhookController($createAppointmentUseCase, $pdoUserRepository);
5. Plantilla de CÃ³digo PatrÃ³n Integrador (Boilerplate de Referencia)
A continuaciÃ³n se muestra cÃ³mo interactÃºan las tres capas de manera desacoplada para el caso crÃ­tico de CreaciÃ³n de Cita con Bloqueo Anti-Overbooking. Este fragmento de cÃ³digo rige como ley de diseÃ±o de software para Antigravity 2.0.

Capa 1: Dominio (DefiniciÃ³n abstracta libre de SQL)
PHP
<?php
declare(strict_types=1);

namespace App\Domain\Booking\Repositories;

use App\Domain\Booking\Entities\Appointment;

interface AppointmentRepositoryInterface {
    /**
     * Verifica de forma atÃ³mica si el profesional tiene un choque de horarios en el servidor.
     */
    public function hasCollision(int $professionalId, string $start, string $end): bool;
    
    public function save(Appointment $appointment): void;
}
Capa 2: AplicaciÃ³n (Orquestador del caso de uso)
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
            throw new \DomainException("El horario seleccionado ya estÃ¡ reservado.", 422);
        }

        // CreaciÃ³n del objeto de negocio y persistencia
        $appointment = new Appointment($dto->clientProfileId, $dto->professionalProfileId, $dto->scheduledTimestamp);
        $this->repository->save($appointment);

        return [
            'success' => true,
            'appointment_id' => $appointment->getId()
        ];
    }
}
Capa 3: Infraestructura (ImplementaciÃ³n real de persistencia con PDO)
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


Plantillas de CÃ³digo PatrÃ³n
Puedes pegar el texto de las plantillas de cÃ³digo al final del documento completo, creando una nueva secciÃ³n dedicada: "6. AmpliaciÃ³n de la Arquitectura: Validadores, Eventos y Helpers", o bien anexarlo directamente al final de la secciÃ³n 5 (la plantilla del Boilerplate).

Al formatearlo dentro de tu archivo .md, asegÃºrate de usar los bloques de cÃ³digo limpios con triple comilla invertida (```) seguidos de la palabra php para que mantenga el resaltado de sintaxis correcto:

Markdown
## 6. AmpliaciÃ³n de Arquitectura: Validadores, Eventos y Helpers

### A. Capa de AplicaciÃ³n: Validadores de Entrada
Las validaciones de formato deben ocurrir en la capa de aplicaciÃ³n usando clases dedicadas antes de que el DTO toque el Caso de Uso.

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
            throw new \InvalidArgumentException("La fecha no tiene un formato vÃ¡lido.", 400);
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
MÃ©todos estÃ¡ticos puros para resolver problemas repetitivos de infraestructura, como formatear respuestas HTTP JSON estÃ¡ndar.

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


