Este manual técnico dicta las especificaciones imperativas de diseño e implementación para el perímetro criptográfico, la gestión de sesiones y el control de acceso en PHP Puro (v8.x+) para Antigravity 2.0. El documento unifica las directivas de seguridad OWASP y el modelo RBAC, garantizando protección de nivel empresarial con soporte omnicanal simultáneo (PWA, App y Bot de WhatsApp).

1. Criptografía de Tokens: Arquitectura JWT Dual (Access & Refresh Tokens)
Para mitigar riesgos de secuestro de sesión sin sobrecargar el servidor de base de datos compartida en Hostinger con lecturas continuas, el sistema implementará un esquema de Tokens Duales independientes.

1.1. Especificación de Tiempos y Parámetros Criptográficos
Algoritmo de Firma: Estrictamente HS256 (HMAC usando SHA-256) empleando una llave maestra de alta entropía (JWT_SECRET) de mínimo 64 caracteres alfanuméricos guardada en las variables de entorno locales (.env).

Access Token (Token de Acceso):

Tiempo de Vida (TTL): Exactamente 15 minutos (exp = time() + 900).

Naturaleza: Inmutable y sin estado (Stateless). Almacenado únicamente en la memoria RAM del frontend (PWA/App) para evitar vulnerabilidades XSS.

Refresh Token (Token de Refresco):

Tiempo de Vida (TTL): Exactamente 7 días (exp = time() + 604800).

Naturaleza: Con estado (Stateful). Almacenado en el navegador del cliente mediante una Cookie HTTP-Only.

1.2. Anatomía de los Payloads (Claims Estructurados)
Payload del Access Token
JSON
{
  "iss": "https://api.esteticacarolinamora.com",
  "sub": "34",
  "iat": 1780754400,
  "exp": 1780755300,
  "user_id": 34,
  "role": "CLIENT",
  "branch_id": null
}
Payload del Refresh Token
JSON
{
  "iss": "https://api.esteticacarolinamora.com",
  "sub": "34",
  "jti": "a5b8e92c-74f1-4d1a-8b9a-7c3e2f5b8d1a",
  "iat": 1780754400,
  "exp": 1781359200
}
El Claim jti (JWT ID): Es un identificador único criptográfico (UUIDv4) para el Refresh Token, indispensable para la invalidación atómica de sesiones en el backend.

2. Gestión e Invalidation de Sesiones Activas en Base de Datos
Para que el backend mantenga la capacidad de revocar de inmediato una sesión (por ejemplo, si el cliente cierra sesión o si el Administrador bloquea un usuario), los Refresh Tokens deben registrarse en la infraestructura persistente de MySQL 8 utilizando la tabla relacional preexistente user_session.

2.1. Estructura Estricta de Persistencia SQL de Sesiones
La tabla user_session mapea directamente el ciclo de vida del Refresh Token:

token_jti: Llave primaria (VARCHAR/UUID) que almacena el claim jti.

user_id: Conecta con el registro maestro del usuario (ON DELETE CASCADE).

is_revoked: Booleano / Diminuto entero (TINYINT(1)) indexado para identificar de forma atómica si la sesión fue anulada perimetralmente.

expires_at: Timestamp de control para depuración automatizada de sesiones caducadas.

2.2. Flujo de Rotación de Tokens (Mecanismo Anti-Replay)
Cuando el Access Token expira tras los 15 minutos, el cliente consume de forma automática el endpoint público /api/v1/auth/refresh enviando el Refresh Token. El controlador PHP ejecuta la siguiente lógica atómica bajo sentencias preparadas PDO:

Plaintext
[Cliente PWA/App] ----(Envía Cookie con Refresh Token JTI)----> [Endpoint /refresh]
                                                                        |
                                                  +---------------------+---------------------+
                                                  |                                           |
                                       ¿JTI existe en BD y es válido?           ¿JTI está marcado como REVOKED?
                                                  |                                           |
                                                  v                                           v
                                        Genera nuevo par de                    ¡ALERTA DE ROBO DE TOKEN!
                                       Access / Refresh Token.              Invalida todas las sesiones del usuario.
Validación Inicial: Verifica la firma del Refresh Token empleando la clave secreta. Extrae el claim jti.

