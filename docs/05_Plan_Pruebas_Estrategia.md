Este documento establece la estrategia, el marco de trabajo y los casos de prueba automatizados y manuales para garantizar la estabilidad, seguridad y resiliencia de la plataforma de la estÃ©tica. Al no depender de frameworks pesados, el plan de pruebas se enfoca en verificar el comportamiento de las tres capas independientes de la arquitectura.

1. Enfoque y PirÃ¡mide de Pruebas para Antigravity 2.0
Para optimizar los recursos del servidor y garantizar respuestas inmediatas (cumpliendo con el RNF-06 de velocidad), el software se regirÃ¡ por una pirÃ¡mide de pruebas automatizadas estricta:

Plaintext
       / \
      /   \     Pruebas E2E / Manuales (10%): Flujo visual PWA, Bot real.
     /     \
    /_______\   Pruebas de IntegraciÃ³n (30%): Persistencia PDO, Webhooks Meta, Bloqueos SQL.
   /         \
  /___________\ Pruebas Unitarias (60%): Entidades de Dominio, Casos de Uso, Reglas de Negocio.
Pruebas Unitarias (60%): Se ejecutan en memoria, de forma ultra veloz. Validan que las entidades del dominio y los Casos de Uso (Application Layer) respondan correctamente ante datos vÃ¡lidos e invÃ¡lidos. Se aÃ­slan las llamadas a la base de datos utilizando dobles de prueba (Mocks o Fakes) de las interfaces de los repositorios.

Pruebas de IntegraciÃ³n (30%): Validan la comunicaciÃ³n con el exterior. Prueban que las consultas SQL en los repositorios PDO funcionen en MySQL 8 y que las firmas criptogrÃ¡ficas de los webhooks se validen correctamente.

Pruebas de Extremo a Extremo (E2E) y Manuales (10%): Validaciones de caja negra simulando las interacciones del cliente final desde la interfaz de la PWA o comandos de texto reales enviados hacia el nÃºmero de pruebas del Bot.

2. ConfiguraciÃ³n del Entorno de Pruebas (PHP Puro)
Dado que el despliegue final se realiza sobre entornos estÃ¡ndar (Hostinger/XAMPP), las pruebas automatizadas se ejecutarÃ¡n localmente o en un flujo de integraciÃ³n mediante una suite basada en PHPUnit o un script ejecutor nativo (tests/run.php) que use sentencias assert() con tipado estricto.

Directivas del Entorno de Pruebas:
Base de Datos Aislada: Las pruebas de integraciÃ³n jamÃ¡s deben tocar la base de datos de producciÃ³n ni la de desarrollo local estÃ¡ndar. Se debe levantar un esquema idÃ©ntico llamado estetica_carolinamora_test.

Estado Limpio (Teardown): Cada prueba de integraciÃ³n que escriba datos en las tablas (appointment, payment, etc.) debe ejecutarse dentro de una transacciÃ³n de base de datos ($pdo->beginTransaction()) y aplicar un $pdo->rollBack() al finalizar, garantizando que la base de datos quede en un estado prÃ­stino para la siguiente prueba.

3. EspecificaciÃ³n de Pruebas Unitarias (Capa de Dominio y AplicaciÃ³n)
Las pruebas unitarias no requieren conexiÃ³n a la base de datos. Utilizan implementaciones falsas en memoria (In-Memory Repositories) que imitan a las interfaces del dominio.

Caso de Criterio 1: ValidaciÃ³n de la Regla de Oro de las 24 Horas para CancelaciÃ³n (RF-09)
Objetivo: Verificar que el caso de uso CancelAppointmentUseCase aborte la operaciÃ³n si el cliente intenta cancelar una cita de forma autÃ³noma con menos de 24 horas de anticipaciÃ³n respecto al scheduled_timestamp.

CÃ³digo de Plantilla Unitaria (Test Case):

PHP
<?php
declare(strict_types=1);

namespace App\Tests\Unit\Booking;

use App\Application\Booking\CancelBooking\CancelAppointmentUseCase;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Entities\Appointment;

