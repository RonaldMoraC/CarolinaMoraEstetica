Este documento tÃ©cnico define los cimientos de ingenierÃ­a, la selecciÃ³n tecnolÃ³gica, la persistencia nativa y el esquema criptogrÃ¡fico de la aplicaciÃ³n mÃ³vil de la estÃ©tica para los ecosistemas iOS y Android. La app estÃ¡ concebida para interactuar de forma fluida con el backend puro en PHP v8.x, minimizando el consumo de baterÃ­a, asegurando transacciones atÃ³micas de reserva y blindando los identificadores de sesiÃ³n contra ataques fÃ­sicos o lÃ³gicos sobre el sistema operativo del smartphone.

1. RecomendaciÃ³n y JustificaciÃ³n de la TecnologÃ­a HÃ­brida
Para maximizar la reutilizaciÃ³n del cÃ³digo, acelerar el time-to-market y operar bajo un Ãºnico repositorio compartido que consuma las especificaciones exactas de la API RESTful, se selecciona formalmente Flutter (Dart) por encima de React Native.

Matriz de Decisiones TÃ©cnicas y JustificaciÃ³n:
Rendimiento y CompilaciÃ³n AOT (Ahead-Of-Time): Flutter no depende de un puente de comunicaciÃ³n de JavaScript (JavaScript Bridge) para renderizar componentes ni interactuar con los canales nativos. Se compila directamente a cÃ³digo de mÃ¡quina binario nativo (C/C++ y ARM), cumpliendo con el RNF-06 de velocidad. Las animaciones de las transiciones de reserva de cabina se ejecutan consistentemente a 60/120 FPS sin degradaciÃ³n del hilo de UI.

ReutilizaciÃ³n Absoluta de la Capa de Datos (90%+): Dart permite definir modelos de datos y DTOs inmutables con tipado fuerte estricto, que se acoplan perfectamente al esquema relacional de la base de datos central y a la RFC 7807 implementada en el backend. Toda la capa de validaciÃ³n, lÃ³gica de sincronizaciÃ³n y procesamiento de peticiones HTTP es idÃ©ntica para iOS y Android.

Independencia de Actualizaciones del OS: Al pintar su propia interfaz grÃ¡fica mediante el motor grÃ¡fico Impeller, la aplicaciÃ³n no sufre roturas visuales cuando Apple o Google modifican los componentes de diseÃ±o de sus sistemas operativos mÃ³viles, asegurando la consistencia UX/UI demandada.

2. Arquitectura FÃ­sica y SeparaciÃ³n por Capas
La aplicaciÃ³n mÃ³vil adopta una variante adaptada de Clean Architecture combinada con el patrÃ³n de diseÃ±o BLoC (Business Logic Component) para lograr un desacoplamiento absoluto de la interfaz respecto a las fuentes de datos.

Plaintext
raiz-proyecto-mobile/lib/
â”œâ”€â”€ domain/                         # Capa de Dominio Pura (Sin dependencias externas)
â”‚   â”œâ”€â”€ entities/                   # Modelos de negocio inmutables (User, Appointment, Service)
â”‚   â””â”€â”€ repositories/               # Interfaces / Contratos abstractos de datos
â”‚
â”œâ”€â”€ application/                    # Capa de AplicaciÃ³n (GestiÃ³n de Estado)
â”‚   â”œâ”€â”€ auth/                       # BLoC / Eventos / Estados de AutenticaciÃ³n
â”‚   â”œâ”€â”€ booking/                    # BLoC de GestiÃ³n de Citas y Calendario
â”‚   â””â”€â”€ catalog/                    # BLoC de Carga de Servicios y Promociones
â”‚
â”œâ”€â”€ infrastructure/                 # Capa de Infraestructura (ImplementaciÃ³n fÃ­sica)
â”‚   â”œâ”€â”€ datasources/
â”‚   â”‚   â”œâ”€â”€ api_client.dart         # Cliente HTTP (Dio) con interceptores criptogrÃ¡ficos
â”‚   â”‚   â””â”€â”€ local_database.dart     # AbstracciÃ³n embebida sobre SQLite local
â”‚   â”œâ”€â”€ repositories/               # ImplementaciÃ³n concreta de las interfaces del dominio
â”‚   â””â”€â”€ security/                   # Adaptador nativo de almacenamiento seguro
â”‚
â””â”€â”€ presentation/                   # Capa de PresentaciÃ³n (UI y Widgets)
    â”œâ”€â”€ core/                       # Temas de diseÃ±o, paleta de colores y componentes atÃ³micos
    â”œâ”€â”€ screens/                    # Vistas completas (LoginScreen, HomeScreen, BookingScreen)
    â””â”€â”€ widgets/                    # Fragmentos de UI reactivos mapeados a estados BLoC
