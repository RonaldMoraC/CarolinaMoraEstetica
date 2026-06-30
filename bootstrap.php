<?php
declare(strict_types=1);

/**
 * ============================================================
 *  BOOTSTRAP — Contenedor de Inyección de Dependencias Manual
 *  Ecosistema Digital Carolina Mora
 * ============================================================
 *
 *  Orden de arranque garantizado:
 *    1. Autoloader PSR-4 de Composer
 *    2. Parser de variables de entorno (.env)
 *    3. Registro del GlobalExceptionHandler (perimetral)
 *    4. Instanciación de la conexión PDO
 *    5. Instanciación de Repositorios de Infraestructura
 *    6. Instanciación de Casos de Uso (Application Layer)
 *    7. Instanciación de Controladores HTTP (Infrastructure Layer)
 *    8. Construcción y devolución del Router configurado
 *
 *  Restricciones de arquitectura:
 *    - CERO variables globales: todo se retorna como objeto Router.
 *    - Las dependencias fluyen SIEMPRE de Infraestructura → Aplicación → Dominio.
 *    - Ninguna clase de Domain es instanciada aquí; solo Infraestructura y Aplicación.
 *
 *  Uso desde public/index.php:
 *    $router = require __DIR__ . '/../bootstrap.php';
 *    $router->dispatch();
 *
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
//  PASO 1 — AUTOLOADER PSR-4 (Composer)
// ─────────────────────────────────────────────────────────────
$autoloaderPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloaderPath)) {
    // Fallo crítico antes de que el GlobalExceptionHandler esté disponible.
    // Es el único echo permitido en todo el sistema bajo esta condición extrema.
    http_response_code(500);
    header('Content-Type: application/problem+json; charset=utf-8');
    echo json_encode([
        'type'     => 'https://carolinamoraestetica.com/errors/bootstrap-failure',
        'title'    => 'Error de Inicialización del Sistema',
        'status'   => 500,
        'detail'   => 'El autoloader de Composer no fue encontrado. Ejecuta: composer install',
        'instance' => '/bootstrap',
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

require_once $autoloaderPath;

// ─────────────────────────────────────────────────────────────
//  PASO 2 — CARGA DEL ARCHIVO .env
// ─────────────────────────────────────────────────────────────
/**
 * DotEnvLoader — Parser .env mínimo y seguro.
 *
 * No requiere dependencias externas (vlucas/phpdotenv).
 * Soporta:
 *   - Comentarios con #
 *   - Valores entre comillas simples y dobles
 *   - Prefijo opcional 'export'
 *   - Variables con espacios en el valor si están entre comillas
 *   - NO sobreescribe variables ya definidas en el entorno del servidor
 *     (importante para Hostinger donde las variables se definen a nivel de VHost).
 */
(static function (string $envFilePath): void {
    if (!file_exists($envFilePath)) {
        // En producción puede no existir el .env si las vars se inyectan desde el servidor.
        return;
    }

    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignorar comentarios y líneas vacías.
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Eliminar prefijo 'export ' (compatibilidad con shells).
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        // Separar clave=valor en la primera ocurrencia del signo igual.
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        // Eliminar comentarios inline: DB_PASS=secret # comentario
        if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
            $commentPos = strpos($value, ' #');
            if ($commentPos !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }
        }

        // Quitar comillas dobles preservando el contenido literal.
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
            // Procesar secuencias de escape estándar dentro de comillas dobles.
            $value = str_replace(['\\n', '\\t', '\\"', '\\\\'], ["\n", "\t", '"', '\\'], $value);
        }

        // Quitar comillas simples (sin procesado de escapes — literal puro).
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        // Solo registrar si la clave es válida y NO está ya definida en el entorno.
        // Así respetamos las variables inyectadas por Hostinger a nivel de servidor.
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
})(__DIR__ . '/.env');


