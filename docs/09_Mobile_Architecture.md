Este documento técnico define los cimientos de ingeniería, la selección tecnológica, la persistencia nativa y el esquema criptográfico de la aplicación móvil de la estética para los ecosistemas iOS y Android. La app está concebida para interactuar de forma fluida con el backend puro en PHP v8.x, minimizando el consumo de batería, asegurando transacciones atómicas de reserva y blindando los identificadores de sesión contra ataques físicos o lógicos sobre el sistema operativo del smartphone.

1. Recomendación y Justificación de la Tecnología Híbrida
Para maximizar la reutilización del código, acelerar el time-to-market y operar bajo un único repositorio compartido que consuma las especificaciones exactas de la API RESTful, se selecciona formalmente Flutter (Dart) por encima de React Native.

Matriz de Decisiones Técnicas y Justificación:
Rendimiento y Compilación AOT (Ahead-Of-Time): Flutter no depende de un puente de comunicación de JavaScript (JavaScript Bridge) para renderizar componentes ni interactuar con los canales nativos. Se compila directamente a código de máquina binario nativo (C/C++ y ARM), cumpliendo con el RNF-06 de velocidad. Las animaciones de las transiciones de reserva de cabina se ejecutan consistentemente a 60/120 FPS sin degradación del hilo de UI.

Reutilización Absoluta de la Capa de Datos (90%+): Dart permite definir modelos de datos y DTOs inmutables con tipado fuerte estricto, que se acoplan perfectamente al esquema relacional de la base de datos central y a la RFC 7807 implementada en el backend. Toda la capa de validación, lógica de sincronización y procesamiento de peticiones HTTP es idéntica para iOS y Android.

Independencia de Actualizaciones del OS: Al pintar su propia interfaz gráfica mediante el motor gráfico Impeller, la aplicación no sufre roturas visuales cuando Apple o Google modifican los componentes de diseño de sus sistemas operativos móviles, asegurando la consistencia UX/UI demandada.

2. Arquitectura Física y Separación por Capas
La aplicación móvil adopta una variante adaptada de Clean Architecture combinada con el patrón de diseño BLoC (Business Logic Component) para lograr un desacoplamiento absoluto de la interfaz respecto a las fuentes de datos.

Plaintext
raiz-proyecto-mobile/lib/
├── domain/                         # Capa de Dominio Pura (Sin dependencias externas)
│   ├── entities/                   # Modelos de negocio inmutables (User, Appointment, Service)
│   └── repositories/               # Interfaces / Contratos abstractos de datos
│
├── application/                    # Capa de Aplicación (Gestión de Estado)
│   ├── auth/                       # BLoC / Eventos / Estados de Autenticación
│   ├── booking/                    # BLoC de Gestión de Citas y Calendario
│   └── catalog/                    # BLoC de Carga de Servicios y Promociones
│
├── infrastructure/                 # Capa de Infraestructura (Implementación física)
│   ├── datasources/
│   │   ├── api_client.dart         # Cliente HTTP (Dio) con interceptores criptográficos
│   │   └── local_database.dart     # Abstracción embebida sobre SQLite local
│   ├── repositories/               # Implementación concreta de las interfaces del dominio
│   └── security/                   # Adaptador nativo de almacenamiento seguro
│
└── presentation/                   # Capa de Presentación (UI y Widgets)
    ├── core/                       # Temas de diseño, paleta de colores y componentes atómicos
    ├── screens/                    # Vistas completas (LoginScreen, HomeScreen, BookingScreen)
    └── widgets/                    # Fragmentos de UI reactivos mapeados a estados BLoC
3. Seguridad y Sesión en Dispositivo: Criptografía en Hardware
Las aplicaciones móviles carecen de mecanismos como las cookies HttpOnly con bandera SameSite disponibles en navegadores web. Para evitar el secuestro del Access Token y el Refresh Token, la aplicación implementa almacenamiento cifrado a nivel de silicio a través del plugin flutter_secure_storage.

3.1. Abstracción del Llavero Nativo
En iOS: Utiliza el servicio del Keychain, donde los datos se cifran empleando una clave generada por hardware que solo se desbloquea bajo el contexto sandbox de la aplicación.

En Android: Utiliza la API KeyStore combinada con cifrado AES-256 en modo GCM. La clave de cifrado se almacena de forma segura en el elemento de hardware dedicado del procesador (Trusted Execution Environment - TEE o StrongBox).

