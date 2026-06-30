<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * ConnectionFactory
 *
 * Fábrica estática de conexiones PDO.
 * Responsabilidades únicas:
 *   1. Leer credenciales del entorno (.env cargado previamente en bootstrap).
 *   2. Construir el DSN y abrir la conexión con los atributos de seguridad mínimos.
 *   3. Lanzar RuntimeException (nunca exponer credenciales) ante cualquier fallo.
 *
 * Cumple:
 *  - Skill 1  → strict_types, sin acoplamiento a clases concretas externas
 *  - Skill 10 → ATTR_EMULATE_PREPARES = false bloquea inyección SQL de segundo orden
 *  - Skill 7  → charset utf8mb4 + timezone de sesión forzada a America/Bogota
 */
final class ConnectionFactory
{
    /** No se permite instanciar esta clase; es una fábrica puramente estática. */
    private function __construct() {}

    // ─────────────────────────────────────────────────────────
    //  FACTORY METHOD PRINCIPAL
    // ─────────────────────────────────────────────────────────

    /**
     * Crea y devuelve una instancia PDO configurada a partir de las variables
     * de entorno cargadas en $_ENV por el bootstrap.
     *
     * Variables requeridas en .env:
     *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
     *
     * @throws RuntimeException Si alguna variable de entorno está ausente o la
     *                          conexión falla (sin revelar el stack trace raw).
     */
    public static function createFromEnv(): PDO
    {
        // ── 1. Lectura y validación de variables de entorno ──────────────
        $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

        foreach ($required as $key) {
            if (self::env($key) === null) {
                throw new RuntimeException(
                    "Variable de entorno requerida '{$key}' no está definida. " .
                    'Verifica tu archivo .env.'
                );
            }
        }

        $host   = (string) self::env('DB_HOST');
        $port   = (int)    self::env('DB_PORT');
        $dbName = (string) self::env('DB_NAME');
        $user   = (string) self::env('DB_USER');
        $pass   = (string) self::env('DB_PASS');

        // ── 2. Construcción del DSN ──────────────────────────────────────
        // charset=utf8mb4 garantiza soporte completo de emojis y caracteres
        // multibyte sin corrupción silenciosa al persistir.
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        // ── 3. Atributos de conexión obligatorios ────────────────────────
        $options = [
            // Convierte errores PDO en excepciones PHP (nunca fallos silenciosos).
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Resultados como arrays asociativos por defecto (sin índices numéricos).
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // CRÍTICO: Desactiva la emulación de sentencias preparadas del driver.
            // Garantiza que MySQL parsee el SQL antes de inyectar parámetros,
            // bloqueando inyección SQL de segundo orden (Skill 10).
            PDO::ATTR_EMULATE_PREPARES   => false,

            // Conexiones persistentes desactivadas en entornos de hosting
            // compartido para evitar el agotamiento del pool de conexiones.
            PDO::ATTR_PERSISTENT         => false,

            // Tiempo máximo de espera de conexión en segundos.
            PDO::ATTR_TIMEOUT            => 5,
        ];

        // ── 4. Apertura de conexión con manejo defensivo ─────────────────
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);

            // Skill 7 — Forzar zona horaria de sesión MySQL a America/Bogota
            // para garantizar coherencia absoluta en columnas DATETIME/TIMESTAMP.
            // Se usa UTC-5 fijo para evitar problemas con el cambio de horario.
            $pdo->exec("SET time_zone = '-05:00'");

            return $pdo;

        } catch (PDOException $e) {
            // Nunca propagamos el PDOException original hacia capas superiores
            // porque su mensaje puede contener el DSN con credenciales.
            // Skill 4: En desarrollo, incluimos el mensaje real para diagnóstico rápido.
            $errorMessage = ($_ENV['APP_ENV'] ?? 'local') === 'local' 
                ? 'Error de conexión: ' . $e->getMessage()
                : 'No se pudo establecer la conexión con la base de datos.';

            throw new RuntimeException(
                $errorMessage,
                (int) $e->getCode(),
                $e // Se preserva como cause para el log interno (nunca expuesto al cliente).
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    //  HELPER PRIVADO
    // ─────────────────────────────────────────────────────────

    /**
     * Lee una variable del entorno priorizando $_ENV sobre getenv().
     * Devuelve null si la variable no existe. Acepta cadenas vacías como valor válido
     * (XAMPP root sin contraseña usa DB_PASS vacío).
     */
    private static function env(string $key): string|null
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return ($value !== false) ? $value : null;
    }
}
