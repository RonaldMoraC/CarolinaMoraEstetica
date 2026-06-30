<?php
declare(strict_types=1);

/**
 * ============================================================
 *  GENERADOR DE ESQUELETO — Ecosistema Digital Carolina Mora
 * ============================================================
 *  Arquitectura: Clean Architecture (Domain / Application /
 *                Infrastructure / Presentation)
 *  Stack:        PHP 8.2+  |  PSR-4  |  MySQL InnoDB
 *  Entorno:      XAMPP (local) → Hostinger Premium (producción)
 *
 *  USO:
 *    CLI:       php generar_esqueleto.php
 *    Navegador: http://localhost/CarolinaMoraEstetica/generar_esqueleto.php
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
//  CONFIGURACIÓN GLOBAL
// ─────────────────────────────────────────────────────────────

/** Raíz absoluta del proyecto (directorio donde vive este script). */
define('PROJECT_ROOT', __DIR__);

/** Modo de salida: true = HTML (navegador), false = texto plano (CLI). */
define('IS_CLI', PHP_SAPI === 'cli');

// ─────────────────────────────────────────────────────────────
//  HELPERS DE SALIDA
// ─────────────────────────────────────────────────────────────

function out(string $message, string $type = 'info'): void
{
    if (IS_CLI) {
        $prefix = match ($type) {
            'ok'    => "\033[32m[OK]   \033[0m",
            'mkdir' => "\033[34m[DIR]  \033[0m",
            'skip'  => "\033[33m[SKIP] \033[0m",
            'error' => "\033[31m[ERR]  \033[0m",
            'title' => "\033[1;36m",
            default => "       ",
        };
        $suffix = ($type === 'title') ? "\033[0m" : '';
        echo $prefix . $message . $suffix . PHP_EOL;
    } else {
        $color = match ($type) {
            'ok'    => '#22c55e',
            'mkdir' => '#3b82f6',
            'skip'  => '#f59e0b',
            'error' => '#ef4444',
            'title' => '#a78bfa',
            default => '#94a3b8',
        };
        $tag = ($type === 'title') ? 'h3' : 'p';
        $prefix = match ($type) {
            'ok'    => '✅ ',
            'mkdir' => '📁 ',
            'skip'  => '⏭️  ',
            'error' => '❌ ',
            'title' => '',
            default => '   ',
        };
        echo "<{$tag} style=\"color:{$color};margin:2px 0;font-family:monospace\">"
            . $prefix . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . "</{$tag}>" . PHP_EOL;
    }
}

function title(string $text): void
{
    if (IS_CLI) {
        echo PHP_EOL . "\033[1;36m══════════════════════════════════════════\033[0m" . PHP_EOL;
        out($text, 'title');
        echo "\033[1;36m══════════════════════════════════════════\033[0m" . PHP_EOL;
    } else {
        echo '<hr style="border-color:#334155"><h2 style="color:#a78bfa;font-family:monospace">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h2>' . PHP_EOL;
    }
}

// ─────────────────────────────────────────────────────────────
//  FUNCIONES PRINCIPALES
// ─────────────────────────────────────────────────────────────

/**
 * Crea un directorio de forma recursiva si no existe.
 */
function makeDir(string $relativePath): void
{
    $fullPath = PROJECT_ROOT . DIRECTORY_SEPARATOR . $relativePath;
    if (is_dir($fullPath)) {
        out($relativePath, 'skip');
        return;
    }
    if (mkdir($fullPath, 0755, true)) {
        out($relativePath, 'mkdir');
    } else {
        out("No se pudo crear: {$relativePath}", 'error');
    }
}

/**
 * Crea un archivo PHP con su cabecera canónica (<?php + strict_types + namespace).
 * Si el archivo ya existe lo omite para no sobreescribir trabajo hecho.
 *
 * @param string $relativePath  Ruta relativa desde PROJECT_ROOT  (ej. "src/Domain/Booking/Entities/Appointment.php")
 * @param string $namespace     Namespace PSR-4 completo            (ej. "App\Domain\Booking\Entities")
 * @param string $type          'class' | 'interface' | 'script'
 * @param string $symbolName    Nombre de la clase/interfaz (vacío en scripts sueltos)
 */
function makePhpFile(
    string $relativePath,
    string $namespace,
    string $type       = 'class',
    string $symbolName = ''
): void {
    $fullPath = PROJECT_ROOT . DIRECTORY_SEPARATOR . $relativePath;

    if (file_exists($fullPath)) {
        out($relativePath, 'skip');
        return;
    }

    // Construir cabecera canónica
    $lines   = [];
    $lines[] = '<?php';
    $lines[] = 'declare(strict_types=1);';
    $lines[] = '';

    if ($namespace !== '') {
        $lines[] = "namespace {$namespace};";
        $lines[] = '';
    }

    if ($symbolName !== '') {
        $keyword = match ($type) {
            'interface' => 'interface',
            'abstract'  => 'abstract class',
            default     => 'class',
        };
        $lines[] = "{$keyword} {$symbolName}";
        $lines[] = '{';
        $lines[] = '    // TODO: implementar';
        $lines[] = '}';
    }

    $content = implode(PHP_EOL, $lines) . PHP_EOL;

    if (file_put_contents($fullPath, $content) !== false) {
        out($relativePath, 'ok');
    } else {
        out("No se pudo escribir: {$relativePath}", 'error');
    }
}

