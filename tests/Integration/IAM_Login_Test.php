<?php
declare(strict_types=1);

/**
 * TEST DE INTEGRACIÓN: Proceso de Autenticación
 * Valida que el flujo completo (DB -> UseCase -> JWT) funcione.
 * 
 * Ejecución: php tests/Integration/IAM_Login_Test.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// El bootstrap devuelve el objeto Router configurado
$router = require __DIR__ . '/../../bootstrap.php';

// Accedemos al contenedor (closure) mediante reflexión para instanciar el UseCase en el test
$reflection = new \ReflectionClass($router);
$containerProp = $reflection->getProperty('container');
$containerProp->setAccessible(true);
$container = $containerProp->getValue($router);

use App\Application\IAM\Authenticate\AuthenticateUserUseCase;
use App\Application\IAM\Authenticate\AuthenticateDTO;

echo "==========================================================\n";
echo "  TEST DE INTEGRACIÓN: IAM LOGIN\n";
echo "==========================================================\n\n";

/** @var AuthenticateUserUseCase $useCase */
$useCase = $container(AuthenticateUserUseCase::class);

$scenarios = [
    'LOGIN EXITOSO' => [
        'email' => 'admin@estetica.com',
        'pass'  => 'password'
    ],
    'USUARIO INEXISTENTE' => [
        'email' => 'desconocido@estetica.com',
        'pass'  => 'password'
    ],
    'CONTRASEÑA INCORRECTA' => [
        'email' => 'admin@estetica.com',
        'pass'  => 'clave_erronea'
    ]
];

foreach ($scenarios as $name => $data) {
    echo "--- Escenario: $name ---\n";
    echo "Probando con: {$data['email']} / {$data['pass']}\n";
    
    try {
        $dto = new AuthenticateDTO($data['email'], $data['pass']);
        $token = $useCase->execute($dto);
        
        echo "✅ RESULTADO: Login exitoso.\n";
        echo "🔹 JWT: " . substr($token, 0, 60) . "...\n\n";
        
    } catch (\DomainException $e) {
        echo "🛑 RESULTADO: Error controlado (RFC 7807).\n";
        echo "🔹 Mensaje: " . $e->getMessage() . " (Código: " . $e->getCode() . ")\n\n";
    } catch (\Exception $e) {
        echo "⚠️ RESULTADO: Error inesperado.\n";
        echo "🔹 " . $e->getMessage() . "\n\n";
    }
}