class CancelAppointmentUseCaseTest {
    
    public function testExecuteDeniesCancellationUnder24Hours(): void {
        // 1. PreparaciÃ³n (Arrange)
        // Simulamos una cita configurada para dentro de 5 horas (ViolaciÃ³n de la regla)
        $currentTime = new \DateTimeImmutable('2026-06-06 12:00:00');
        $appointmentTime = new \DateTimeImmutable('2026-06-06 17:00:00');
        
        // Creamos un Mock manual en memoria de la interfaz del repositorio
        $repositoryMock = new class implements AppointmentRepositoryInterface {
            public function findById(int $id): ?Appointment {
                // Retorna una entidad simulada con horario conflictivo
                return new Appointment(id: 50812, scheduledTimestamp: '2026-06-06 17:00:00', status: 'CONFIRMED');
            }
            public function hasCollision(int $pId, string $s, string $e): bool { return false; }
            public function save(Appointment $appointment): void {}
            public function updateStatus(int $id, string $status): void {}
        };

        $useCase = new CancelAppointmentUseCase($repositoryMock);

        // 2. EjecuciÃ³n y AserciÃ³n (Act & Assert)
        try {
            // Pasamos la hora actual simulada del sistema al caso de uso
            $useCase->execute(appointmentId: 50812, contextTime: $currentTime);
            
            // Si llega a esta lÃ­nea, la prueba fallÃ³ porque debiÃ³ lanzar una excepciÃ³n
            echo "âŒ FAILED: testExecuteDeniesCancellationUnder24Hours - No lanzÃ³ excepciÃ³n.\n";
            exit(1);
        } catch (\DomainException $e) {
            // Verificamos que el cÃ³digo de error semÃ¡ntico o mensaje sea el correcto
            if ($e->getCode() === 422 && str_contains($e->getMessage(), 'anticipaciÃ³n')) {
                echo "ðŸŸ¢ PASSED: testExecuteDeniesCancellationUnder24Hours - ExcepciÃ³n capturada correctamente.\n";
            } else {
                echo "âŒ FAILED: testExecuteDeniesCancellationUnder24Hours - Mensaje o cÃ³digo inesperado: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
    }
}

// EjecuciÃ³n directa de la prueba
(new CancelAppointmentUseCaseTest())->testExecuteDeniesCancellationUnder24Hours();
Caso de Criterio 2: CÃ¡lculo DinÃ¡mico del Fin de la Cita (RF-06, RF-07)
Objetivo: Validar que al procesar una nueva reserva, el sistema sume de forma exacta los minutos de duraciÃ³n del servicio (registrado en el catÃ¡logo) a la hora de inicio para rellenar de forma automatizada el campo estimated_end_timestamp.

Datos de Entrada en el Test: start_time = "2026-06-15 09:00:00", Servicio = Manicure (DuraciÃ³n = 45 minutos).

Resultado Esperado: El DTO de salida o la entidad generada debe tener un estimated_end_timestamp igual a "2026-06-15 09:45:00". Si se aÃ±ade otra duraciÃ³n o promociÃ³n, el cÃ¡lculo debe cuadrar matemÃ¡ticamente al minuto exacto.

4. EspecificaciÃ³n de Pruebas de IntegraciÃ³n Concurrente (Capa de Infraestructura)Estas pruebas interactÃºan directamente con el motor MySQL 8 en el entorno aislado estetica_carolinamora_test. Su propÃ³sito principal es asegurar que los repositorios cumplan con las transacciones ACID y que el bloqueo pesimista impida condiciones de carrera catastrÃ³ficas.Caso de Criterio 3: Bloqueo Pesimista contra Overbooking SimultÃ¡neo (RF-06, RF-07, RNF-01)Objetivo: Verificar que si la PWA y el Bot de WhatsApp intentan reservar exactamente el mismo bloque horario para el mismo especialista de forma simultÃ¡nea, el sistema procese Ãºnicamente una de las dos solicitudes con cÃ³digo 201 Created y rechace la otra de forma segura con cÃ³digo 422 Unprocessable Entity.Estrategia de SimulaciÃ³n en PHP Puro: Se emula la concurrencia abriendo dos conexiones de base de datos independientes ($pdoA y $pdoB) y ejecutando transacciones en hilos lÃ³gicos secuenciales mediante un script de prueba automatizado.PHP<?php
declare(strict_types=1);

namespace App\Tests\Integration\Booking;

use App\Infrastructure\Booking\Persistence\PdoAppointmentRepository;
use App\Infrastructure\Shared\Database\ConnectionFactory;

class ConcurrentOverbookingTest {
    
    public function testPessimisticLockingPreventsDoubleBooking(): void {
        // 1. PreparaciÃ³n de dos conexiones de datos aisladas
        $pdoA = ConnectionFactory::createTestConnection();
        $pdoB = ConnectionFactory::createTestConnection();
        
        $repoA = new PdoAppointmentRepository($pdoA);
        $repoB = new PdoAppointmentRepository($pdoB);
        
        $professionalId = 14;
        $start = '2026-06-15 09:00:00';
        $end   = '2026-06-15 09:45:00';

        echo "Iniciando SimulaciÃ³n de RÃ¡faga Concurrente (PWA vs WhatsApp Bot)...\n";

        // 2. TransacciÃ³n A inicia (Simula PWA ganando el milisegundo)
        $pdoA->beginTransaction();
        $collisionA = $repoA->hasCollision($professionalId, $start, $end); // Ejecuta FOR UPDATE
        
        // En este punto, MySQL bloquea las filas correspondientes para el especialista 14

        // 3. TransacciÃ³n B inicia (Simula Bot de WhatsApp llegando instantÃ¡neamente)
        $pdoB->beginTransaction();
        
        // Intentamos ejecutar la verificaciÃ³n en B. Esto se quedarÃ¡ esperando en el motor SQL
        // Para evitar bloqueos indefinidos en la prueba, configuramos un timeout de sesiÃ³n corto
        $pdoB->exec("SET sys.lock_timeout = 1000; SET innodb_lock_wait_timeout = 1;");
        
        try {
            // Esto deberÃ­a arrojar una excepciÃ³n por timeout o retornar verdadero si el motor libera
            $collisionB = $repoB->hasCollision($professionalId, $start, $end);
            
            // Si llega aquÃ­ sin bloquearse ni fallar antes de que A termine, la prueba fallÃ³
            if ($collisionB === false) {
                echo "âŒ FAILED: Â¡CondiciÃ³n de carrera detectada! El sistema permitiÃ³ lectura sucia en ConexiÃ³n B.\n";
                $pdoA->rollBack();
                $pdoB->rollBack();
                exit(1);
            }
        } catch (\PDOException $e) {
            // El estado esperado es que la base de datos proteja el registro bloqueÃ¡ndolo
            echo "ðŸŸ¢ PASSED (Paso 1): ConexiÃ³n B bloqueada exitosamente por el motor de base de datos.\n";
        }

        // 4. Completar transacciÃ³n A (Inserta la cita y libera el candado)
        $sql = "INSERT INTO appointment (client_profile_id, professional_profile_id, branch_id, scheduled_timestamp, estimated_end_timestamp, appointment_status) 
                VALUES (34, :prof, 1, :start, :end, 'PENDING')";
        $stmt = $pdoA->prepare($sql);
        $stmt->execute(['prof' => $professionalId, 'start' => $start, 'end' => $end]);
        $pdoA->commit();
        
        echo "ðŸŸ¢ PASSED (Paso 2): TransacciÃ³n A guardada con Ã©xito. Base de datos consistente.\n";
        
        // Limpieza de datos de prueba
        $pdoA->exec("DELETE FROM appointment WHERE professional_profile_id = 14");
    }
}

// Ejecutar prueba de integraciÃ³n
(new ConcurrentOverbookingTest())->testPessimisticLockingPreventsDoubleBooking();
5. SimulaciÃ³n de Caja Negra: Webhook de WhatsApp (Meta Cloud API)Para probar la integraciÃ³n del bot conversacional sin consumir saldo de la API de producciÃ³n de Meta, la IA agÃ©ntica de Antigravity ejecutarÃ¡ peticiones HTTP controladas hacia el endpoint del webhook utilizando la herramienta integrada curl desde la consola del servidor de desarrollo.Script de SimulaciÃ³n de Mensaje de Texto Entrante (RF-07)Este comando inyecta un payload sintÃ¡ctico idÃ©ntico al enviado por los servidores de Meta, simulando que un usuario con el nÃºmero de telÃ©fono 573001234567 escribe el mensaje de texto "Quiero reservar una cita para manicure maÃ±ana".Bashcurl -X POST http://localhost/estetica-carolina-mora/api/v1/webhooks/whatsapp \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [
      {
        "id": "109283746564738",
        "changes": [
          {
            "value": {
              "messaging_product": "whatsapp",
              "metadata": {
                "display_phone_number": "15550123456",
                "phone_number_id": "104958273645"
              },
              "contacts": [
                {
                  "profile": { "name": "Camila Gomez" },
                  "wa_id": "573001234567"
                }
              ],
              "messages": [
                {
                  "from": "573001234567",
                  "id": "wamid.HBgLNTczMDAxMjM0NTY3FQIAERgE...",
                  "timestamp": "1780754400",
                  "text": {
                    "body": "Quiero reservar una cita para manicure maÃ±ana"
                  },
                  "type": "text"
                }
              ]
            },
            "field": "messages"
          }
        ]
      }
    ]
  }'