/**
 * Crea un archivo de texto / config vacío (no PHP).
 */
function makeEmptyFile(string $relativePath, string $stub = ''): void
{
    $fullPath = PROJECT_ROOT . DIRECTORY_SEPARATOR . $relativePath;
    if (file_exists($fullPath)) {
        out($relativePath, 'skip');
        return;
    }
    if (file_put_contents($fullPath, $stub) !== false) {
        out($relativePath, 'ok');
    } else {
        out("No se pudo escribir: {$relativePath}", 'error');
    }
}

// ─────────────────────────────────────────────────────────────
//  INICIO DE EJECUCIÓN
// ─────────────────────────────────────────────────────────────

if (!IS_CLI) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<title>Generador de Esqueleto — Carolina Mora</title>'
        . '<style>body{background:#0f172a;padding:20px}</style></head><body>' . PHP_EOL;
}

out('Ecosistema Digital — Estética Carolina Mora', 'title');
out('Iniciando generación del esqueleto de arquitectura limpia…');
out('Raíz del proyecto: ' . PROJECT_ROOT);

$startTime = microtime(true);

// ══════════════════════════════════════════════════════════════
//  SECCIÓN 1 — DIRECTORIOS RAÍZ Y DE SOPORTE
// ══════════════════════════════════════════════════════════════
title('1. Directorios raíz y de soporte del proyecto');

makeDir('logs');
makeDir('vendor');
makeDir('database');
makeDir('database/migrations');
makeDir('database/seeders');
makeDir('tests');
makeDir('tests/Unit');
makeDir('tests/Integration');
makeDir('tests/Unit/Domain');
makeDir('tests/Unit/Application');

// ══════════════════════════════════════════════════════════════
//  SECCIÓN 2 — DIRECTORIO PUBLIC (Raíz Web + PWA)
// ══════════════════════════════════════════════════════════════
title('2. Directorio public/ (única raíz accesible desde la web)');

makeDir('public');
makeDir('public/assets');
makeDir('public/assets/css');
makeDir('public/assets/js');
makeDir('public/assets/fonts');
makeDir('public/assets/images');
makeDir('public/assets/images/landing');
makeDir('public/assets/icons');
makeDir('public/storage');
makeDir('public/storage/vouchers');
makeDir('public/storage/pdfs');

// Archivos públicos de la PWA y Front Controller
makeEmptyFile('public/index.php',
    '<?php' . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL .
    '// Front Controller — Punto de entrada único de la API' . PHP_EOL
);
makeEmptyFile('public/.htaccess',
    "Options -Indexes\nRewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^(.*)$ index.php [QSA,L]\n"
);
makeEmptyFile('public/manifest.json',
    '{' . PHP_EOL .
    '  "name": "Carolina Mora Estética",' . PHP_EOL .
    '  "short_name": "CarolinaMora",' . PHP_EOL .
    '  "start_url": "/",' . PHP_EOL .
    '  "display": "standalone",' . PHP_EOL .
    '  "theme_color": "#1e1b4b",' . PHP_EOL .
    '  "background_color": "#0f172a",' . PHP_EOL .
    '  "icons": []' . PHP_EOL .
    '}' . PHP_EOL
);
makeEmptyFile('public/sw.js',
    "// Service Worker — Estrategia Offline-First (IndexedDB + Background Sync)\n" .
    "'use strict';\n\n" .
    "const CACHE_NAME = 'carolina-mora-v1';\n" .
    "const BROADCAST_CHANNEL = 'sync-channel';\n"
);
makeEmptyFile('public/offline.html', "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><title>Sin conexión</title></head><body><h1>Sin conexión</h1></body></html>\n");
makeEmptyFile('public/robots.txt', "User-agent: *\nDisallow: /api/\n");

// ══════════════════════════════════════════════════════════════
//  SECCIÓN 3 — ÁRBOL src/ (Arquitectura Limpia)
// ══════════════════════════════════════════════════════════════
title('3. src/ — Capa 1: DOMAIN (Lógica pura de negocio, sin SQL ni frameworks)');

// ── 3.1 Domain/Shared ────────────────────────────────────────
makeDir('src/Domain/Shared/ValueObjects');
makeDir('src/Domain/Shared/Exceptions');
makeDir('src/Domain/Shared/Events');

