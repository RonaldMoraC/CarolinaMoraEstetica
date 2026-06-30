Este documento técnico establece las especificaciones de ingeniería, el diseño de la interfaz y la estrategia de resiliencia desconectada para la Progressive Web App (PWA) de Antigravity 2.0. La aplicación está diseñada para operar simultáneamente en dispositivos móviles de clientes y en terminales de escritorio de las recepcionistas en las sucursales, garantizando la continuidad del negocio incluso ante caídas del enlace de internet (Requerimientos RNF-01, RNF-06).

Para maximizar la velocidad de carga en infraestructuras de hosting compartido (Hostinger) y eliminar el sobrepeso de hidratación de frameworks complejos, la PWA se estructura sobre Vite + Vanilla JS (TypeScript opcional) compilado a componentes nativos desacoplados utilizando Web Components estándar o módulos ESM puros.

1. Árbol de Directorios Estricto
La PWA sigue un patrón de arquitectura desacoplada por componentes lógicos y servicios autónomos de consumo de la API RESTful.

Plaintext
raiz-del-proyecto-pwa/
├── public/
│   ├── favicon.ico
│   ├── icon-192.png
│   ├── icon-512.png
│   └── manifest.json             # Manifiesto de Instalación PWA
├── src/
│   ├── components/               # UI Reutilizable, encapsulada y atómica
│   │   ├── Common/
│   │   │   ├── Navbar.js
│   │   │   └── OfflineToast.js   # Notificador de estado de red en tiempo real
│   │   ├── Booking/
│   │   │   ├── AppointmentCard.js
│   │   │   └── ScheduleSelector.js
│   │   └── Catalog/
│   │       └── ServiceItem.js
│   │
│   ├── core/                     # Lógica central del sistema y orquestación
│   │   ├── app.js                # Enrutador e inicializador global
│   │   ├── store.js              # Gestor de Estado Inmutable (Reactive State)
│   │   └── database.js           # Capa de abstracción sobre IndexedDB local
│   │
│   ├── services/                 # Clientes HTTP y adaptadores de conexión exterior
│   │   ├── api.js                # Instancia base de Fetch con inyección de JWT
│   │   ├── auth.service.js
│   │   └── booking.service.js
│   │
│   └── styles/
│       └── global.css            # Sistema de diseño (CSS Variables, Utilidades)
│
├── index.html                    # Punto de entrada HTML5
├── sw.js                         # Código fuente del Service Worker
└── vite.config.js                # Configuración del bundler ultra-veloz
2. Estrategia Offline y Ciclo de Vida del Service Worker
El Service Worker (sw.js) actúa como un proxy de red perimetral interceptando todas las peticiones salientes del navegador. Su ciclo de vida está diseñado para no bloquear la experiencia de usuario y actualizar los recursos en segundo plano.

2.1. Políticas de Caché Diferenciadas
Cache-First (Estrategia para Assets Estáticos): Aplicada al Core de la Aplicación (HTML, JS compilados, CSS, fuentes e imágenes locales). El Service Worker sirve los archivos directamente desde la caché del navegador, eliminando la latencia de red a cero milisegundos tras la primera visita.