Criterios de AceptaciÃ³n para el Webhook:
CÃ³digo de Respuesta HTTP: El endpoint debe responder inmediatamente con un cÃ³digo 200 OK o 202 Accepted a Meta para evitar reintentos duplicados del webhook, de acuerdo con las especificaciones tÃ©cnicas internacionales de Meta.
Efecto colateral en base de datos: Al consultar la tabla audit_data_log o la tabla appointment, se debe verificar que el motor de lenguaje parseÃ³ la solicitud de forma correcta e iniciÃ³ o continuÃ³ el flujo de agenda adecuado para el cliente correspondiente.
6. Estrategia de Entornos y Despliegue Seguro
El ciclo de vida del software contarÃ¡ con tres entornos estrictamente aislados para mitigar riesgos en la operaciÃ³n presencial de las sucursales:
Entorno Infraestructura PropÃ³sito ConexiÃ³n a Base de Datos

Local / Dev             Servidor XAMPP local (PHP 8.x)                               Escritura de cÃ³digo, diseÃ±o de rutas, pruebas unitarias veloces.           estetica_carolinamora_dev
Staging / QA            Subdominio dedicado en Hostinger                             Pruebas de integraciÃ³n integrales, pruebas de carga de webhooks, verificaciÃ³n de compatibilidad de SSL. estetica_carolinamora_test
Production              Dominio principal en Hostinger (Con SSL)                     OperaciÃ³n real del negocio en mostrador, consumo de clientes PWA, App y Bot activo. estetica_carolinamora_prod
Protocolo de Despliegue a ProducciÃ³n (Checklist TÃ©cnico):
MigraciÃ³n de Esquema Limpia: EjecuciÃ³n del script DDL estructural (bd_estetica_carolinamora.sql) asegurando que los Ã­ndices como idx_schedule_matrix estÃ©n optimizados.
Variables de Entorno Estrictas: Cambiar en el archivo de configuraciÃ³n .env el parÃ¡metro APP_ENV=production. Esto desactiva automÃ¡ticamente la impresiÃ³n en pantalla del stack de errores tÃ©cnicos de PDO, impidiendo ataques de ingenierÃ­a social o exposiciÃ³n de credenciales del servidor compartido (RNF-03).

Aseguramiento SSL: Forzar mediante el archivo .htaccess del servidor Apache que todas las solicitudes HTTP sean redirigidas obligatoriamente hacia HTTPS para proteger la integridad del JWT y los tokens del bot.  
