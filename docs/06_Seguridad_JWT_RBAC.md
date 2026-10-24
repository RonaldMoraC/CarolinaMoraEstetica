Este manual tÃ©cnico dicta las especificaciones imperativas de diseÃ±o e implementaciÃ³n para el perÃ­metro criptogrÃ¡fico, la gestiÃ³n de sesiones y el control de acceso en PHP Puro (v8.x+) para Antigravity 2.0. El documento unifica las directivas de seguridad OWASP y el modelo RBAC, garantizando protecciÃ³n de nivel empresarial con soporte omnicanal simultÃ¡neo (PWA, App y Bot de WhatsApp).

1. CriptografÃ­a de Tokens: Arquitectura JWT Dual (Access & Refresh Tokens)
Para mitigar riesgos de secuestro de sesiÃ³n sin sobrecargar el servidor de base de datos compartida en Hostinger con lecturas continuas, el sistema implementarÃ¡ un esquema de Tokens Duales independientes.

1.1. EspecificaciÃ³n de Tiempos y ParÃ¡metros CriptogrÃ¡ficos
Algoritmo de Firma: Estrictamente HS256 (HMAC usando SHA-256) empleando una llave maestra de alta entropÃ­a (JWT_SECRET) de mÃ­nimo 64 caracteres alfanumÃ©ricos guardada en las variables de entorno locales (.env).

Access Token (Token de Acceso):

Tiempo de Vida (TTL): Exactamente 15 minutos (exp = time() + 900).

Naturaleza: Inmutable y sin estado (Stateless). Almacenado Ãºnicamente en la memoria RAM del frontend (PWA/App) para evitar vulnerabilidades XSS.

Refresh Token (Token de Refresco):

Tiempo de Vida (TTL): Exactamente 7 dÃ­as (exp = time() + 604800).

Naturaleza: Con estado (Stateful). Almacenado en el navegador del cliente mediante una Cookie HTTP-Only.

1.2. AnatomÃ­a de los Payloads (Claims Estructurados)
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
El Claim jti (JWT ID): Es un identificador Ãºnico criptogrÃ¡fico (UUIDv4) para el Refresh Token, indispensable para la invalidaciÃ³n atÃ³mica de sesiones en el backend.

2. GestiÃ³n e Invalidation de Sesiones Activas en Base de Datos
Para que el backend mantenga la capacidad de revocar de inmediato una sesiÃ³n (por ejemplo, si el cliente cierra sesiÃ³n o si el Administrador bloquea un usuario), los Refresh Tokens deben registrarse en la infraestructura persistente de MySQL 8 utilizando la tabla relacional preexistente user_session.

2.1. Estructura Estricta de Persistencia SQL de Sesiones
La tabla user_session mapea directamente el ciclo de vida del Refresh Token:

token_jti: Llave primaria (VARCHAR/UUID) que almacena el claim jti.

user_id: Conecta con el registro maestro del usuario (ON DELETE CASCADE).

is_revoked: Booleano / Diminuto entero (TINYINT(1)) indexado para identificar de forma atÃ³mica si la sesiÃ³n fue anulada perimetralmente.

expires_at: Timestamp de control para depuraciÃ³n automatizada de sesiones caducadas.

2.2. Flujo de RotaciÃ³n de Tokens (Mecanismo Anti-Replay)
Cuando el Access Token expira tras los 15 minutos, el cliente consume de forma automÃ¡tica el endpoint pÃºblico /api/v1/auth/refresh enviando el Refresh Token. El controlador PHP ejecuta la siguiente lÃ³gica atÃ³mica bajo sentencias preparadas PDO:

Plaintext
[Cliente PWA/App] ----(EnvÃ­a Cookie con Refresh Token JTI)----> [Endpoint /refresh]
                                                                        |
                                                  +---------------------+---------------------+
                                                  |                                           |
                                       Â¿JTI existe en BD y es vÃ¡lido?           Â¿JTI estÃ¡ marcado como REVOKED?
                                                  |                                           |
                                                  v                                           v
                                        Genera nuevo par de                    Â¡ALERTA DE ROBO DE TOKEN!
                                       Access / Refresh Token.              Invalida todas las sesiones del usuario.