3. Seguridad y SesiÃ³n en Dispositivo: CriptografÃ­a en Hardware
Las aplicaciones mÃ³viles carecen de mecanismos como las cookies HttpOnly con bandera SameSite disponibles en navegadores web. Para evitar el secuestro del Access Token y el Refresh Token, la aplicaciÃ³n implementa almacenamiento cifrado a nivel de silicio a travÃ©s del plugin flutter_secure_storage.

3.1. AbstracciÃ³n del Llavero Nativo
En iOS: Utiliza el servicio del Keychain, donde los datos se cifran empleando una clave generada por hardware que solo se desbloquea bajo el contexto sandbox de la aplicaciÃ³n.

En Android: Utiliza la API KeyStore combinada con cifrado AES-256 en modo GCM. La clave de cifrado se almacena de forma segura en el elemento de hardware dedicado del procesador (Trusted Execution Environment - TEE o StrongBox).

3.2. ImplementaciÃ³n del Gestor Cifrado de Tokens (secure_token_manager.dart)
Dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureTokenManager {
  final FlutterSecureStorage _storage;

  SecureTokenManager() : _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  static const String _accessTokenKey = 'jwt_access_token';
  static const String _refreshTokenKey = 'jwt_refresh_token';

  /// Guarda de forma segura los tokens devueltos por la API del Backend.
  Future<void> saveTokens({required String accessToken, required String refreshToken}) async {
    await _storage.write(key: _accessTokenKey, value: accessToken);
    await _storage.write(key: _refreshTokenKey, value: refreshToken);
  }

  /// Recupera el Access Token para inyectarlo en la cabecera Authorization Bearer.
  Future<String?> getAccessToken() async {
    return await _storage.read(key: _accessTokenKey);
  }

  /// Recupera el Refresh Token para procesar el ciclo de rotaciÃ³n automatizado.
  Future<String?> getRefreshToken() async {
    return await _storage.read(key: _refreshTokenKey);
  }

  /// Purga completa del llavero criptogrÃ¡fico (Cierre de sesiÃ³n seguro).
  Future<void> clearTokens() async {
    await _storage.delete(key: _accessTokenKey);
    await _storage.delete(key: _refreshTokenKey);
  }
}
3.3. Interceptor HTTP Automatizado (RotaciÃ³n Anti-Overhead)
El cliente HTTP de la aplicaciÃ³n (Dio) incorpora un interceptor que automatiza la inyecciÃ³n del token y procesa la recuperaciÃ³n atÃ³mica ante un error 401 Unauthorized. Si el Access Token expira, detiene de forma transparente la cola de peticiones, consume de forma segura el endpoint /api/v1/auth/refresh enviando el Refresh Token, actualiza el almacenamiento cifrado y reintenta la peticiÃ³n original del cliente sin alterar la experiencia visual del usuario.