makePhpFile('src/Domain/Shared/ValueObjects/Money.php',           'App\Domain\Shared\ValueObjects',  'class',     'Money');
makePhpFile('src/Domain/Shared/ValueObjects/TimeRange.php',       'App\Domain\Shared\ValueObjects',  'class',     'TimeRange');
makePhpFile('src/Domain/Shared/ValueObjects/UuidVO.php',          'App\Domain\Shared\ValueObjects',  'class',     'UuidVO');
makePhpFile('src/Domain/Shared/Exceptions/DomainException.php',   'App\Domain\Shared\Exceptions',    'class',     'DomainException');
makePhpFile('src/Domain/Shared/Events/DomainEventInterface.php',  'App\Domain\Shared\Events',        'interface', 'DomainEventInterface');
makePhpFile('src/Domain/Shared/Events/EventDispatcher.php',       'App\Domain\Shared\Events',        'class',     'EventDispatcher');

// ── 3.2 Domain/IAM ───────────────────────────────────────────
makeDir('src/Domain/IAM/Entities');
makeDir('src/Domain/IAM/Repositories');
makeDir('src/Domain/IAM/ValueObjects');

makePhpFile('src/Domain/IAM/Entities/User.php',                        'App\Domain\IAM\Entities',      'class',     'User');
makePhpFile('src/Domain/IAM/Entities/Role.php',                        'App\Domain\IAM\Entities',      'class',     'Role');
makePhpFile('src/Domain/IAM/Entities/UserSession.php',                 'App\Domain\IAM\Entities',      'class',     'UserSession');
makePhpFile('src/Domain/IAM/Repositories/UserRepositoryInterface.php', 'App\Domain\IAM\Repositories',  'interface', 'UserRepositoryInterface');
makePhpFile('src/Domain/IAM/ValueObjects/HashedPassword.php',          'App\Domain\IAM\ValueObjects',  'class',     'HashedPassword');
makePhpFile('src/Domain/IAM/ValueObjects/Email.php',                   'App\Domain\IAM\ValueObjects',  'class',     'Email');

// ── 3.3 Domain/Catalog ───────────────────────────────────────
makeDir('src/Domain/Catalog/Entities');
makeDir('src/Domain/Catalog/Repositories');

makePhpFile('src/Domain/Catalog/Entities/Service.php',                       'App\Domain\Catalog\Entities',     'class',     'Service');
makePhpFile('src/Domain/Catalog/Entities/Promotion.php',                     'App\Domain\Catalog\Entities',     'class',     'Promotion');
makePhpFile('src/Domain/Catalog/Entities/Branch.php',                        'App\Domain\Catalog\Entities',     'class',     'Branch');
makePhpFile('src/Domain/Catalog/Repositories/ServiceRepositoryInterface.php','App\Domain\Catalog\Repositories', 'interface', 'ServiceRepositoryInterface');
makePhpFile('src/Domain/Catalog/Repositories/PromotionRepositoryInterface.php','App\Domain\Catalog\Repositories','interface','PromotionRepositoryInterface');
makePhpFile('src/Domain/Catalog/Repositories/BranchRepositoryInterface.php', 'App\Domain\Catalog\Repositories', 'interface', 'BranchRepositoryInterface');

// ── 3.4 Domain/Staffing ──────────────────────────────────────
makeDir('src/Domain/Staffing/Entities');
makeDir('src/Domain/Staffing/Repositories');

makePhpFile('src/Domain/Staffing/Entities/ProfessionalProfile.php',            'App\Domain\Staffing\Entities',     'class',     'ProfessionalProfile');
makePhpFile('src/Domain/Staffing/Entities/WorkSchedule.php',                   'App\Domain\Staffing\Entities',     'class',     'WorkSchedule');
makePhpFile('src/Domain/Staffing/Entities/ScheduleException.php',              'App\Domain\Staffing\Entities',     'class',     'ScheduleException');
makePhpFile('src/Domain/Staffing/Repositories/StaffingRepositoryInterface.php','App\Domain\Staffing\Repositories', 'interface', 'StaffingRepositoryInterface');

// ── 3.5 Domain/Booking ───────────────────────────────────────
makeDir('src/Domain/Booking/Entities');
makeDir('src/Domain/Booking/Repositories');
makeDir('src/Domain/Booking/Events');