Verificación en Base de Datos: Consulta la tabla user_session usando el identificador jti mediante bloqueo pesimista:

SQL
SELECT is_revoked, user_id FROM user_session WHERE token_jti = :jti FOR UPDATE;
Detección de Reutilización de Token: Si el campo is_revoked es igual a 1, el sistema asume de inmediato una brecha de seguridad (intento de secuestro por token robado). Ejecuta inmediatamente una query punitiva anulando de golpe todas las sesiones activas del usuario comprometido:

SQL
UPDATE user_session SET is_revoked = 1 WHERE user_id = :user_id;
Y retorna un código de error de acceso prohibido 403 Forbidden.

Mutación Segura: Si el token es perfectamente válido (is_revoked = 0), invalida de forma inmediata el jti actual, registra un nuevo UUIDv4 para el próximo Refresh Token en la tabla y devuelve al cliente un par de tokens totalmente nuevos (Rotación Completa).

3. Implementación del Generador y Validador de JWT en PHP Puro
Para cumplir rigurosamente con las directivas de inyección manual por constructor y declaración estricta de tipos definidas en la arquitectura del proyecto, el software utilizará el siguiente componente nativo desacoplado en la capa de infraestructura (src/Infrastructure/Shared/Security/JwtTokenManager.php):

PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

class JwtTokenManager {
    private string $secret;
    private string $issuer;

    public function __construct(string $secret, string $issuer) {
        if (strlen($secret) < 64) {
            throw new \InvalidArgumentException("La llave secreta JWT debe tener al menos 64 caracteres de longitud.");
        }
        $this->secret = $secret;
        $this->issuer = $issuer;
    }

    /**
     * Genera un token JWT codificado bajo el estándar criptográfico Base64Url.
     */
    public function generate(array $claims, int $ttlSeconds): string {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        
        $payloadData = array_merge([
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $ttlSeconds
        ], $claims);
        
        $payload = json_encode($payloadData);

        $base64UrlHeader  = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    /**
     * Valida la firma e integridad del token devuelto. Retorna el payload decodificado si es válido.
     */
    public function validate(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \UnexpectedValueException("Estructura de token JWT inválida.", 401);
        }

        [$header, $payload, $signature] = $parts;

        // Validar la firma criptográfica de forma determinista
        $expectedSignature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        if (!hash_equals($this->base64UrlEncode($expectedSignature), $signature)) {
            throw new \SecurityException("Manipulación criptográfica detectada. Firma inválida.", 401);
        }

        $payloadData = json_decode($this->base64UrlDecode($payload), true);
        
        // Validar vigencia temporal
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            throw new \ExpiredException("El token JWT ha expirado.", 401);
        }

        return $payloadData;
    }

    private function base64UrlEncode(string $data): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
4. Protección contra Ataques de Fijación de Sesión (Session Fixation)
Para proteger a las recepcionistas y administradores que ingresan desde computadoras físicas compartidas en la sucursal:

Regeneración de Sesión PHP: En el momento exacto en que un usuario ingresa sus credenciales en el endpoint de autenticación física y este es validado con éxito, el backend invocará de forma obligatoria la función nativa session_regenerate_id(true). Esto destruye el identificador de sesión anterior del lado del servidor Apache y asigna uno totalmente nuevo, anulando cualquier cookie interceptada previamente en la red local.

Aislamiento de Atributos de Cookie: Las cookies destinadas a almacenar el Refresh Token o identificadores de sesión en producción dentro de Hostinger serán inyectadas de forma exclusiva bajo las siguientes banderas estrictas del protocolo HTTP:

Secure: Exige de forma imperativa que la cookie viaje únicamente sobre canales cifrados HTTPS.

HttpOnly: Bloquea por completo el acceso a la cookie desde scripts de JavaScript en el navegador, mitigando de forma total ataques de robo de sesión a través de vulnerabilidades XSS.

SameSite=Strict: Restringe el envío de la cookie solo a peticiones originadas en el propio dominio de la estética, blindando la API ante ataques Cross-Site Request Forgery (CSRF).