ValidaciÃ³n Inicial: Verifica la firma del Refresh Token empleando la clave secreta. Extrae el claim jti.

VerificaciÃ³n en Base de Datos: Consulta la tabla user_session usando el identificador jti mediante bloqueo pesimista:

SQL
SELECT is_revoked, user_id FROM user_session WHERE token_jti = :jti FOR UPDATE;
DetecciÃ³n de ReutilizaciÃ³n de Token: Si el campo is_revoked es igual a 1, el sistema asume de inmediato una brecha de seguridad (intento de secuestro por token robado). Ejecuta inmediatamente una query punitiva anulando de golpe todas las sesiones activas del usuario comprometido:

SQL
UPDATE user_session SET is_revoked = 1 WHERE user_id = :user_id;
Y retorna un cÃ³digo de error de acceso prohibido 403 Forbidden.

MutaciÃ³n Segura: Si el token es perfectamente vÃ¡lido (is_revoked = 0), invalida de forma inmediata el jti actual, registra un nuevo UUIDv4 para el prÃ³ximo Refresh Token en la tabla y devuelve al cliente un par de tokens totalmente nuevos (RotaciÃ³n Completa).

3. ImplementaciÃ³n del Generador y Validador de JWT en PHP Puro
Para cumplir rigurosamente con las directivas de inyecciÃ³n manual por constructor y declaraciÃ³n estricta de tipos definidas en la arquitectura del proyecto, el software utilizarÃ¡ el siguiente componente nativo desacoplado en la capa de infraestructura (src/Infrastructure/Shared/Security/JwtTokenManager.php):

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
     * Genera un token JWT codificado bajo el estÃ¡ndar criptogrÃ¡fico Base64Url.
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
     * Valida la firma e integridad del token devuelto. Retorna el payload decodificado si es vÃ¡lido.
     */
    public function validate(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \UnexpectedValueException("Estructura de token JWT invÃ¡lida.", 401);
        }

        [$header, $payload, $signature] = $parts;

        // Validar la firma criptogrÃ¡fica de forma determinista
        $expectedSignature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        if (!hash_equals($this->base64UrlEncode($expectedSignature), $signature)) {
            throw new \SecurityException("ManipulaciÃ³n criptogrÃ¡fica detectada. Firma invÃ¡lida.", 401);
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
4. ProtecciÃ³n contra Ataques de FijaciÃ³n de SesiÃ³n (Session Fixation)
Para proteger a las recepcionistas y administradores que ingresan desde computadoras fÃ­sicas compartidas en la sucursal:

RegeneraciÃ³n de SesiÃ³n PHP: En el momento exacto en que un usuario ingresa sus credenciales en el endpoint de autenticaciÃ³n fÃ­sica y este es validado con Ã©xito, el backend invocarÃ¡ de forma obligatoria la funciÃ³n nativa session_regenerate_id(true). Esto destruye el identificador de sesiÃ³n anterior del lado del servidor Apache y asigna uno totalmente nuevo, anulando cualquier cookie interceptada previamente en la red local.

Aislamiento de Atributos de Cookie: Las cookies destinadas a almacenar el Refresh Token o identificadores de sesiÃ³n en producciÃ³n dentro de Hostinger serÃ¡n inyectadas de forma exclusiva bajo las siguientes banderas estrictas del protocolo HTTP:

Secure: Exige de forma imperativa que la cookie viaje Ãºnicamente sobre canales cifrados HTTPS.

HttpOnly: Bloquea por completo el acceso a la cookie desde scripts de JavaScript en el navegador, mitigando de forma total ataques de robo de sesiÃ³n a travÃ©s de vulnerabilidades XSS.

SameSite=Strict: Restringe el envÃ­o de la cookie solo a peticiones originadas en el propio dominio de la estÃ©tica, blindando la API ante ataques Cross-Site Request Forgery (CSRF).

5. PerÃ­metro de ProtecciÃ³n OWASP y MitigaciÃ³n de Ataques5.1. Rate Limiting en PHP Puro: Algoritmo Token BucketPara evitar ataques de denegaciÃ³n de servicio (DoS) o ataques de fuerza bruta en los endpoints de autenticaciÃ³n y webhooks (Meta Cloud API), se implementa un control de tasa basado en persistencia. Cada IP o identificador Ãºnico dispone de una cubeta de tokens que se vacÃ­a con cada peticiÃ³n y se rellena de forma pasiva con el paso del tiempo.El middleware de control de tasa de solicitudes (RateLimiterMiddleware) interactÃºa con la base de datos utilizando la siguiente lÃ³gica:PHP<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use \PDO;

class RateLimiterMiddleware {
    private PDO $pdo;
    private int $maxTokens = 60;       // Capacidad mÃ¡xima de la cubeta
    private float $refillRate = 1.0;   // Tokens aÃ±adidos por segundo

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
            // Primera peticiÃ³n: Inicializar cubeta limpia
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

        // Calcular tokens aÃ±adidos pasivamente segÃºn el tiempo transcurrido
        $elapsedTime = $now - $lastUpdated;
        $tokensToAdd = $elapsedTime * $this->refillRate;
        $newTokens = min((float)$this->maxTokens, $currentTokens + $tokensToAdd);

        if ($newTokens < 1.0) {
            // Cubeta vacÃ­a: Denegar el acceso inmediatamente
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

        // Restar un token por la peticiÃ³n actual y actualizar marcas de tiempo
        $sqlUpdate = "UPDATE rate_limit SET tokens = :tokens, last_updated = :last_updated WHERE identifier = :id";
        $this->pdo->prepare($sqlUpdate)->execute([
            'tokens' => $newTokens - 1.0,
            'last_updated' => $now,
            'id' => $identifier
        ]);
    }
}
5.2. Cabeceras de Seguridad Inyectadas por Servidor y Apache (.htaccess)Para mitigar de raÃ­z vulnerabilidades de inyecciÃ³n de scripts cruzados (XSS), secuestro de clics (Clickjacking) y sniffing de tipos MIME, el archivo .htaccess en Hostinger debe inyectar de forma obligatoria las siguientes cabeceras en cada respuesta HTTP:Apache# Forzar HTTPS y mitigar ataques Man-In-The-Middle (MitM)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

# Impedir que el sitio sea embebido en iFrames externos (Anti-Clickjacking)
Header always set X-Frame-Options "DENY"

# Desactivar la detecciÃ³n automÃ¡tica de tipos de archivos (Anti-Sniffing)
Header always set X-Content-Type-Options "nosniff"

# Bloquear la ejecuciÃ³n de scripts no autorizados (MitigaciÃ³n XSS)
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self' https://api.whatsapp.com;"

# Restringir la informaciÃ³n de referencia enviada en los enlaces
Header always set Referrer-Policy "strict-origin-when-cross-origin"
5.3. Control de Intercambio de Recursos de Origen Cruzado (CORS)Para permitir que la PWA y la App MÃ³vil consuman la API sin abrir brechas de seguridad a orÃ­genes arbitrarios, el inicializador PHP procesarÃ¡ las solicitudes OPTIONS previas (preflight) validando de forma explÃ­cita una lista blanca de dominios:PHP$allowedOrigins = [
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
6. Matriz Exhaustiva de Roles y Permisos (RBAC Matrix)El control de acceso basado en roles se estructura bajo un modelo restrictivo: todo lo que no estÃ© explÃ­citamente permitido estÃ¡ prohibido.6.1. DefiniciÃ³n de Roles del SistemaSUPER_ADMIN: Control absoluto sobre todas las sucursales, catÃ¡logos, configuraciones e inventarios del sistema.BRANCH_ADMIN: GestiÃ³n total operativa y financiera de su sucursal asignada. No puede dar de alta otras sucursales.RECEPCIONIST: GestiÃ³n diaria de citas en mostrador, check-in, cobros en caja fÃ­sica POS y atenciÃ³n al pÃºblico.PROFESSIONAL: Acceso exclusivo a la lectura de su propia agenda de citas y actualizaciÃ³n de notas tÃ©cnicas/clÃ­nicas de cabina.CLIENT: Consulta de su propio historial de citas, exploraciÃ³n del catÃ¡logo y agendamiento autÃ³nomo de sus reservas.6.2. Matriz de Permisos por AcciÃ³n y MÃ³duloMÃ³dulo CorePermiso EspecÃ­ficoSUPER_ADMINBRANCH_ADMINRECEPCIONISTPROFESSIONALCLIENTIAMuser:createðŸŸ¢ðŸŸ¢ðŸŸ¢âŒâŒIAMuser:modify-roleðŸŸ¢âŒâŒâŒâŒCatalogservice:writeðŸŸ¢ðŸŸ¢âŒâŒâŒCatalogpromotion:writeðŸŸ¢ðŸŸ¢âŒâŒâŒStaffingschedule:writeðŸŸ¢ðŸŸ¢âŒâŒâŒBookingappointment:createðŸŸ¢ðŸŸ¢ðŸŸ¢âŒðŸŸ¢Bookingappointment:cancelðŸŸ¢ðŸŸ¢ðŸŸ¢âŒðŸŸ¢Bookingappointment:check-inðŸŸ¢ðŸŸ¢ðŸŸ¢âŒâŒBookingappointment:completeâŒâŒâŒðŸŸ¢âŒBookingappointment:view-allðŸŸ¢ðŸŸ¢ðŸŸ¢âŒâŒBookingappointment:view-ownâŒâŒâŒðŸŸ¢ðŸŸ¢Billingpayment:process-posðŸŸ¢ðŸŸ¢ðŸŸ¢âŒâŒBillingcash-desk:closingðŸŸ¢ðŸŸ¢âŒâŒâŒ7. Middleware de AutorizaciÃ³n e IntercepciÃ³n PerimetralEste componente de infraestructura se ejecuta inmediatamente despuÃ©s de validar la autenticidad criptogrÃ¡fica del token JWT. Su responsabilidad es contrastar los claims del usuario extraÃ­dos del token con las restricciones de la ruta HTTP antes de invocar la ejecuciÃ³n del caso de uso.PHP<?php
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
        // Verificar si el permiso existe dentro de la configuraciÃ³n lÃ³gica estructurada
        if (!isset($this->matrix[$requiredPermission])) {
            $this->denyAccess("El permiso solicitado '$requiredPermission' no se encuentra parametrizado.");
        }

        // Validar si el rol especÃ­fico tiene asignado el permiso con valor de bandera verdadero
        $allowedRoles = $this->matrix[$requiredPermission];
        if (!isset($allowedRoles[$userRole]) || $allowedRoles[$userRole] !== true) {
            $this->denyAccess("Tu rol de usuario '$userRole' no cuenta con privilegios suficientes para realizar esta acciÃ³n.");
        }
        
        // Acceso concedido: El flujo del controlador puede continuar de forma segura
    }

    private function denyAccess(string $reason): void {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/problem+json');
        echo json_encode([
            'type' => 'https://api.esteticacarolinamora.com/errors/forbidden',
            'title' => 'Acceso denegaciÃ³n por privilegios (RBAC)',
            'status' => 403,
            'detail' => $reason
        ]);
        exit;
    }
}
Flujo de Uso en un Controlador de la API (Ejemplo de IntegraciÃ³n):PHP// 1. El Middleware de autenticaciÃ³n previo valida el JWT y expone el objeto de contexto
$userRole = $userContext->getRole(); // Ej: "RECEPCIONIST"

// 2. Instanciar el middleware de autorizaciÃ³n pasÃ¡ndole la matriz estricta
$auth = new AuthorizationMiddleware($rbacMatrixConfiguration);

// 3. Ejecutar la validaciÃ³n del perÃ­metro antes de tocar el modelo o el caso de uso
$auth->checkPermission($userRole, 'appointment:check-in');

// 4. Si pasa, se ejecuta la acciÃ³n transaccional de forma totalmente blindada
$executeCheckInUseCase->execute($appointmentId);