makePhpFile('src/Domain/Booking/Entities/Appointment.php',                        'App\Domain\Booking\Entities',     'class',     'Appointment');
makePhpFile('src/Domain/Booking/Entities/ClientProfile.php',                      'App\Domain\Booking\Entities',     'class',     'ClientProfile');
makePhpFile('src/Domain/Booking/Repositories/AppointmentRepositoryInterface.php', 'App\Domain\Booking\Repositories', 'interface', 'AppointmentRepositoryInterface');
makePhpFile('src/Domain/Booking/Repositories/ClientProfileRepositoryInterface.php','App\Domain\Booking\Repositories','interface', 'ClientProfileRepositoryInterface');
makePhpFile('src/Domain/Booking/Events/AppointmentCreatedEvent.php',              'App\Domain\Booking\Events',       'class',     'AppointmentCreatedEvent');
makePhpFile('src/Domain/Booking/Events/AppointmentCancelledEvent.php',            'App\Domain\Booking\Events',       'class',     'AppointmentCancelledEvent');

// ── 3.7 Domain/Dashboard ─────────────────────────────────────
makeDir('src/Domain/Dashboard/Repositories');
makePhpFile('src/Domain/Dashboard/Repositories/DashboardMetricsRepositoryInterface.php', 'App\Domain\Dashboard\Repositories', 'interface', 'DashboardMetricsRepositoryInterface');

// ── 3.6 Domain/Billing ───────────────────────────────────────
makeDir('src/Domain/Billing/Entities');
makeDir('src/Domain/Billing/Repositories');

makePhpFile('src/Domain/Billing/Entities/Invoice.php',                       'App\Domain\Billing\Entities',     'class',     'Invoice');
makePhpFile('src/Domain/Billing/Entities/Payment.php',                       'App\Domain\Billing\Entities',     'class',     'Payment');
makePhpFile('src/Domain/Billing/Entities/CashRegisterSession.php',           'App\Domain\Billing\Entities',     'class',     'CashRegisterSession');
makePhpFile('src/Domain/Billing/Repositories/PaymentRepositoryInterface.php','App\Domain\Billing\Repositories', 'interface', 'PaymentRepositoryInterface');
makePhpFile('src/Domain/Billing/Repositories/InvoiceRepositoryInterface.php','App\Domain\Billing\Repositories', 'interface', 'InvoiceRepositoryInterface');

// ══════════════════════════════════════════════════════════════
title('4. src/ — Capa 2: APPLICATION (Casos de uso / Orquestadores)');

// ── 4.1 Application/Shared ───────────────────────────────────
makeDir('src/Application/Shared/Validators');
makeDir('src/Application/Shared/Contracts');

makePhpFile('src/Application/Shared/Validators/InputSanitizer.php',          'App\Application\Shared\Validators', 'class',     'InputSanitizer');
makePhpFile('src/Application/Shared/Validators/ValidatorInterface.php',      'App\Application\Shared\Validators', 'interface', 'ValidatorInterface');
makePhpFile('src/Application/Shared/Contracts/UseCaseInterface.php',         'App\Application\Shared\Contracts',  'interface', 'UseCaseInterface');

// ── 4.2 Application/IAM ──────────────────────────────────────
makeDir('src/Application/IAM/RegisterClient');
makeDir('src/Application/IAM/Authenticate');
makeDir('src/Application/IAM/ManageRoles');

makePhpFile('src/Application/IAM/RegisterClient/RegisterNewClientUseCase.php','App\Application\IAM\RegisterClient', 'class',  'RegisterNewClientUseCase');
makePhpFile('src/Application/IAM/RegisterClient/RegisterClientDTO.php',       'App\Application\IAM\RegisterClient', 'class',  'RegisterClientDTO');
makePhpFile('src/Application/IAM/Authenticate/AuthenticateUserUseCase.php',   'App\Application\IAM\Authenticate',   'class',  'AuthenticateUserUseCase');
makePhpFile('src/Application/IAM/Authenticate/AuthenticateDTO.php',           'App\Application\IAM\Authenticate',   'class',  'AuthenticateDTO');
makePhpFile('src/Application/IAM/ManageRoles/AssignRoleUseCase.php',          'App\Application\IAM\ManageRoles',    'class',  'AssignRoleUseCase');

// ── 4.3 Application/Catalog ──────────────────────────────────
makeDir('src/Application/Catalog/BrowseCatalog');
makeDir('src/Application/Catalog/ManageServices');
makeDir('src/Application/Catalog/ManagePromotions');

makePhpFile('src/Application/Catalog/BrowseCatalog/BrowseServiceCatalogUseCase.php', 'App\Application\Catalog\BrowseCatalog',   'class', 'BrowseServiceCatalogUseCase');
makePhpFile('src/Application/Catalog/ManageServices/ManageServiceInventoryUseCase.php','App\Application\Catalog\ManageServices', 'class', 'ManageServiceInventoryUseCase');
makePhpFile('src/Application/Catalog/ManageServices/CreateServiceDTO.php',            'App\Application\Catalog\ManageServices', 'class', 'CreateServiceDTO');
makePhpFile('src/Application/Catalog/ManagePromotions/CreatePromotionUseCase.php',    'App\Application\Catalog\ManagePromotions','class', 'CreatePromotionUseCase');
makePhpFile('src/Application/Catalog/ManagePromotions/CreatePromotionDTO.php',        'App\Application\Catalog\ManagePromotions','class', 'CreatePromotionDTO');