5. Perímetro de Protección OWASP y Mitigación de Ataques5.1. Rate Limiting en PHP Puro: Algoritmo Token BucketPara evitar ataques de denegación de servicio (DoS) o ataques de fuerza bruta en los endpoints de autenticación y webhooks (Meta Cloud API), se implementa un control de tasa basado en persistencia. Cada IP o identificador único dispone de una cubeta de tokens que se vacía con cada petición y se rellena de forma pasiva con el paso del tiempo.El middleware de control de tasa de solicitudes (RateLimiterMiddleware) interactúa con la base de datos utilizando la siguiente lógica:PHP<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use \PDO;

class RateLimiterMiddleware {
    private PDO $pdo;
    private int $maxTokens = 60;       // Capacidad máxima de la cubeta
    private float $refillRate = 1.0;   // Tokens añadidos por segundo

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle(string $identifier): void {
        $now = microtime(true);
        
        $sql = "SELECT tokens, last_updated FROM rate_limit WHERE identifier = :id FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $identifier]);
        $row = $stmt->fetch();

        if (!$row) {
            // Primera petición: Inicializar cubeta limpia
            $sqlInsert = "INSERT INTO rate_limit (identifier, tokens, last_updated) VALUES (:id, :tokens, :last_updated)";
            $this->pdo->prepare($sqlInsert)->execute([
                'id' => $identifier,
                'tokens' => $this->maxTokens - 1,
                'last_updated' => $now
            ]);
            return;
        }

        $currentTokens = (float)$row['tokens'];
        $lastUpdated = (float)$row['last_updated'];

        // Calcular tokens añadidos pasivamente según el tiempo transcurrido
        $elapsedTime = $now - $lastUpdated;
        $tokensToAdd = $elapsedTime * $this->refillRate;
        $newTokens = min((float)$this->maxTokens, $currentTokens + $tokensToAdd);

        if ($newTokens < 1.0) {
            // Cubeta vacía: Denegar el acceso inmediatamente
            header('HTTP/1.1 429 Too Many Requests');
            header('Content-Type: application/problem+json');
            header('Retry-After: ' . ceil(1.0 / $this->refillRate));
            echo json_encode([
                'type' => 'https://api.esteticacarolinamora.com/errors/too-many-requests',
                'title' => 'Tasa de solicitudes excedida',
                'status' => 429,
                'detail' => 'Has realizado demasiadas peticiones en un corto periodo de tiempo. Por favor, espera antes de reintentar.'
            ]);
            exit;
        }

        // Restar un token por la petición actual y actualizar marcas de tiempo
        $sqlUpdate = "UPDATE rate_limit SET tokens = :tokens, last_updated = :last_updated WHERE identifier = :id";
        $this->pdo->prepare($sqlUpdate)->execute([
            'tokens' => $newTokens - 1.0,
            'last_updated' => $now,
            'id' => $identifier
        ]);
    }
}
5.2. Cabeceras de Seguridad Inyectadas por Servidor y Apache (.htaccess)Para mitigar de raíz vulnerabilidades de inyección de scripts cruzados (XSS), secuestro de clics (Clickjacking) y sniffing de tipos MIME, el archivo .htaccess en Hostinger debe inyectar de forma obligatoria las siguientes cabeceras en cada respuesta HTTP:Apache# Forzar HTTPS y mitigar ataques Man-In-The-Middle (MitM)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

# Impedir que el sitio sea embebido en iFrames externos (Anti-Clickjacking)
Header always set X-Frame-Options "DENY"

# Desactivar la detección automática de tipos de archivos (Anti-Sniffing)
Header always set X-Content-Type-Options "nosniff"

# Bloquear la ejecución de scripts no autorizados (Mitigación XSS)
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self' https://api.whatsapp.com;"