// ─────────────────────────────────────────────────────────────
//  PASO 3 — REGISTRO DEL MANEJADOR GLOBAL DE EXCEPCIONES
// ─────────────────────────────────────────────────────────────
//
//  Debe registrarse INMEDIATAMENTE después del .env para que
//  cualquier fallo posterior (PDO, rutas, etc.) sea capturado
//  y formateado bajo RFC 7807 antes de llegar al cliente.

use App\Infrastructure\Shared\Logging\AppLogger;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

$isProduction = (($_ENV['APP_ENV'] ?? 'local') === 'production');
$logFilePath  = __DIR__ . '/logs/app.log';

GlobalExceptionHandler::register(
    isProduction: $isProduction,
    logFilePath:  $logFilePath
);


// ─────────────────────────────────────────────────────────────
//  PASO 4 — CONTENEDOR DE INYECCIÓN DE DEPENDENCIAS LAZY (Skill 1 / 3)
// ─────────────────────────────────────────────────────────────
//
//  Implementación de Carga Diferida (Lazy Loading) para evitar la instanciación
//  de recursos no utilizados (como WhatsApp o pasarelas de pago) durante el arranque.
//  Si faltan variables de entorno para estos servicios, el sistema continuará
//  funcionando de forma segura siempre que no se requieran esas rutas.
 