// ── 4.4 Application/Staffing ─────────────────────────────────
makeDir('src/Application/Staffing/GetAvailableSlots');
makeDir('src/Application/Staffing/SaveSchedule');
makeDir('src/Application/Staffing/ManageExceptions');

makePhpFile('src/Application/Staffing/GetAvailableSlots/GetAvailableSlotsUseCase.php','App\Application\Staffing\GetAvailableSlots', 'class', 'GetAvailableSlotsUseCase');
makePhpFile('src/Application/Staffing/GetAvailableSlots/GetSlotsDTO.php',             'App\Application\Staffing\GetAvailableSlots', 'class', 'GetSlotsDTO');
makePhpFile('src/Application/Staffing/SaveSchedule/ConfigureScheduleUseCase.php',     'App\Application\Staffing\SaveSchedule',      'class', 'ConfigureScheduleUseCase');
makePhpFile('src/Application/Staffing/SaveSchedule/ConfigureScheduleDTO.php',         'App\Application\Staffing\SaveSchedule',      'class', 'ConfigureScheduleDTO');
makePhpFile('src/Application/Staffing/ManageExceptions/CreateScheduleExceptionUseCase.php','App\Application\Staffing\ManageExceptions','class','CreateScheduleExceptionUseCase');

// ── 4.5 Application/Booking ──────────────────────────────────
makeDir('src/Application/Booking/CreateBooking');
makeDir('src/Application/Booking/CancelBooking');
makeDir('src/Application/Booking/Operation');
makeDir('src/Application/Booking/Validators');

makePhpFile('src/Application/Booking/CreateBooking/CreateAppointmentUseCase.php', 'App\Application\Booking\CreateBooking', 'class', 'CreateAppointmentUseCase');
makePhpFile('src/Application/Booking/CreateBooking/CreateAppointmentDTO.php',     'App\Application\Booking\CreateBooking', 'class', 'CreateAppointmentDTO');
makePhpFile('src/Application/Booking/CancelBooking/CancelAppointmentUseCase.php', 'App\Application\Booking\CancelBooking', 'class', 'CancelAppointmentUseCase');
makePhpFile('src/Application/Booking/CancelBooking/CancelAppointmentDTO.php',     'App\Application\Booking\CancelBooking', 'class', 'CancelAppointmentDTO');
makePhpFile('src/Application/Booking/Operation/ExecuteCheckInUseCase.php',        'App\Application\Booking\Operation',    'class', 'ExecuteCheckInUseCase');
makePhpFile('src/Application/Booking/Operation/CompleteServiceUseCase.php',       'App\Application\Booking\Operation',    'class', 'CompleteServiceUseCase');
makePhpFile('src/Application/Booking/Operation/MarkNoShowUseCase.php',            'App\Application\Booking\Operation',    'class', 'MarkNoShowUseCase');
makePhpFile('src/Application/Booking/Validators/CreateAppointmentValidator.php',  'App\Application\Booking\Validators',   'class', 'CreateAppointmentValidator');

// ── 4.6 Application/Billing ──────────────────────────────────
makeDir('src/Application/Billing/ProcessWebhook');
makeDir('src/Application/Billing/PosPayment');
makeDir('src/Application/Billing/CashRegister');

makePhpFile('src/Application/Billing/ProcessWebhook/ProcessOnlinePaymentWebhookUseCase.php','App\Application\Billing\ProcessWebhook', 'class', 'ProcessOnlinePaymentWebhookUseCase');
makePhpFile('src/Application/Billing/ProcessWebhook/PaymentWebhookDTO.php',                 'App\Application\Billing\ProcessWebhook', 'class', 'PaymentWebhookDTO');
makePhpFile('src/Application/Billing/PosPayment/RegisterPosPaymentUseCase.php',             'App\Application\Billing\PosPayment',     'class', 'RegisterPosPaymentUseCase');
makePhpFile('src/Application/Billing/PosPayment/RegisterPosPaymentDTO.php',                 'App\Application\Billing\PosPayment',     'class', 'RegisterPosPaymentDTO');
makePhpFile('src/Application/Billing/CashRegister/OpenCashRegisterUseCase.php',             'App\Application\Billing\CashRegister',   'class', 'OpenCashRegisterUseCase');
makePhpFile('src/Application/Billing/CashRegister/CloseCashRegisterUseCase.php',            'App\Application\Billing\CashRegister',   'class', 'CloseCashRegisterUseCase');

// ── 4.7 Application/Dashboard ────────────────────────────────
makeDir('src/Application/Dashboard/GetMetrics');
makePhpFile('src/Application/Dashboard/GetMetrics/GetDashboardMetricsUseCase.php', 'App\Application\Dashboard\GetMetrics', 'class', 'GetDashboardMetricsUseCase');