Network-First con Fallback Offline (Estrategia para Datos de la API): Aplicada a las peticiones dinámicas de endpoints /api/v1/* (Catálogos, disponibilidad, perfiles). El sistema intenta consumir la red para garantizar datos frescos de la estética; si la red falla (latencia o desconexión), intercepta el error y extrae el último snapshot válido almacenado en el almacenamiento IndexedDB local.

2.2. Implementación Técnica del Service Worker Puro (sw.js)
JavaScript
declare const self: ServiceWorkerGlobalScope;

const CACHE_CORE_NAME = 'antigravity-core-v2.0';
const ASSETS_TO_CACHE = [
  '/',
  '/index.html',
  '/src/core/app.js',
  '/src/styles/global.css',
  '/public/manifest.json',
  '/public/icon-192.png',
  '/public/icon-512.png'
];

// 1. Fase de Instalación: Pre-caché del esqueleto de la aplicación (App Shell)
self.addEventListener('install', (event: any) => {
  event.waitUntil(
    caches.open(CACHE_CORE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => {
      return self.skipWaiting(); // Forzar la activación inmediata
    })
  );
});

// 2. Fase de Activación: Limpieza de cachés obsoletas de versiones previas
self.addEventListener('activate', (event: any) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_CORE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => {
      return self.clients.claim(); // Tomar control de todas las pestañas abiertas
    })
  );
});

// 3. Intercepción Perimetral (Fetch Proxy)
self.addEventListener('fetch', (event: any) => {
  const requestUrl = new URL(event.request.url);

  // Filtrar peticiones destinadas a la API RESTful (Network-First)
  if (requestUrl.pathname.startsWith('/api/v1/')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Si la respuesta es exitosa, se clona para persistirla si es necesario
          return response;
        })
        .catch(() => {
          // Fallback offline automático manejado por la capa lógica core de la app
          return caches.match('/index.html');
        })
    );
    return;
  }

  // Peticiones estáticas ordinarias (Cache-First)
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request);
    })
  );
});
3. Sincronización en Segundo Plano (Background Sync) mediante IndexedDB
Para las recepcionistas que gestionan la agenda de las cabinas en mostrador, un micro-corte de internet local no puede congelar la operación del negocio. La PWA utiliza IndexedDB como un motor de persistencia transaccional del lado del cliente.

3.1. Arquitectura de Encolamiento de Reservas Locales
Plaintext
[Formulario de Cita] ---> ¿Hay Red? --- (Sí) ---> [API Post Directo] ---> 201 Created
                               |
                             (No)
                               v
                     [Persiste en IndexedDB]
                     [Registra Evento Sync]
                               v
               (El navegador recupera la conexión)
                               v
            [Service Worker despierta Background Sync]
            [Lee cola en IndexedDB -> Envía DTOs a la API]
            [Limpia la base de datos local de forma atómica]
3.2. Implementación de la Capa de Datos Local (src/core/database.js)
JavaScript
export class LocalQueueDB {
  private dbName = 'antigravity_offline_db';
  private version = 1;

  public open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onupgradeneeded = (event: any) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('appointment_queue')) {
          // Tabla/Almacén indexado por clave autoincremental
          db.createObjectStore('appointment_queue', { keyPath: 'id', autoIncrement: true });
        }
      };

      request.onsuccess = (event: any) => resolve(event.target.result);
      request.onerror = (event: any) => reject(event.target.error);
    });
  }

  /**
   * Encola un DTO de reserva de cita en el almacenamiento local fuera de línea.
   */
  public async enqueueAppointment(appointmentDTO: object): Promise<void> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction('appointment_queue', 'readwrite');
      const store = transaction.objectStore('appointment_queue');
      
      const payload = {
        ...appointmentDTO,
        captured_at: new Date().toISOString(),
        correlation_id: crypto.randomUUID() // Propagación del ID correlativo forense desde el frontend
      };

      const request = store.add(payload);
      request.onsuccess = () => resolve();
      request.onerror = (event: any) => reject(event.target.error);
    });
  }
}
4. UI/UX Estricto y Control de Estado Global
4.1. Manifiesto de la Aplicación (public/manifest.json)
Este archivo habilita los criterios de instalación nativa de cromo en dispositivos Android, iOS y Escritorio.

JSON
{
  "short_name": "CarolinaMora",
  "name": "Estética Carolina Mora - Antigravity PWA",
  "icons": [
    {
      "src": "icon-192.png",
      "type": "image/png",
      "sizes": "192x192"
    },
    {
      "src": "icon-512.png",
      "type": "image/png",
      "sizes": "512x512"
    }
  ],
  "start_url": "/?utm_source=pwa",
  "background_color": "#ffffff",
  "theme_color": "#111111",
  "display": "standalone",
  "orientation": "portrait-primary",
  "prefer_related_applications": false
}
4.2. Gestión de Estado Global Inmutable Reactivo (src/core/store.js)
Para evitar acoplamientos y propagaciones descontroladas de estados (prop-drilling), la PWA implementa un contenedor de estado global centralizado basado en proxies reactivos.

JavaScript
class GlobalStore {
  private state: any;
  private listeners: Set<Function> = new Set();

  constructor() {
    this.state = new Proxy({
      user: null,
      appointments: [],
      isOnline: navigator.onLine,
      activeBranch: 1
    }, {
      set: (target: any, key: string, value: any) => {
        target[key] = value;
        this.listeners.forEach((listener) => listener(target));
        return true;
      }
    });

    window.addEventListener('online', () => this.state.isOnline = true);
    window.addEventListener('offline', () => this.state.isOnline = false);
  }

  public getState() {
    return this.state;
  }

  public subscribe(listener: Function): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener); // Función de desuscripción
  }
}

export const store = new GlobalStore();