# Restringir la información de referencia enviada en los enlaces
Header always set Referrer-Policy "strict-origin-when-cross-origin"
5.3. Control de Intercambio de Recursos de Origen Cruzado (CORS)Para permitir que la PWA y la App Móvil consuman la API sin abrir brechas de seguridad a orígenes arbitrarios, el inicializador PHP procesará las solicitudes OPTIONS previas (preflight) validando de forma explícita una lista blanca de dominios:PHP$allowedOrigins = [
    'https://esteticacarolinamora.com',
    'https://www.esteticacarolinamora.com',
    'http://localhost:5173' // Permitido exclusivamente en entorno de desarrollo local
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Credentials: true");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}
6. Matriz Exhaustiva de Roles y Permisos (RBAC Matrix)El control de acceso basado en roles se estructura bajo un modelo restrictivo: todo lo que no esté explícitamente permitido está prohibido.6.1. Definición de Roles del SistemaSUPER_ADMIN: Control absoluto sobre todas las sucursales, catálogos, configuraciones e inventarios del sistema.BRANCH_ADMIN: Gestión total operativa y financiera de su sucursal asignada. No puede dar de alta otras sucursales.RECEPCIONIST: Gestión diaria de citas en mostrador, check-in, cobros en caja física POS y atención al público.PROFESSIONAL: Acceso exclusivo a la lectura de su propia agenda de citas y actualización de notas técnicas/clínicas de cabina.CLIENT: Consulta de su propio historial de citas, exploración del catálogo y agendamiento autónomo de sus reservas.6.2. Matriz de Permisos por Acción y MóduloMódulo CorePermiso EspecíficoSUPER_ADMINBRANCH_ADMINRECEPCIONISTPROFESSIONALCLIENTIAMuser:create🟢🟢🟢❌❌IAMuser:modify-role🟢❌❌❌❌Catalogservice:write🟢🟢❌❌❌Catalogpromotion:write🟢🟢❌❌❌Staffingschedule:write🟢🟢❌❌❌Bookingappointment:create🟢🟢🟢❌🟢Bookingappointment:cancel🟢🟢🟢❌🟢Bookingappointment:check-in🟢🟢🟢❌❌Bookingappointment:complete❌❌❌🟢❌Bookingappointment:view-all🟢🟢🟢❌❌Bookingappointment:view-own❌❌❌🟢🟢Billingpayment:process-pos🟢🟢🟢❌❌Billingcash-desk:closing🟢🟢❌❌❌7. Middleware de Autorización e Intercepción PerimetralEste componente de infraestructura se ejecuta inmediatamente después de validar la autenticidad criptográfica del token JWT. Su responsabilidad es contrastar los claims del usuario extraídos del token con las restricciones de la ruta HTTP antes de invocar la ejecución del caso de uso.PHP<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

class AuthorizationMiddleware {
    private array $matrix;

    public function __construct(array $rbacMatrixConfiguration) {
        $this->matrix = $rbacMatrixConfiguration;
    }

    /**
     * Intercepta la solicitud y valida los privilegios del rol del usuario.
     */
    public function checkPermission(string $userRole, string $requiredPermission): void {
        // Verificar si el permiso existe dentro de la configuración lógica estructurada
        if (!isset($this->matrix[$requiredPermission])) {
            $this->denyAccess("El permiso solicitado '$requiredPermission' no se encuentra parametrizado.");
        }

        // Validar si el rol específico tiene asignado el permiso con valor de bandera verdadero
        $allowedRoles = $this->matrix[$requiredPermission];
        if (!isset($allowedRoles[$userRole]) || $allowedRoles[$userRole] !== true) {
            $this->denyAccess("Tu rol de usuario '$userRole' no cuenta con privilegios suficientes para realizar esta acción.");
        }
        
        // Acceso concedido: El flujo del controlador puede continuar de forma segura
    }

    private function denyAccess(string $reason): void {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/problem+json');
        echo json_encode([
            'type' => 'https://api.esteticacarolinamora.com/errors/forbidden',
            'title' => 'Acceso denegación por privilegios (RBAC)',
            'status' => 403,
            'detail' => $reason
        ]);
        exit;
    }
}
Flujo de Uso en un Controlador de la API (Ejemplo de Integración):PHP// 1. El Middleware de autenticación previo valida el JWT y expone el objeto de contexto
$userRole = $userContext->getRole(); // Ej: "RECEPCIONIST"

// 2. Instanciar el middleware de autorización pasándole la matriz estricta
$auth = new AuthorizationMiddleware($rbacMatrixConfiguration);

// 3. Ejecutar la validación del perímetro antes de tocar el modelo o el caso de uso
$auth->checkPermission($userRole, 'appointment:check-in');

// 4. Si pasa, se ejecuta la acción transaccional de forma totalmente blindada
$executeCheckInUseCase->execute($appointmentId);