// ══════════════════════════════════════════════════════════════
title('5. src/ — Capa 3: INFRASTRUCTURE (Adaptadores tecnológicos)');

// ── 5.1 Infrastructure/Shared ────────────────────────────────
makeDir('src/Infrastructure/Shared/Database');
makeDir('src/Infrastructure/Shared/Security');
makeDir('src/Infrastructure/Shared/Errors');
makeDir('src/Infrastructure/Shared/Routing');
makeDir('src/Infrastructure/Shared/Helpers');
makeDir('src/Infrastructure/Shared/Audit');
makeDir('src/Infrastructure/Shared/Logging');
makeDir('src/Infrastructure/Shared/Views');

makePhpFile('src/Infrastructure/Shared/Database/ConnectionFactory.php',     'App\Infrastructure\Shared\Database',  'class', 'ConnectionFactory');
makePhpFile('src/Infrastructure/Shared/Security/JwtTokenManager.php',       'App\Infrastructure\Shared\Security',  'class', 'JwtTokenManager');
makePhpFile('src/Infrastructure/Shared/Security/EncryptionService.php',     'App\Infrastructure\Shared\Security',  'class', 'EncryptionService');
makePhpFile('src/Infrastructure/Shared/Errors/GlobalExceptionHandler.php',  'App\Infrastructure\Shared\Errors',    'class', 'GlobalExceptionHandler');
makePhpFile('src/Infrastructure/Shared/Routing/Router.php',                 'App\Infrastructure\Shared\Routing',   'class', 'Router');
makePhpFile('src/Infrastructure/Shared/Helpers/DateTimeHelper.php',         'App\Infrastructure\Shared\Helpers',   'class', 'DateTimeHelper');
makePhpFile('src/Infrastructure/Shared/Helpers/ResponseHelper.php',         'App\Infrastructure\Shared\Helpers',   'class', 'ResponseHelper');
makePhpFile('src/Infrastructure/Shared/Audit/AuditLogger.php',              'App\Infrastructure\Shared\Audit',     'class', 'AuditLogger');
makePhpFile('src/Infrastructure/Shared/Audit/SystemAuditLogRepository.php', 'App\Infrastructure\Shared\Audit',     'class', 'SystemAuditLogRepository');
makePhpFile('src/Infrastructure/Shared/Logging/AppLogger.php',              'App\Infrastructure\Shared\Logging',   'class', 'AppLogger');

// Archivo de rutas (no es una clase, es un script de configuración)
makePhpFile('src/Infrastructure/Shared/Routing/routes.php', 'App\Infrastructure\Shared\Routing');

// ── 5.2 Infrastructure/IAM ───────────────────────────────────
makeDir('src/Infrastructure/IAM/Persistence');
makeDir('src/Infrastructure/IAM/Http');

makePhpFile('src/Infrastructure/IAM/Persistence/PdoUserRepository.php',    'App\Infrastructure\IAM\Persistence', 'class', 'PdoUserRepository');
makePhpFile('src/Infrastructure/IAM/Http/LoginController.php',             'App\Infrastructure\IAM\Http',        'class', 'LoginController');
makePhpFile('src/Infrastructure/IAM/Http/RegisterController.php',          'App\Infrastructure\IAM\Http',        'class', 'RegisterController');
makePhpFile('src/Infrastructure/IAM/Http/LogoutController.php',            'App\Infrastructure\IAM\Http',        'class', 'LogoutController');
makePhpFile('src/Infrastructure/IAM/Http/MeController.php',                'App\Infrastructure\IAM\Http',        'class', 'MeController');

// ── 5.3 Infrastructure/Catalog ───────────────────────────────
makeDir('src/Infrastructure/Catalog/Persistence');
makeDir('src/Infrastructure/Catalog/Http');

makePhpFile('src/Infrastructure/Catalog/Persistence/PdoServiceRepository.php',   'App\Infrastructure\Catalog\Persistence', 'class', 'PdoServiceRepository');
makePhpFile('src/Infrastructure/Catalog/Persistence/PdoPromotionRepository.php', 'App\Infrastructure\Catalog\Persistence', 'class', 'PdoPromotionRepository');
makePhpFile('src/Infrastructure/Catalog/Persistence/PdoBranchRepository.php',    'App\Infrastructure\Catalog\Persistence', 'class', 'PdoBranchRepository');
makePhpFile('src/Infrastructure/Catalog/Persistence/PdoCategoryRepository.php',  'App\Infrastructure\Catalog\Persistence', 'class', 'PdoCategoryRepository');
makePhpFile('src/Infrastructure/Catalog/Http/BrowseCatalogController.php',       'App\Infrastructure\Catalog\Http',        'class', 'BrowseCatalogController');
makePhpFile('src/Infrastructure/Catalog/Http/CreateServiceController.php',       'App\Infrastructure\Catalog\Http',        'class', 'CreateServiceController');
makePhpFile('src/Infrastructure/Catalog/Http/UpdateServiceController.php',       'App\Infrastructure\Catalog\Http',        'class', 'UpdateServiceController');
makePhpFile('src/Infrastructure/Catalog/Http/CreatePromotionController.php',     'App\Infrastructure\Catalog\Http',        'class', 'CreatePromotionController');

