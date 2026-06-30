<?php
declare(strict_types=1);

/**
 * ============================================================
 *  src/bootstrap.php — Kernel de Arranque del Sistema
 *  Ecosistema Digital — Estética Carolina Mora
 * ============================================================
 *
 *  Responsabilidad única de este archivo:
 *    Preparar el entorno de ejecución PHP para que el Front
 *    Controller (public/index.php) pueda arrancar de forma
 *    segura y predecible en cualquier entorno
 *    (XAMPP local / Hostinger producción).
 *
 *  Orden de arranque garantizado y secuencial:
 *    1. Validar y cargar el Autoloader PSR-4 de Composer
 *    2. Parsear y registrar variables del archivo .env
 *    3. Registrar el GlobalExceptionHandler (perímetro de error)
 *    4. Crear y devolver la conexión PDO configurada
 *
 *  CONTRATO de retorno:
 *    Este archivo devuelve un array asociativo con las
 *    dependencias fundacionales resueltas, listo para ser
 *    consumido por bootstrap.php (raíz) sin variables globales.
 *
 *    [
 *      'pdo'          => PDO,
 *      'isProduction' => bool,
 *      'logFilePath'  => string,
 *    ]
 *
 *  Uso desde bootstrap.php (raíz):
 *    $kernel = require __DIR__ . '/src/bootstrap.php';
 *    $pdo    = $kernel['pdo'];
 *
 *  Restricciones de arquitectura (Skill 1):
 *    - CERO variables globales expuestas al scope padre.
 *    - Todas las dependencias se devuelven como valores.
 *    - Nunca instancia clases de Domain (solo Infrastructure).
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
//  PASO 1 — AUTOLOADER PSR-4 (Composer)
// ─────────────────────────────────────────────────────────────
//
//  Ruta desde src/ hacia la raíz del proyecto: __DIR__/.. = raíz
//  Estructura esperada:
//    raíz/
//    ├── vendor/autoload.php   ← generado por `composer install`
//    └── src/bootstrap.php     ← este archivo

$autoloaderPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloaderPath)) {
    // Fallo crítico pre-autoloader: el GlobalExceptionHandler aún no
    // está disponible. Único punto donde se permite un echo directo.
    http_response_code(500);
    header('Content-Type: application/problem+json; charset=utf-8');
    echo json_encode([
        'type'     => 'https://carolinamoraestetica.com/errors/bootstrap-failure',
        'title'    => 'Fallo de Inicialización — Autoloader no encontrado',
        'status'   => 500,
        'detail'   => 'El autoloader de Composer no fue encontrado en vendor/autoload.php. '
                    . 'Ejecuta: composer install',
        'instance' => '/bootstrap',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(1);
}

require_once $autoloaderPath;


// ─────────────────────────────────────────────────────────────
//  PASO 2 — PARSER DE VARIABLES DE ENTORNO (.env)
// ─────────────────────────────────────────────────────────────
//
//  Implementación propia sin dependencias externas.
//  Prioridad de lectura (mayor a menor):
//    a) Variables ya definidas en el servidor (VHost de Hostinger)
//    b) Variables del archivo .env
//
//  Características del parser:
//    ✓ Soporta comentarios inline  (CLAVE=valor # comentario)
//    ✓ Soporta comillas dobles con escapes (\n \t \" \\)
//    ✓ Soporta comillas simples sin escapes (literal puro)
//    ✓ Soporta prefijo 'export ' (compatibilidad con bash)
//    ✓ NO sobreescribe variables ya definidas en getenv()
//    ✗ No soporta variables multilínea (innecesario para este proyecto)

(static function (): void {
    // La ruta al .env es relativa a la raíz del proyecto (src/../.env)
    $envFilePath = __DIR__ . '/../.env';

    if (!file_exists($envFilePath)) {
        // En producción Hostinger las variables se inyectan por el servidor.
        // En local, si no existe .env se debe copiar desde .env.example.
        return;
    }

    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $raw) {
        $line = trim($raw);

        // Ignorar líneas de comentario puro y líneas vacías.
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Eliminar prefijo 'export ' (compatibilidad con scripts de shell).
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        // Separar en KEY=VALUE en la primera ocurrencia de '='.
        $eqPos = strpos($line, '=');
        if ($eqPos === false || $eqPos === 0) {
            continue; // Línea sin valor o sin clave: ignorar.
        }

        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        // ── Procesado del valor ────────────────────────────
        // Eliminar comentarios inline solo si el valor NO está entre comillas.
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $commentPos = strpos($value, ' #');
            if ($commentPos !== false) {
                $value = rtrim(substr($value, 0, $commentPos));
            }
        }

        // Comillas dobles: procesar secuencias de escape estándar.
        if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
            $value = str_replace(
                ['\\n',  '\\t', '\\"', '\\\\'],
                ["\n",   "\t",  '"',   '\\'],
                $value
            );
        }

        // Comillas simples: literal puro, sin procesado de escapes.
        if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
        }

        // Registrar SOLO si la clave es válida (solo letras, números, guiones bajos)
        // y NO está ya definida en el entorno del servidor.
        if ($key !== '' && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key) && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
})();


// ─────────────────────────────────────────────────────────────
//  PASO 3 — REGISTRO DEL MANEJADOR GLOBAL DE EXCEPCIONES
// ─────────────────────────────────────────────────────────────
//
//  Se registra INMEDIATAMENTE después del autoloader para que
//  cualquier excepción no capturada en los pasos siguientes
//  (PDO, instanciación de servicios, etc.) sea interceptada
//  y devuelta como RFC 7807 — nunca como HTML de error de PHP.
//
//  Comportamiento según APP_ENV:
//    local/development → Incluye stack trace completo en la respuesta JSON
//    production        → Devuelve mensaje seguro genérico al cliente
//                        y escribe el stack trace real en el log del servidor

use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

$isProduction = (($_ENV['APP_ENV'] ?? 'local') === 'production');

// La ruta de log es absoluta desde la raíz del proyecto.
$logFilePath = __DIR__ . '/../logs/app.log';

GlobalExceptionHandler::register(
    isProduction: $isProduction,
    logFilePath:  $logFilePath
);


// ─────────────────────────────────────────────────────────────
//  PASO 4 — CONEXIÓN PDO FUNDACIONAL
// ─────────────────────────────────────────────────────────────
//
//  ConnectionFactory.createFromEnv() lee las variables ya
//  registradas en $_ENV por el parser del Paso 2.
//  Si faltan variables o la conexión falla, lanza RuntimeException
//  que será capturada por el GlobalExceptionHandler del Paso 3
//  y devuelta como RFC 7807 HTTP 500 — nunca como error PHP raw.

use App\Infrastructure\Shared\Database\ConnectionFactory;

$pdo = ConnectionFactory::createFromEnv();


// ─────────────────────────────────────────────────────────────
//  RETORNO DEL KERNEL
// ─────────────────────────────────────────────────────────────
//
//  Devolver un array inmutable con las dependencias fundacionales.
//  El bootstrap.php (raíz) desestructura este array para
//  construir el árbol de DI sin acceder a variables globales.

return [
    'pdo'          => $pdo,
    'isProduction' => $isProduction,
    'logFilePath'  => $logFilePath,
];
