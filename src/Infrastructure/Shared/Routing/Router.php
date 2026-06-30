<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Routing;

use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;

/**
 * Router
 *
 * Motor de enrutamiento HTTP de la API REST.
 *
 * Responsabilidades:
 *   - Registrar rutas con método HTTP, patrón URI y handler (callable o [Clase, método]).
 *   - Soporte de segmentos dinámicos:  /api/appointments/{id}  /api/staff/{id}/slots
 *   - Despachar la petición entrante al controlador correcto inyectando los parámetros.
 *   - Emitir 404 (recurso no encontrado) y 405 (método no permitido) en RFC 7807.
 *   - Soporte de grupos de rutas con prefijo URI y middleware compartido.
 *
 * Cumple:
 *  - Skill 1  → strict_types, inyección por constructor, sin variables globales
 *  - Skill 4  → 404/405 formateados bajo RFC 7807 vía GlobalExceptionHandler
 *  - Skill 10 → La URI se sanea antes de la comparación para evitar path-traversal
 *
 * Uso mínimo en bootstrap.php:
 *   $router = new Router($exceptionHandler);
 *   $router->get('/api/services', [BrowseCatalogController::class, 'index']);
 *   $router->post('/api/appointments', [CreateAppointmentController::class, 'handle']);
 *   $router->dispatch();
 */
final class Router
{
    // ─────────────────────────────────────────────────────────
    //  TIPOS INTERNOS
    // ─────────────────────────────────────────────────────────

    /**
     * Estructura de una ruta registrada.
     *
     * @phpstan-type RouteDefinition array{
     *   method:      string,
     *   pattern:     string,
     *   regex:       string,
     *   paramNames:  list<string>,
     *   handler:     callable|array{class-string, string},
     *   middlewares: list<callable>
     * }
     */

    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    /** Prefijo URI activo durante un grupo de rutas (vacío si no hay grupo). */
    private string $groupPrefix = '';

    /** Middlewares activos durante un grupo de rutas. */
    /** @var list<callable> */
    private array $groupMiddlewares = [];

    /**
     * Contenedor de servicios: se usa para instanciar controladores bajo demanda
     * sin acoplar el Router a clases concretas.
     * Firma: fn(class-string): object
     *
     * @var callable(class-string): object
     */
    private $container;

    private GlobalExceptionHandler $exceptionHandler;

    // ─────────────────────────────────────────────────────────
    //  CONSTRUCTOR
    // ─────────────────────────────────────────────────────────

    /**
     * @param callable(class-string): object $container
     *   Closure que recibe un FQCN y devuelve la instancia ya resuelta
     *   con sus dependencias inyectadas desde bootstrap.php.
     */
    public function __construct(
        callable             $container,
        GlobalExceptionHandler $exceptionHandler
    ) {
        $this->container        = $container;
        $this->exceptionHandler = $exceptionHandler;
    }