$lazyContainer = new class($logFilePath) {
    private array $instances = [];
    private ?\PDO $pdo = null;
    private string $logFilePath;

    public function __construct(string $logFilePath) {
        $this->logFilePath = $logFilePath;
    }

    private function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = \App\Infrastructure\Shared\Database\ConnectionFactory::createFromEnv();
        }
        return $this->pdo;
    }

    public function get(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        $instance = $this->createInstance($class);
        $this->instances[$class] = $instance;
        return $instance;
    }

    private function createInstance(string $class): object
    {
        return match ($class) {
            // Repositorios de Infraestructura (Paso 5)
            \App\Infrastructure\IAM\Persistence\PdoUserRepository::class =>
                new \App\Infrastructure\IAM\Persistence\PdoUserRepository($this->getPdo()),

            \App\Infrastructure\Catalog\Persistence\PdoServiceRepository::class =>
                new \App\Infrastructure\Catalog\Persistence\PdoServiceRepository($this->getPdo()),

            \App\Infrastructure\Catalog\Persistence\PdoPromotionRepository::class =>
                new \App\Infrastructure\Catalog\Persistence\PdoPromotionRepository($this->getPdo()),

            \App\Infrastructure\Catalog\Persistence\PdoBranchRepository::class =>
                new \App\Infrastructure\Catalog\Persistence\PdoBranchRepository($this->getPdo()),

            \App\Infrastructure\Catalog\Persistence\PdoCategoryRepository::class =>
                new \App\Infrastructure\Catalog\Persistence\PdoCategoryRepository($this->getPdo()),

            \App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class =>
                new \App\Infrastructure\Staffing\Persistence\PdoStaffingRepository($this->getPdo()),

            \App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class =>
                new \App\Infrastructure\Booking\Persistence\PdoAppointmentRepository($this->getPdo()),

            \App\Infrastructure\Booking\Persistence\PdoClientProfileRepository::class =>
                new \App\Infrastructure\Booking\Persistence\PdoClientProfileRepository($this->getPdo()),

            \App\Infrastructure\Billing\Persistence\PdoPaymentRepository::class =>
                new \App\Infrastructure\Billing\Persistence\PdoPaymentRepository($this->getPdo()),

            \App\Infrastructure\Billing\Persistence\PdoInvoiceRepository::class =>
                new \App\Infrastructure\Billing\Persistence\PdoInvoiceRepository($this->getPdo()),

            \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaNotificationQueueRepository::class =>
                new \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaNotificationQueueRepository($this->getPdo()),

            \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaMessageLogRepository::class =>
                new \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaMessageLogRepository($this->getPdo()),

            \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaChatSessionRepository::class =>
                new \App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaChatSessionRepository($this->getPdo()),

            \App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class =>
                new \App\Infrastructure\Shared\Audit\SystemAuditLogRepository($this->getPdo()),

            // ── Dashboard (Métricas del Panel Administrativo) ────────────
            \App\Infrastructure\Dashboard\Persistence\PdoDashboardMetricsRepository::class =>
                new \App\Infrastructure\Dashboard\Persistence\PdoDashboardMetricsRepository($this->getPdo()),

            // ── Logging ──────────────────────────────────────────────────
            \App\Infrastructure\Shared\Logging\AppLogger::class =>
                new \App\Infrastructure\Shared\Logging\AppLogger($this->logFilePath),

            // Middlewares (Paso 8)
            \App\Infrastructure\Shared\Security\AuthMiddleware::class =>
                new \App\Infrastructure\Shared\Security\AuthMiddleware(
                    $this->getSecurityJwtTokenManager()
                ),

            \App\Infrastructure\Shared\Security\ViewAuthMiddleware::class =>
                new \App\Infrastructure\Shared\Security\ViewAuthMiddleware(),

            // Casos de Uso (Paso 6)
            \App\Application\IAM\RegisterClient\RegisterNewClientUseCase::class =>
                new \App\Application\IAM\RegisterClient\RegisterNewClientUseCase(
                    $this->get(\App\Domain\IAM\Repositories\UserRepositoryInterface::class), // Inyectar interfaz
                    $this->get(\App\Domain\Booking\Repositories\ClientProfileRepositoryInterface::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class),
                    $this->getPdo()
                ),

            \App\Application\IAM\Authenticate\AuthenticateUserUseCase::class =>
                new \App\Application\IAM\Authenticate\AuthenticateUserUseCase(
                    $this->get(\App\Infrastructure\IAM\Persistence\PdoUserRepository::class),
                    $this->get(\App\Application\IAM\Authenticate\TokenManagerInterface::class)
                ),

            \App\Application\IAM\ManageRoles\AssignRoleUseCase::class =>
                new \App\Application\IAM\ManageRoles\AssignRoleUseCase(
                    $this->getPdo(),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\IAM\UpdateProfile\UpdateUserProfileUseCase::class =>
                new \App\Application\IAM\UpdateProfile\UpdateUserProfileUseCase(
                    $this->get(\App\Domain\IAM\Repositories\UserRepositoryInterface::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class),
                    $this->getPdo()
                ),

            \App\Application\IAM\Logout\LogoutUserUseCase::class =>
                new \App\Application\IAM\Logout\LogoutUserUseCase(
                    $this->getPdo()
                ),

            \App\Application\Catalog\GetProfessionalsByService\GetProfessionalsByServiceUseCase::class =>
                new \App\Application\Catalog\GetProfessionalsByService\GetProfessionalsByServiceUseCase(
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class)
                ),

            \App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase::class =>
                new \App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase(
                    $this->get(\App\Infrastructure\Catalog\Persistence\PdoServiceRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Catalog\BrowseCatalog\BrowseServiceCatalogUseCase::class =>
                new \App\Application\Catalog\BrowseCatalog\BrowseServiceCatalogUseCase(
                    $this->get(\App\Domain\Catalog\Repositories\ServiceRepositoryInterface::class)
                ),
            
            \App\Application\IAM\ManageServices\ManageProfessionalServicesUseCase::class =>
                new \App\Application\IAM\ManageServices\ManageProfessionalServicesUseCase(
                    $this->get(\App\Domain\Staffing\Repositories\StaffingRepositoryInterface::class),
                    $this->get(\App\Domain\Catalog\Repositories\ServiceRepositoryInterface::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),


            \App\Application\Catalog\ManagePromotions\CreatePromotionUseCase::class =>
                new \App\Application\Catalog\ManagePromotions\CreatePromotionUseCase(
                    $this->get(\App\Infrastructure\Catalog\Persistence\PdoPromotionRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase::class =>
                new \App\Application\Staffing\GetAvailableSlots\GetAvailableSlotsUseCase(
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class),
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class)
                ),

            \App\Application\Staffing\SaveSchedule\ConfigureScheduleUseCase::class =>
                new \App\Application\Staffing\SaveSchedule\ConfigureScheduleUseCase(
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Staffing\ManageExceptions\CreateScheduleExceptionUseCase::class =>
                new \App\Application\Staffing\ManageExceptions\CreateScheduleExceptionUseCase(
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Staffing\ListProfessionals\GetAllProfessionalsUseCase::class =>
                new \App\Application\Staffing\ListProfessionals\GetAllProfessionalsUseCase(
                    $this->get(\App\Domain\Staffing\Repositories\StaffingRepositoryInterface::class)
                ),

            \App\Application\Booking\CreateBooking\CreateAppointmentUseCase::class =>
                new \App\Application\Booking\CreateBooking\CreateAppointmentUseCase(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Catalog\Persistence\PdoServiceRepository::class),
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Booking\CancelBooking\CancelAppointmentUseCase::class =>
                new \App\Application\Booking\CancelBooking\CancelAppointmentUseCase(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Booking\Operation\ExecuteCheckInUseCase::class =>
                new \App\Application\Booking\Operation\ExecuteCheckInUseCase(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Booking\Operation\CompleteServiceUseCase::class =>
                new \App\Application\Booking\Operation\CompleteServiceUseCase(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Booking\Operation\MarkNoShowUseCase::class =>
                new \App\Application\Booking\Operation\MarkNoShowUseCase(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Billing\ProcessWebhook\ProcessOnlinePaymentWebhookUseCase::class =>
                new \App\Application\Billing\ProcessWebhook\ProcessOnlinePaymentWebhookUseCase(
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoPaymentRepository::class),
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoInvoiceRepository::class),
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Billing\PosPayment\RegisterPosPaymentUseCase::class =>
                new \App\Application\Billing\PosPayment\RegisterPosPaymentUseCase(
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoPaymentRepository::class),
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoInvoiceRepository::class),
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Billing\CashRegister\OpenCashRegisterUseCase::class =>
                new \App\Application\Billing\CashRegister\OpenCashRegisterUseCase(
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoPaymentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            \App\Application\Billing\CashRegister\CloseCashRegisterUseCase::class =>
                new \App\Application\Billing\CashRegister\CloseCashRegisterUseCase(
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoPaymentRepository::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class)
                ),

            // ── Dashboard ────────────────────────────────────────────────
            \App\Application\Dashboard\GetMetrics\GetDashboardMetricsUseCase::class =>
                new \App\Application\Dashboard\GetMetrics\GetDashboardMetricsUseCase(
                    $this->get(\App\Infrastructure\Dashboard\Persistence\PdoDashboardMetricsRepository::class)
                ),

            // Controladores HTTP (Paso 7)
            \App\Infrastructure\IAM\Http\LoginController::class =>
                new \App\Infrastructure\IAM\Http\LoginController(
                    $this->get(\App\Application\IAM\Authenticate\AuthenticateUserUseCase::class),
                    $this->getSecurityJwtTokenManager(),
                    $this->getPdo()
                ),

            \App\Infrastructure\IAM\Http\RegisterController::class =>
                new \App\Infrastructure\IAM\Http\RegisterController(
                    $this->get(\App\Application\IAM\RegisterClient\RegisterNewClientUseCase::class)
                ),

            \App\Infrastructure\IAM\Http\LogoutController::class =>
                new \App\Infrastructure\IAM\Http\LogoutController(
                    $this->get(\App\Application\IAM\Logout\LogoutUserUseCase::class),
                    $this->getSecurityJwtTokenManager()
                ),

            \App\Infrastructure\IAM\Http\UserController::class =>
                new \App\Infrastructure\IAM\Http\UserController(
                    $this->get(\App\Infrastructure\IAM\Persistence\PdoUserRepository::class),
                    $this->get(\App\Application\IAM\ManageRoles\AssignRoleUseCase::class),
                    $this->get(\App\Infrastructure\Shared\Audit\SystemAuditLogRepository::class),
                    $this->getPdo()
                ),

            \App\Infrastructure\IAM\Http\MeController::class =>
                new \App\Infrastructure\IAM\Http\MeController(
                    $this->get(\App\Infrastructure\IAM\Persistence\PdoUserRepository::class),
                    $this->getPdo()
                ),

            \App\Infrastructure\IAM\Http\UpdateProfileController::class =>
                new \App\Infrastructure\IAM\Http\UpdateProfileController(
                    $this->get(\App\Application\IAM\UpdateProfile\UpdateUserProfileUseCase::class)
                ),

            \App\Infrastructure\IAM\Http\ProfessionalServiceController::class =>
                new \App\Infrastructure\IAM\Http\ProfessionalServiceController(
                    $this->get(\App\Application\IAM\ManageServices\ManageProfessionalServicesUseCase::class)
                ),

            \App\Infrastructure\Catalog\Http\BrowseCatalogController::class =>
                new \App\Infrastructure\Catalog\Http\BrowseCatalogController(
                    $this->get(\App\Application\Catalog\BrowseCatalog\BrowseServiceCatalogUseCase::class),
                    $this->get(\App\Domain\Catalog\Repositories\ServiceRepositoryInterface::class)
                ),

            \App\Infrastructure\Catalog\Http\CreateServiceController::class =>
                new \App\Infrastructure\Catalog\Http\CreateServiceController(
                    $this->get(\App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase::class)
                ),

            \App\Infrastructure\Catalog\Http\UpdateServiceController::class =>
                new \App\Infrastructure\Catalog\Http\UpdateServiceController(
                    $this->get(\App\Application\Catalog\ManageServices\ManageServiceInventoryUseCase::class)
                ),

            \App\Infrastructure\Catalog\Http\CreatePromotionController::class =>
                new \App\Infrastructure\Catalog\Http\CreatePromotionController(
                    $this->get(\App\Application\Catalog\ManagePromotions\CreatePromotionUseCase::class)
                ),

            \App\Infrastructure\Catalog\Http\GetCategoriesController::class =>
                new \App\Infrastructure\Catalog\Http\GetCategoriesController(
                    $this->get(\App\Infrastructure\Catalog\Persistence\PdoCategoryRepository::class)
                ),

            \App\Infrastructure\Catalog\Http\GetProfessionalsByServiceController::class =>
                new \App\Infrastructure\Catalog\Http\GetProfessionalsByServiceController(
                    $this->get(\App\Application\Catalog\GetProfessionalsByService\GetProfessionalsByServiceUseCase::class)
                ),

            \App\Infrastructure\Staffing\Http\GetSlotsController::class =>
                new \App\Infrastructure\Staffing\Http\GetSlotsController(
                    $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class)
                ),

            \App\Infrastructure\Staffing\Http\SaveScheduleController::class =>
                new \App\Infrastructure\Staffing\Http\SaveScheduleController(
                    $this->get(\App\Application\Staffing\SaveSchedule\ConfigureScheduleUseCase::class)
                ),

            \App\Infrastructure\Staffing\Http\ScheduleExceptionController::class =>
                new \App\Infrastructure\Staffing\Http\ScheduleExceptionController(
                    $this->get(\App\Application\Staffing\ManageExceptions\CreateScheduleExceptionUseCase::class)
                ),

            \App\Infrastructure\Staffing\Http\GetProfessionalsController::class =>
                new \App\Infrastructure\Staffing\Http\GetProfessionalsController(
                    $this->get(\App\Application\Staffing\ListProfessionals\GetAllProfessionalsUseCase::class)
                ),

            \App\Infrastructure\Booking\Http\CreateAppointmentController::class =>
                new \App\Infrastructure\Booking\Http\CreateAppointmentController(
                    $this->get(\App\Application\Booking\CreateBooking\CreateAppointmentUseCase::class)
                ),

            \App\Infrastructure\Booking\Http\CancelAppointmentController::class =>
                new \App\Infrastructure\Booking\Http\CancelAppointmentController(
                    $this->get(\App\Application\Booking\CancelBooking\CancelAppointmentUseCase::class)
                ),

            \App\Infrastructure\Booking\Http\OperationsController::class =>
                new \App\Infrastructure\Booking\Http\OperationsController(
                    $this->get(\App\Application\Booking\Operation\ExecuteCheckInUseCase::class),
                    $this->get(\App\Application\Booking\Operation\CompleteServiceUseCase::class),
                    $this->get(\App\Application\Booking\Operation\MarkNoShowUseCase::class)
                ),

            \App\Infrastructure\Booking\Http\GetAppointmentsController::class =>
                new \App\Infrastructure\Booking\Http\GetAppointmentsController(
                    $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class)
                ),

            // ── Dashboard Controllers ─────────────────────────────────────
            \App\Infrastructure\Dashboard\Http\AdminMetricsController::class =>
                new \App\Infrastructure\Dashboard\Http\AdminMetricsController(
                    $this->get(\App\Application\Dashboard\GetMetrics\GetDashboardMetricsUseCase::class),
                    $this->get(\App\Infrastructure\Shared\Logging\AppLogger::class)
                ),

            \App\Infrastructure\Billing\Http\PosPaymentController::class =>
                new \App\Infrastructure\Billing\Http\PosPaymentController(
                    $this->get(\App\Application\Billing\PosPayment\RegisterPosPaymentUseCase::class)
                ),

            \App\Infrastructure\Billing\Http\PaymentWebhookController::class =>
                new \App\Infrastructure\Billing\Http\PaymentWebhookController(
                    $this->get(\App\Application\Billing\ProcessWebhook\ProcessOnlinePaymentWebhookUseCase::class)
                ),

            \App\Infrastructure\Billing\Http\GetInvoicesController::class =>
                new \App\Infrastructure\Billing\Http\GetInvoicesController(
                    $this->get(\App\Infrastructure\Billing\Persistence\PdoInvoiceRepository::class)
                ),

            \App\Infrastructure\Billing\Http\CashRegisterController::class =>
                new \App\Infrastructure\Billing\Http\CashRegisterController(
                    $this->get(\App\Application\Billing\CashRegister\OpenCashRegisterUseCase::class),
                    $this->get(\App\Application\Billing\CashRegister\CloseCashRegisterUseCase::class)
                ),

            \App\Infrastructure\Integration\WhatsApp\Http\WhatsAppWebhookController::class =>
                new \App\Infrastructure\Integration\WhatsApp\Http\WhatsAppWebhookController(
                    $this->getWhatsAppWebhookSignatureValidator(),
                    $this->get(\App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaNotificationQueueRepository::class),
                    $this->get(\App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaMessageLogRepository::class),
                    $this->get(\App\Infrastructure\Integration\WhatsApp\Persistence\PdoWaChatSessionRepository::class),
                    $this->get(\App\Application\Booking\CreateBooking\CreateAppointmentUseCase::class)
                ),

            // ── Interfaces y Abstracciones (Skill 1) ─────────────
            \App\Application\IAM\Authenticate\TokenManagerInterface::class =>
                $this->getSecurityJwtTokenManager(),

            // Mapeo de Interfaces a Implementaciones Concretas (Skill 1)
            \App\Domain\IAM\Repositories\UserRepositoryInterface::class =>
                $this->get(\App\Infrastructure\IAM\Persistence\PdoUserRepository::class),

            \App\Domain\Booking\Repositories\ClientProfileRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Booking\Persistence\PdoClientProfileRepository::class),


            \App\Domain\Catalog\Repositories\ServiceRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Catalog\Persistence\PdoServiceRepository::class),

            \App\Domain\Booking\Repositories\AppointmentRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Booking\Persistence\PdoAppointmentRepository::class),

            \App\Domain\Catalog\Repositories\PromotionRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Catalog\Persistence\PdoPromotionRepository::class),

            \App\Domain\Catalog\Repositories\BranchRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Catalog\Persistence\PdoBranchRepository::class),

            \App\Domain\Staffing\Repositories\StaffingRepositoryInterface::class =>
                $this->get(\App\Infrastructure\Staffing\Persistence\PdoStaffingRepository::class),
            default => throw new \RuntimeException("El servicio '{$class}' no está registrado en el contenedor lazy."),
        };
    }

    // Servicios de soporte y configuración compartidos instanciados de manera diferida
    private ?\App\Infrastructure\Shared\Security\JwtTokenManager $jwtTokenManager = null;
    private function getSecurityJwtTokenManager(): \App\Infrastructure\Shared\Security\JwtTokenManager
    {
        if ($this->jwtTokenManager === null) {
            if (!isset($_ENV['JWT_SECRET']) && !getenv('JWT_SECRET')) {
                throw new \RuntimeException('JWT_SECRET no está definido en el archivo .env o en las variables de entorno.');
            }
            $secret = (string) ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
            if ($secret === '') {
                throw new \RuntimeException('JWT_SECRET no puede estar vacío. Defínelo en el archivo .env.');
            }
            $ttl = (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600);
            $this->jwtTokenManager = new \App\Infrastructure\Shared\Security\JwtTokenManager($secret, $ttl);
        }
        return $this->jwtTokenManager;
    }

    private ?\App\Infrastructure\Integration\WhatsApp\Services\WebhookSignatureValidator $webhookSignatureValidator = null;
    private function getWhatsAppWebhookSignatureValidator(): \App\Infrastructure\Integration\WhatsApp\Services\WebhookSignatureValidator
    {
        if (!isset($_ENV['WHATSAPP_APP_SECRET']) && !getenv('WHATSAPP_APP_SECRET')) {
            throw new \RuntimeException('WHATSAPP_APP_SECRET no está definido en el archivo .env o en las variables de entorno.');
        }
        if ($this->webhookSignatureValidator === null) {
            $appSecret = $_ENV['WHATSAPP_APP_SECRET'] ?? getenv('WHATSAPP_APP_SECRET') ?? '';
            if ($appSecret === '') {
                throw new \RuntimeException('WHATSAPP_APP_SECRET no puede estar vacío en el .env para operar WhatsApp.');
            }
            $this->webhookSignatureValidator = new \App\Infrastructure\Integration\WhatsApp\Services\WebhookSignatureValidator($appSecret);
        }
        return $this->webhookSignatureValidator;
    }
};

$container = static function (string $class) use ($lazyContainer): object {
    return $lazyContainer->get($class);
};


// ─────────────────────────────────────────────────────────────
//  PASO 9 — CONSTRUCCIÓN DEL ROUTER CON RUTAS REGISTRADAS
// ─────────────────────────────────────────────────────────────

use App\Infrastructure\Shared\Routing\Router;

$router = new Router($container, new GlobalExceptionHandler());

// ── Cargar tabla de rutas desde archivo dedicado ─────────────
// El archivo routes.php recibe el $router ya instanciado y registra
// todas las rutas sobre él. Esta separación mantiene el bootstrap
// limpio y permite modificar rutas sin tocarlo.
$routesFile = __DIR__ . '/src/Infrastructure/Shared/Routing/routes.php';
if (file_exists($routesFile)) {
    require $routesFile;
}

// ─────────────────────────────────────────────────────────────
//  RETORNAR EL ROUTER AL FRONT CONTROLLER
// ─────────────────────────────────────────────────────────────
//  public/index.php recibe este objeto y llama a $router->dispatch().
//  NINGUNA variable de este archivo escapa al scope del require.
return $router;
