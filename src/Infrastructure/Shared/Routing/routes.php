<?php
declare(strict_types=1);

use App\Infrastructure\Shared\Routing\Router;
use App\Infrastructure\IAM\Http\LoginController;
use App\Infrastructure\IAM\Http\RegisterController;
use App\Infrastructure\IAM\Http\MeController;
use App\Infrastructure\IAM\Http\UpdateProfileController;
use App\Infrastructure\IAM\Http\LogoutController;
use App\Infrastructure\IAM\Http\UserController;
use App\Infrastructure\IAM\Http\ProfessionalServiceController;
use App\Infrastructure\Shared\Security\AuthMiddleware;
use App\Infrastructure\Shared\Security\ViewAuthMiddleware;
use App\Infrastructure\Booking\Http\GetAppointmentsController;
use App\Infrastructure\Booking\Http\CancelAppointmentController;
use App\Infrastructure\Booking\Http\CreateAppointmentController;
use App\Infrastructure\Booking\Http\OperationsController;
use App\Infrastructure\Dashboard\Http\AdminMetricsController;
use App\Infrastructure\Catalog\Http\BrowseCatalogController;
use App\Infrastructure\Catalog\Http\AdminServicesController;
use App\Infrastructure\Catalog\Http\CreateServiceController;
use App\Infrastructure\Catalog\Http\UpdateServiceController;
use App\Infrastructure\Catalog\Http\GetCategoriesController;
use App\Infrastructure\Catalog\Http\GetProfessionalsByServiceController;
use App\Infrastructure\Staffing\Http\GetProfessionalsController;
use App\Infrastructure\Staffing\Http\GetSlotsController;
use App\Infrastructure\Staffing\Http\SaveScheduleController;
use App\Infrastructure\Staffing\Http\ScheduleExceptionController;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

/** @var Router $router */

// --- RUTAS DE NAVEGACIÓN (VISTAS) ---

// Ruta raíz: Sirve la landing page
$router->get('/', function() {
    include __DIR__ . '/../Views/landing.php';
});

// Ruta de Login: Sirve el formulario de acceso
$router->get('/login', function() {
    include __DIR__ . '/../Views/login.php';
});

// Ruta de Registro: Sirve el formulario de creación de cuenta
$router->get('/register', function() {
    include __DIR__ . '/../Views/register.php';
});

// --- RUTAS DE ADMINISTRACIÓN (VISTAS) ---
$router->group('/admin', function(Router $r) {
    $r->get('/{view}', function($params) {
        $view = $params['view'] ?? 'dashboard';
        $basePath = realpath(__DIR__ . "/../../../../public/views/admin");
        $filePath = $basePath . DIRECTORY_SEPARATOR . "admin-{$view}.php";

        if (!$basePath || !file_exists($filePath)) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/view-not-found',
                title: 'Vista de Administración no encontrada',
                detail: "No se encuentra el archivo físico para la vista 'admin-{$view}'. Asegúrate de que exista en public/views/admin/",
                instance: $_SERVER['REQUEST_URI']
            );
        }
        include $filePath;
    });
}, [ViewAuthMiddleware::class]);

// --- RUTAS DE CLIENTES (VISTAS PWA) ---
$router->group('/app', function(Router $r) {
    $r->get('/{view}', function($params) {
        $view = $params['view'] ?? 'dashboard';
        $basePath = realpath(__DIR__ . "/../../../../public/views/app");
        $filePath = $basePath . DIRECTORY_SEPARATOR . "app-{$view}.php";

        if (!$basePath || !file_exists($filePath)) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 404,
                type: 'https://carolinamoraestetica.com/errors/view-not-found',
                title: 'Vista de Cliente no encontrada',
                detail: "No se encuentra el archivo físico para la vista 'app-{$view}'. Asegúrate de que el archivo existe en public/views/app/",
                instance: $_SERVER['REQUEST_URI']
            );
        }
        include $filePath;
    });
}, [ViewAuthMiddleware::class]);

// Rutas Públicas
$router->post('/api/v1/auth/login', [LoginController::class, 'handle']);
$router->post('/api/v1/auth/register', [RegisterController::class, 'handle']);