// ── 5.4 Infrastructure/Staffing ──────────────────────────────
makeDir('src/Infrastructure/Staffing/Persistence');
makeDir('src/Infrastructure/Staffing/Http');

makePhpFile('src/Infrastructure/Staffing/Persistence/PdoStaffingRepository.php',  'App\Infrastructure\Staffing\Persistence', 'class', 'PdoStaffingRepository');
makePhpFile('src/Infrastructure/Staffing/Http/GetSlotsController.php',            'App\Infrastructure\Staffing\Http',        'class', 'GetSlotsController');
makePhpFile('src/Infrastructure/Staffing/Http/SaveScheduleController.php',        'App\Infrastructure\Staffing\Http',        'class', 'SaveScheduleController');
makePhpFile('src/Infrastructure/Staffing/Http/ScheduleExceptionController.php',   'App\Infrastructure\Staffing\Http',        'class', 'ScheduleExceptionController');

// ── 5.5 Infrastructure/Booking ───────────────────────────────
makeDir('src/Infrastructure/Booking/Persistence');
makeDir('src/Infrastructure/Booking/Http');

makePhpFile('src/Infrastructure/Booking/Persistence/PdoAppointmentRepository.php',   'App\Infrastructure\Booking\Persistence', 'class', 'PdoAppointmentRepository');
makePhpFile('src/Infrastructure/Booking/Persistence/PdoClientProfileRepository.php', 'App\Infrastructure\Booking\Persistence', 'class', 'PdoClientProfileRepository');
makePhpFile('src/Infrastructure/Booking/Http/CreateAppointmentController.php',       'App\Infrastructure\Booking\Http',        'class', 'CreateAppointmentController');
makePhpFile('src/Infrastructure/Booking/Http/CancelAppointmentController.php',       'App\Infrastructure\Booking\Http',        'class', 'CancelAppointmentController');
makePhpFile('src/Infrastructure/Booking/Http/OperationsController.php',              'App\Infrastructure\Booking\Http',        'class', 'OperationsController');
makePhpFile('src/Infrastructure/Booking/Http/GetAppointmentsController.php',         'App\Infrastructure\Booking\Http',        'class', 'GetAppointmentsController');

// ── 5.6 Infrastructure/Billing ───────────────────────────────
makeDir('src/Infrastructure/Billing/Persistence');
makeDir('src/Infrastructure/Billing/Http');

makePhpFile('src/Infrastructure/Billing/Persistence/PdoPaymentRepository.php',       'App\Infrastructure\Billing\Persistence', 'class', 'PdoPaymentRepository');
makePhpFile('src/Infrastructure/Billing/Persistence/PdoInvoiceRepository.php',       'App\Infrastructure\Billing\Persistence', 'class', 'PdoInvoiceRepository');
makePhpFile('src/Infrastructure/Billing/Http/PosPaymentController.php',              'App\Infrastructure\Billing\Http',        'class', 'PosPaymentController');
makePhpFile('src/Infrastructure/Billing/Http/PaymentWebhookController.php',          'App\Infrastructure\Billing\Http',        'class', 'PaymentWebhookController');
makePhpFile('src/Infrastructure/Billing/Http/GetInvoicesController.php',             'App\Infrastructure\Billing\Http',        'class', 'GetInvoicesController');
makePhpFile('src/Infrastructure/Billing/Http/CashRegisterController.php',            'App\Infrastructure\Billing\Http',        'class', 'CashRegisterController');

// ── 5.7 Infrastructure/Integration/WhatsApp ──────────────────
makeDir('src/Infrastructure/Integration/WhatsApp/Http');
makeDir('src/Infrastructure/Integration/WhatsApp/Workers');
makeDir('src/Infrastructure/Integration/WhatsApp/Persistence');
makeDir('src/Infrastructure/Integration/WhatsApp/Services');

makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Http/WhatsAppWebhookController.php',
    'App\Infrastructure\Integration\WhatsApp\Http',
    'class',
    'WhatsAppWebhookController'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Workers/NotificationQueueWorker.php',
    'App\Infrastructure\Integration\WhatsApp\Workers',
    'class',
    'NotificationQueueWorker'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaNotificationQueueRepository.php',
    'App\Infrastructure\Integration\WhatsApp\Persistence',
    'class',
    'PdoWaNotificationQueueRepository'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaMessageLogRepository.php',
    'App\Infrastructure\Integration\WhatsApp\Persistence',
    'class',
    'PdoWaMessageLogRepository'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Persistence/PdoWaChatSessionRepository.php',
    'App\Infrastructure\Integration\WhatsApp\Persistence',
    'class',
    'PdoWaChatSessionRepository'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Services/MetaApiClient.php',
    'App\Infrastructure\Integration\WhatsApp\Services',
    'class',
    'MetaApiClient'
);
makePhpFile(
    'src/Infrastructure/Integration/WhatsApp/Services/WebhookSignatureValidator.php',
    'App\Infrastructure\Integration\WhatsApp\Services',
    'class',
    'WebhookSignatureValidator'
);

// ── 5.8 Infrastructure/Dashboard ─────────────────────────────
makeDir('src/Infrastructure/Dashboard/Persistence');
makeDir('src/Infrastructure/Dashboard/Http');

makePhpFile('src/Infrastructure/Dashboard/Persistence/PdoDashboardMetricsRepository.php', 'App\Infrastructure\Dashboard\Persistence', 'class', 'PdoDashboardMetricsRepository');
makePhpFile('src/Infrastructure/Dashboard/Http/AdminMetricsController.php',               'App\Infrastructure\Dashboard\Http',        'class', 'AdminMetricsController');


// ══════════════════════════════════════════════════════════════
title('6. src/ — Archivo de arranque central (bootstrap.php)');

makePhpFile('src/bootstrap.php', 'App', 'script');

// ══════════════════════════════════════════════════════════════
title('7. Archivos de configuración del proyecto');

// composer.json (stub con autoload PSR-4 preconfigurado)
$composerStub = json_encode([
    'name'        => 'carolina-mora/estetica-ecosystem',
    'description' => 'Ecosistema Digital Omnicanal — Estética Carolina Mora',
    'type'        => 'project',
    'require'     => [
        'php' => '^8.2'
    ],
    'require-dev' => [
        'phpunit/phpunit' => '^11.0'
    ],
    'autoload'    => [
        'psr-4' => [
            'App\\' => 'src/'
        ]
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Tests\\' => 'tests/'
        ]
    ],
    'config' => [
        'optimize-autoloader' => true,
        'sort-packages'       => true
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

makeEmptyFile('composer.json', $composerStub);

// .env y .env.example
$envExample = <<<'ENV'
# ────────────────────────────────────────────────────────────
#  Entorno — Estética Carolina Mora
# ────────────────────────────────────────────────────────────
APP_ENV=local
APP_NAME="Carolina Mora Estética"
APP_URL=http://localhost/CarolinaMoraEstetica/public

# Base de Datos
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=carolina_mora_db
DB_USER=root
DB_PASS=

# JWT
JWT_SECRET=CHANGE_ME_TO_A_STRONG_RANDOM_SECRET
JWT_EXPIRATION_SECONDS=3600

# WhatsApp Meta Cloud API
WHATSAPP_APP_SECRET=CHANGE_ME
WHATSAPP_VERIFY_TOKEN=CHANGE_ME
WHATSAPP_PHONE_NUMBER_ID=CHANGE_ME
WHATSAPP_ACCESS_TOKEN=CHANGE_ME

# Pasarela de Pagos
PAYMENT_GATEWAY_SECRET=CHANGE_ME
PAYMENT_WEBHOOK_SECRET=CHANGE_ME

# Logs
LOG_LEVEL=debug
LOG_PATH=../logs/app.log
ENV;

makeEmptyFile('.env.example', $envExample);

// .gitignore
$gitignore = <<<'GIT'
/vendor/
/logs/*.log
/.env
/public/storage/pdfs/*.pdf
/public/storage/vouchers/*
*.lock
.DS_Store
Thumbs.db
GIT;
makeEmptyFile('.gitignore', $gitignore);

// phpunit.xml
$phpunit = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
XML;
makeEmptyFile('phpunit.xml', $phpunit);

// ══════════════════════════════════════════════════════════════
//  RESUMEN FINAL
// ══════════════════════════════════════════════════════════════
$elapsed = round(microtime(true) - $startTime, 4);

title('✅ Generación de esqueleto completada');
out("Tiempo total de ejecución: {$elapsed} segundos");
out('Próximos pasos:');
out('  1. Ejecuta: composer install  (para generar vendor/ y el autoloader PSR-4)');
out('  2. Copia .env.example → .env  y rellena las credenciales reales');
out('  3. Importa el DDL SQL en tu base de datos MySQL (XAMPP)');
out('  4. Inicia el desarrollo desde src/bootstrap.php y public/index.php');

if (!IS_CLI) {
    echo '</body></html>' . PHP_EOL;
}