4. Estrategia de SincronizaciÃ³n Local y Carga InstantÃ¡nea
Para ofrecer una experiencia fluida al cliente, el catÃ¡logo de servicios, especialistas y sucursales debe cargar en menos de 100 milisegundos. La aplicaciÃ³n mÃ³vil no realiza peticiones HTTP a la red durante la renderizaciÃ³n inicial; consume una base de datos local embebida basada en SQLite (mediante drift o sqflite), sincronizÃ¡ndose en segundo plano mediante un esquema diferencial de marcas de tiempo (timestamps).

4.1. Esquema de Datos de SincronizaciÃ³n Local
Plaintext
       [Inicios de la App] â”€â”€> Lee CatÃ¡logo desde SQLite Local â”€â”€> Renderizado InstantÃ¡neo (UI)
                                             â”‚
                                     (Segundo Plano)
                                             v
                         PeticiÃ³n GET /api/v1/catalog/sync?last_sync=TS
                                             â”‚
                     â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                     v                                               v
             Â¿Respuesta 200 OK con Cambios?                  Â¿Respuesta 304 Not Modified?
                     â”‚                                               â”‚
                     v                                               v
       Aplica Deltas (Inserta/Actualiza)                       No hace nada.
       Actualiza timestamp local en la base de datos.         Mantiene cachÃ© intacta.
4.2. Estructura de la Tabla de Control de SincronizaciÃ³n
La base de datos local gestiona una tabla interna denominada sync_metadata:

table_name: Nombre del recurso indexado (services, professionals, branches).

last_synchronized_at: String ISO8601 o marca de tiempo milisegÃºndica provista por el reloj del backend durante la Ãºltima descarga exitosa.

4.3. Script de Repositorio de SincronizaciÃ³n Local (catalog_repository.dart)
Dart
import 'local_database.dart';
import 'api_client.dart';

class CatalogRepository {
  final ApiClient _apiClient;
  final LocalDatabase _localDb;

  CatalogRepository(this._apiClient, this._localDb);

  /// Sincroniza el catÃ¡logo local con los cambios del backend de forma diferencial.
  Future<void> synchronizeCatalog() async {
    try {
      // 1. Obtener la Ãºltima marca de tiempo registrada localmente
      final String lastSyncTimestamp = await _localDb.getLastSyncForTable('services');

      // 2. Consumir el endpoint incremental pasÃ¡ndole la cabecera o parÃ¡metro condicional
      final response = await _apiClient.get(
        '/api/v1/catalog/sync',
        queryParameters: {'last_sync': lastSyncTimestamp},
      );

      if (response.statusCode == 200) {
        final List<dynamic> upsertDeltas = response.data['deltas']['upserted'];
        final List<dynamic> deleteDeltas = response.data['deltas']['deleted'];
        final String newServerTimestamp = response.data['server_timestamp'];

        // 3. Ejecutar una transacciÃ³n atÃ³mica local en la base de datos SQLite
        await _localDb.transaction(() async {
          // Procesar inserciones o actualizaciones estructurales
          for (var item in upsertDeltas) {
            await _localDb.upsertService({
              'id': item['id'],
              'branch_id': item['branch_id'],
              'name': item['name'],
              'duration_minutes': item['duration_minutes'],
              'price': double.parse(item['price'].toString()),
              'is_active': item['is_active'] ? 1 : 0
            });
          }

          // Eliminar lÃ³gicamente registros purgados del panel administrativo del backend
          for (var id in deleteDeltas) {
            await _localDb.deleteServiceById(id);
          }

          // Actualizar metadatos para la prÃ³xima iteraciÃ³n diferencial
          await _localDb.updateSyncMetadata('services', newServerTimestamp);
        });
      }
    } catch (e) {
      // Manejo de resiliencia: Si la red falla o el hosting compartido tarda en responder,
      // la aplicaciÃ³n ignora el fallo de red silenciosamente, manteniendo la usabilidad offline
      // basada estrictamente en los datos de SQLite previamente guardados.
    }
  }
}