    // ─────────────────────────────────────────────────────────
    //  MÉTODOS DE REGISTRO DE RUTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Registra una ruta GET.
     * @param string $uri Patrón URI con segmentos dinámicos {param}.
     * @param callable|array{0: class-string, 1: string} $handler Closure o [Clase, método].
     * @param list<callable|string> $middlewares Middlewares a aplicar.
     */
    public function get(string $uri, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $uri, $handler, $middlewares);
    }

    /**
     * Registra una ruta POST.
     * @param string $uri Patrón URI con segmentos dinámicos {param}.
     * @param callable|array{0: class-string, 1: string} $handler Closure o [Clase, método].
     * @param list<callable|string> $middlewares Middlewares a aplicar.
     */
    public function post(string $uri, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $uri, $handler, $middlewares);
    }

    /**
     * Registra una ruta PUT.
     * @param string $uri Patrón URI con segmentos dinámicos {param}.
     * @param callable|array{0: class-string, 1: string} $handler Closure o [Clase, método].
     * @param list<callable|string> $middlewares Middlewares a aplicar.
     */
    public function put(string $uri, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $uri, $handler, $middlewares);
    }

    /**
     * Registra una ruta PATCH.
     * @param string $uri Patrón URI con segmentos dinámicos {param}.
     * @param callable|array{0: class-string, 1: string} $handler Closure o [Clase, método].
     * @param list<callable|string> $middlewares Middlewares a aplicar.
     */
    public function patch(string $uri, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $uri, $handler, $middlewares);
    }

    /**
     * Registra una ruta DELETE.
     * @param string $uri Patrón URI con segmentos dinámicos {param}.
     * @param callable|array{0: class-string, 1: string} $handler Closure o [Clase, método].
     * @param list<callable|string> $middlewares Middlewares a aplicar.
     */
    public function delete(string $uri, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $uri, $handler, $middlewares);
    }

    // ─────────────────────────────────────────────────────────
    //  AGRUPAMIENTO DE RUTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Agrupa rutas bajo un prefijo URI y/o middlewares compartidos.
     *
     * @param string $prefix Prefijo URI para todas las rutas del grupo.
     * @param callable(Router): void $callback Callback que recibe el Router para registrar rutas.
     * @param list<callable|class-string> $middlewares Middlewares a aplicar (FQCN o closures).
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        // Guardar estado anterior para soportar grupos anidados.
        $previousPrefix      = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix      = $previousPrefix . $prefix;
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        // Restaurar estado.
        $this->groupPrefix      = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    // ─────────────────────────────────────────────────────────
    //  DESPACHO DE LA PETICIÓN
    // ─────────────────────────────────────────────────────────

    /**
     * Lee la petición HTTP actual, busca la ruta coincidente y la despacha.
     * Si no hay coincidencia emite 404 ó 405 bajo RFC 7807.
     *
     * Diseñado para llamarse UNA sola vez desde public/index.php.
     */
    public function dispatch(): void
    {
        $method = $this->resolveHttpMethod();
        $uri    = $this->resolveRequestUri();

        // ── 1. Buscar ruta coincidente ───────────────────────────────────
        $matchedRoutes = [];   // Rutas cuyo patrón URI coincide (sin importar método).

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            // Extraer solo los grupos con nombre (parámetros de ruta).
            $params = array_filter(
                $matches,
                static fn($key) => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            // URI coincide — registrar el método que acepta.
            $matchedRoutes[] = [
                'route'  => $route,
                'params' => $params,
            ];

            if ($route['method'] === $method || $route['method'] === 'ANY') {
                // ✅ Coincidencia exacta de URI + método → despachar.
                $this->runMiddlewaresAndHandler($route, $params);
                return;
            }
        }

        // ── 2. URI coincide pero método no → 405 Method Not Allowed ─────
        if ($matchedRoutes !== []) {
            $allowed = array_unique(
                array_map(static fn($m) => $m['route']['method'], $matchedRoutes)
            );
            header('Allow: ' . implode(', ', $allowed));

            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus : 405,
                type       : 'https://carolinamoraestetica.com/errors/method-not-allowed',
                title      : 'Método HTTP No Permitido',
                detail     : "El método '{$method}' no está soportado para esta ruta. "
                             . 'Métodos permitidos: ' . implode(', ', $allowed) . '.',
                instance   : $uri,
                extraData  : []
            );
        }

        // ── 3. URI no coincide con ninguna ruta → 404 Not Found ─────────
        GlobalExceptionHandler::emitRfc7807Response(
            httpStatus : 404,
            type       : 'https://carolinamoraestetica.com/errors/not-found',
            title      : 'Ruta No Encontrada',
            detail     : "La URI '{$uri}' no corresponde a ningún endpoint registrado en la API.",
            instance   : $uri,
            extraData  : []
        );
    }

    // ─────────────────────────────────────────────────────────
    //  REGISTRO INTERNO DE RUTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Convierte el patrón de URI en una expresión regular con grupos con nombre
     * para cada segmento dinámico {param}.
     *
     * @param list<callable> $middlewares
     */
    private function addRoute(
        string         $method,
        string         $uri,
        callable|array $handler,
        array          $middlewares = []
    ): self {
        $fullUri        = $this->groupPrefix . '/' . ltrim($uri, '/');
        $fullUri        = rtrim($fullUri, '/') ?: '/';
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        // Extraer nombres de parámetros: {id}, {serviceId}, etc.
        preg_match_all('/\{(\w+)\}/', $fullUri, $paramMatches);
        $paramNames = $paramMatches[1];

        // Convertir patrón a regex:
        //   {id}        → (?P<id>[^/]+)
        //   {uuid}      → (?P<uuid>[^/]+)
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $fullUri);
        $regex = '@^' . $regex . '$@';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $fullUri,
            'regex'       => $regex,
            'paramNames'  => $paramNames,
            'handler'     => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    // ─────────────────────────────────────────────────────────
    //  DESPACHO CON PIPELINE DE MIDDLEWARES
    // ─────────────────────────────────────────────────────────

    /**
     * Ejecuta la cadena de middlewares (si los hay) y luego el handler final.
     *
     * @param array<string, mixed> $route
     * @param array<string, string> $params
     */
    private function runMiddlewaresAndHandler(array $route, array $params): void
    {
        $handler     = $route['handler'];
        $middlewares = $route['middlewares'];

        // Pipeline de ejecución: Cada eslabón puede modificar o aumentar los $params
        $next = function (array $currentParams) use ($handler): void {
            $this->invokeHandler($handler, $currentParams);
        };

        // Envolver en orden inverso para que el primer middleware sea el más externo.
        foreach (array_reverse($middlewares) as $middleware) {
            $innerNext = $next;
            $next = function (array $currentParams) use ($middleware, $innerNext): void {
                // Resolver instancia si es un nombre de clase
                $instance = is_string($middleware) ? ($this->container)($middleware) : $middleware;
                $instance($currentParams, $innerNext);
            };
        }

        $next($params);
    }

    /**
     * Resuelve e invoca el handler final.
     * Soporta dos formatos:
     *   - Callable closure: fn(array $params): void
     *   - Array [ClassName::class, 'methodName']  →  instanciado vía container
     *
     * @param callable|array{class-string, string} $handler
     * @param array<string, string>                $params
     */
    private function invokeHandler(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($params);
            return;
        }

        [$class, $method] = $handler;

        // Resolver la instancia del controlador desde el contenedor del bootstrap.
        $controllerInstance = ($this->container)($class);

        if (!method_exists($controllerInstance, $method)) {
            throw new \BadMethodCallException(
                "El controlador '{$class}' no tiene el método '{$method}'."
            );
        }

        $controllerInstance->$method($params);
    }

    // ─────────────────────────────────────────────────────────
    //  RESOLVERS DE LA PETICIÓN HTTP
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene y normaliza el método HTTP real.
     * Soporta el override X-HTTP-Method-Override para clientes que no
     * pueden enviar PUT/PATCH/DELETE nativamente (p.ej. algunos proxies).
     */
    private function resolveHttpMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Permitir override de método (Skill 10: Robustez Perimetral)
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
                 ?? $_POST['_method']
                 ?? '';
        if ($override !== '') {
            $method = strtoupper($override);
        }

        // Validar que el método sea uno de los verbos HTTP estándar.
        $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        if (!in_array($method, $allowed, true)) {
            $method = 'GET';
        }

        // Responder a OPTIONS (preflight CORS) directamente.
        if ($method === 'OPTIONS') {
            $this->emitCorsPreflightHeaders();
            exit;
        }

        return $method;
    }

    /**
     * Obtiene y sanea la URI de la petición:
     *   - Elimina la query string.
     *   - Normaliza slashes múltiples.
     *   - Bloquea path-traversal (/../).
     */
    private function resolveRequestUri(): string
    {
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Separar path de query string.
        $path = (string) parse_url($rawUri, PHP_URL_PATH);

        // Normalizar: decodificar, colapsar slashes, eliminar traversal.
        $path = rawurldecode($path);
        $path = preg_replace('@/+@', '/', $path)     ?? '/';
        $path = str_replace(['../', '..\\'], '', $path);

        // Autodetectar y eliminar el subdirectorio de instalación si existe.
        // SCRIPT_NAME puede ser: /CarolinaMoraEstetica/public/index.php
        // Necesitamos strippear el project root (/CarolinaMoraEstetica),
        // no el directorio public completo, porque las API URLs son
        // /CarolinaMoraEstetica/api/v1/... (sin /public/).
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        // Calcular baseDir como el directorio del proyecto (2 niveles arriba de index.php)
        // /CarolinaMoraEstetica/public/index.php → /CarolinaMoraEstetica
        $projectRoot = rtrim(dirname(dirname($scriptName)), '/\\');
        // Fallback: si SCRIPT_NAME es solo /index.php (sin subdirectorios), no strippear nada.
        if ($projectRoot === '/' || $projectRoot === '') {
            $projectRoot = '';
        }

        if ($projectRoot !== '' && str_starts_with($path, $projectRoot)) {
            $path = substr($path, strlen($projectRoot));
        }

        // Strip "/public" si la URI residual comienza con él.
        // Esto permite acceso directo via /CarolinaMoraEstetica/public/
        // y lo normaliza a "/" para que coincida con la ruta raíz.
        if (str_starts_with($path, '/public')) {
            $path = substr($path, strlen('/public'));
        }

        $path = rtrim($path, '/') ?: '/';

        return $path;
    }

    /**
     * Emite los headers necesarios para responder al preflight CORS (OPTIONS).
     * La lista de orígenes permitidos se lee de la variable de entorno CORS_ALLOW_ORIGINS.
     */
    private function emitCorsPreflightHeaders(): void
    {
        $allowedOrigins = $_ENV['CORS_ALLOW_ORIGINS'] ?? 'http://localhost';
        header("Access-Control-Allow-Origin: {$allowedOrigins}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
    }
}