3.2. Implementación del Gestor Cifrado de Tokens (secure_token_manager.dart)
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

  /// Recupera el Refresh Token para procesar el ciclo de rotación automatizado.
  Future<String?> getRefreshToken() async {
    return await _storage.read(key: _refreshTokenKey);
  }

  /// Purga completa del llavero criptográfico (Cierre de sesión seguro).
  Future<void> clearTokens() async {
    await _storage.delete(key: _accessTokenKey);
    await _storage.delete(key: _refreshTokenKey);
  }
}
3.3. Interceptor HTTP Automatizado (Rotación Anti-Overhead)
El cliente HTTP de la aplicación (Dio) incorpora un interceptor que automatiza la inyección del token y procesa la recuperación atómica ante un error 401 Unauthorized. Si el Access Token expira, detiene de forma transparente la cola de peticiones, consume de forma segura el endpoint /api/v1/auth/refresh enviando el Refresh Token, actualiza el almacenamiento cifrado y reintenta la petición original del cliente sin alterar la experiencia visual del usuario.

4. Estrategia de Sincronización Local y Carga Instantánea
Para ofrecer una experiencia fluida al cliente, el catálogo de servicios, especialistas y sucursales debe cargar en menos de 100 milisegundos. La aplicación móvil no realiza peticiones HTTP a la red durante la renderización inicial; consume una base de datos local embebida basada en SQLite (mediante drift o sqflite), sincronizándose en segundo plano mediante un esquema diferencial de marcas de tiempo (timestamps).

4.1. Esquema de Datos de Sincronización Local
Plaintext
       [Inicios de la App] ──> Lee Catálogo desde SQLite Local ──> Renderizado Instantáneo (UI)
                                             │
                                     (Segundo Plano)
                                             v
                         Petición GET /api/v1/catalog/sync?last_sync=TS
                                             │
                     ┌───────────────────────┴───────────────────────┐
                     v                                               v
             ¿Respuesta 200 OK con Cambios?                  ¿Respuesta 304 Not Modified?
                     │                                               │
                     v                                               v
       Aplica Deltas (Inserta/Actualiza)                       No hace nada.
       Actualiza timestamp local en la base de datos.         Mantiene caché intacta.
4.2. Estructura de la Tabla de Control de Sincronización
La base de datos local gestiona una tabla interna denominada sync_metadata:

table_name: Nombre del recurso indexado (services, professionals, branches).

last_synchronized_at: String ISO8601 o marca de tiempo milisegúndica provista por el reloj del backend durante la última descarga exitosa.

4.3. Script de Repositorio de Sincronización Local (catalog_repository.dart)
Dart
import 'local_database.dart';
import 'api_client.dart';

class CatalogRepository {
  final ApiClient _apiClient;
  final LocalDatabase _localDb;

  CatalogRepository(this._apiClient, this._localDb);

  /// Sincroniza el catálogo local con los cambios del backend de forma diferencial.
  Future<void> synchronizeCatalog() async {
    try {
      // 1. Obtener la última marca de tiempo registrada localmente
      final String lastSyncTimestamp = await _localDb.getLastSyncForTable('services');

      // 2. Consumir el endpoint incremental pasándole la cabecera o parámetro condicional
      final response = await _apiClient.get(
        '/api/v1/catalog/sync',
        queryParameters: {'last_sync': lastSyncTimestamp},
      );

      if (response.statusCode == 200) {
        final List<dynamic> upsertDeltas = response.data['deltas']['upserted'];
        final List<dynamic> deleteDeltas = response.data['deltas']['deleted'];
        final String newServerTimestamp = response.data['server_timestamp'];

        // 3. Ejecutar una transacción atómica local en la base de datos SQLite
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

          // Eliminar lógicamente registros purgados del panel administrativo del backend
          for (var id in deleteDeltas) {
            await _localDb.deleteServiceById(id);
          }

          // Actualizar metadatos para la próxima iteración diferencial
          await _localDb.updateSyncMetadata('services', newServerTimestamp);
        });
      }
    } catch (e) {
      // Manejo de resiliencia: Si la red falla o el hosting compartido tarda en responder,
      // la aplicación ignora el fallo de red silenciosamente, manteniendo la usabilidad offline
      // basada estrictamente en los datos de SQLite previamente guardados.
    }
  }
}