// --- RUTAS DE GESTIÓN DE USUARIOS (ADMIN IAM) ---
$router->group('/api/v1/iam', function(Router $r) {
    $r->get('/users', [UserController::class, 'handle']);
    $r->post('/users', [UserController::class, 'handle']);
    $r->put('/users/{id}', [UserController::class, 'handle']);
    $r->patch('/users/{id}', [UserController::class, 'handle']);
}, [AuthMiddleware::class]);

// --- RUTAS DE GESTIÓN DE CAPACIDADES TÉCNICAS (Staff & Skills) ---
$router->group('/api/v1/iam/professionals', function(Router $r) {
    $r->get('/{id}/services', [ProfessionalServiceController::class, 'handle']);
    $r->post('/{id}/services', [ProfessionalServiceController::class, 'handle']);
}, [AuthMiddleware::class]);

// --- RUTAS DE STAFFING (Profesionales y Disponibilidad) ---
$router->group('/api/v1/staffing', function(Router $r) {
    $r->get('/professionals', [GetProfessionalsController::class, 'handle']);
    $r->get('/professionals/{id}/schedules', [GetSlotsController::class, 'handle']);
    $r->post('/schedules', [SaveScheduleController::class, 'handle']);
    $r->post('/exceptions', [ScheduleExceptionController::class, 'handle']);
}, [AuthMiddleware::class]);

// Rutas Protegidas de Autenticación (perfil + logout)
$router->group('/api/v1/auth', function(Router $r) {
    $r->get('/me', [MeController::class, 'handle']);
    $r->put('/me/profile', [UpdateProfileController::class, 'handle']);
    $r->post('/logout', [LogoutController::class, 'handle']);
}, [AuthMiddleware::class]);

// Rutas Protegidas para Clientes (/app/*)
$router->group('/api/v1/app', function(Router $r) {
    $r->get('/appointments', [GetAppointmentsController::class, 'handle']);
}, [AuthMiddleware::class]);

// Rutas Protegidas para Booking (citas accesibles por admin y recepción)
$router->group('/api/v1/booking', function(Router $r) {
    $r->get('/appointments', [GetAppointmentsController::class, 'handle']);
    $r->post('/appointments', [CreateAppointmentController::class, 'handle']);
    $r->delete('/appointments/{id}', [CancelAppointmentController::class, 'handle']);
    // Rutas para operaciones de estado de citas (check-in, complete, cancel, noshow)
    $r->patch('/appointments/{id}/check-in', [OperationsController::class, 'checkIn']);
    $r->patch('/appointments/{id}/complete', [OperationsController::class, 'complete']);
    $r->patch('/appointments/{id}/cancel', [OperationsController::class, 'cancel']);
    $r->patch('/appointments/{id}/noshow', [OperationsController::class, 'noShow']);
}, [AuthMiddleware::class]);

// Rutas Protegidas para Administración (/admin/*)
// Metrics: público para desarrollo, proteger con AuthMiddleware cuando login esté completo
$router->get('/api/v1/admin/metrics', [AdminMetricsController::class, 'handle']);

// Rutas del Catálogo — Listado público (PWA + Admin), mutaciones protegidas
$router->get('/api/v1/catalog/services', [BrowseCatalogController::class, 'handle']);
$router->get('/api/v1/admin/services', [AdminServicesController::class, 'getAll']);
$router->get('/api/v1/catalog/services/{id}', [BrowseCatalogController::class, 'show']);
$router->get('/api/v1/catalog/services/{id}/professionals', [GetProfessionalsByServiceController::class, 'handle']);

$router->group('/api/v1/catalog/services', function(Router $r) {
    $r->post('', [CreateServiceController::class, 'handle']);
    $r->put('/{id}', [UpdateServiceController::class, 'handle']);
    $r->patch('/{id}', [UpdateServiceController::class, 'handle']);
}, [AuthMiddleware::class]);

$router->get('/api/v1/catalog/categories', [GetCategoriesController::class, 'handle']);
