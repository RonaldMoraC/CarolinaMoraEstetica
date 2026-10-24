Este documento tÃ©cnico establece las especificaciones de ingenierÃ­a, el diseÃ±o de la interfaz y la estrategia de resiliencia desconectada para la Progressive Web App (PWA) de Antigravity 2.0. La aplicaciÃ³n estÃ¡ diseÃ±ada para operar simultÃ¡neamente en dispositivos mÃ³viles de clientes y en terminales de escritorio de las recepcionistas en las sucursales, garantizando la continuidad del negocio incluso ante caÃ­das del enlace de internet (Requerimientos RNF-01, RNF-06).

Para maximizar la velocidad de carga en infraestructuras de hosting compartido (Hostinger) y eliminar el sobrepeso de hidrataciÃ³n de frameworks complejos, la PWA se estructura sobre Vite + Vanilla JS (TypeScript opcional) compilado a componentes nativos desacoplados utilizando Web Components estÃ¡ndar o mÃ³dulos ESM puros.

1. Ãrbol de Directorios Estricto
La PWA sigue un patrÃ³n de arquitectura desacoplada por componentes lÃ³gicos y servicios autÃ³nomos de consumo de la API RESTful.

Plaintext
raiz-del-proyecto-pwa/
â”œâ”€â”€ public/
â”‚   â”œâ”€â”€ favicon.ico
â”‚   â”œâ”€â”€ icon-192.png
â”‚   â”œâ”€â”€ icon-512.png
â”‚   â””â”€â”€ manifest.json             # Manifiesto de InstalaciÃ³n PWA
â”œâ”€â”€ src/
â”‚   â”œâ”€â”€ components/               # UI Reutilizable, encapsulada y atÃ³mica
â”‚   â”‚   â”œâ”€â”€ Common/
â”‚   â”‚   â”‚   â”œâ”€â”€ Navbar.js
â”‚   â”‚   â”‚   â””â”€â”€ OfflineToast.js   # Notificador de estado de red en tiempo real
â”‚   â”‚   â”œâ”€â”€ Booking/
â”‚   â”‚   â”‚   â”œâ”€â”€ AppointmentCard.js
â”‚   â”‚   â”‚   â””â”€â”€ ScheduleSelector.js
â”‚   â”‚   â””â”€â”€ Catalog/
â”‚   â”‚       â””â”€â”€ ServiceItem.js
â”‚   â”‚
â”‚   â”œâ”€â”€ core/                     # LÃ³gica central del sistema y orquestaciÃ³n
â”‚   â”‚   â”œâ”€â”€ app.js                # Enrutador e inicializador global
â”‚   â”‚   â”œâ”€â”€ store.js              # Gestor de Estado Inmutable (Reactive State)
â”‚   â”‚   â””â”€â”€ database.js           # Capa de abstracciÃ³n sobre IndexedDB local
â”‚   â”‚
â”‚   â”œâ”€â”€ services/                 # Clientes HTTP y adaptadores de conexiÃ³n exterior
â”‚   â”‚   â”œâ”€â”€ api.js                # Instancia base de Fetch con inyecciÃ³n de JWT
â”‚   â”‚   â”œâ”€â”€ auth.service.js
â”‚   â”‚   â””â”€â”€ booking.service.js
â”‚   â”‚
â”‚   â””â”€â”€ styles/
â”‚       â””â”€â”€ global.css            # Sistema de diseÃ±o (CSS Variables, Utilidades)
â”‚
â”œâ”€â”€ index.html                    # Punto de entrada HTML5
â”œâ”€â”€ sw.js                         # CÃ³digo fuente del Service Worker
â””â”€â”€ vite.config.js                # ConfiguraciÃ³n del bundler ultra-veloz
2. Estrategia Offline y Ciclo de Vida del Service Worker
El Service Worker (sw.js) actÃºa como un proxy de red perimetral interceptando todas las peticiones salientes del navegador. Su ciclo de vida estÃ¡ diseÃ±ado para no bloquear la experiencia de usuario y actualizar los recursos en segundo plano.

2.1. PolÃ­ticas de CachÃ© Diferenciadas
Cache-First (Estrategia para Assets EstÃ¡ticos): Aplicada al Core de la AplicaciÃ³n (HTML, JS compilados, CSS, fuentes e imÃ¡genes locales). El Service Worker sirve los archivos directamente desde la cachÃ© del navegador, eliminando la latencia de red a cero milisegundos tras la primera visita.

Network-First con Fallback Offline (Estrategia para Datos de la API): Aplicada a las peticiones dinÃ¡micas de endpoints /api/v1/* (CatÃ¡logos, disponibilidad, perfiles). El sistema intenta consumir la red para garantizar datos frescos de la estÃ©tica; si la red falla (latencia o desconexiÃ³n), intercepta el error y extrae el Ãºltimo snapshot vÃ¡lido almacenado en el almacenamiento IndexedDB local.

2.2. ImplementaciÃ³n TÃ©cnica del Service Worker Puro (sw.js)
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

// 1. Fase de InstalaciÃ³n: Pre-cachÃ© del esqueleto de la aplicaciÃ³n (App Shell)
self.addEventListener('install', (event: any) => {
  event.waitUntil(
    caches.open(CACHE_CORE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => {
      return self.skipWaiting(); // Forzar la activaciÃ³n inmediata
    })
  );
});

// 2. Fase de ActivaciÃ³n: Limpieza de cachÃ©s obsoletas de versiones previas
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
      return self.clients.claim(); // Tomar control de todas las pestaÃ±as abiertas
    })
  );
});

// 3. IntercepciÃ³n Perimetral (Fetch Proxy)
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
          // Fallback offline automÃ¡tico manejado por la capa lÃ³gica core de la app
          return caches.match('/index.html');
        })
    );
    return;
  }

  // Peticiones estÃ¡ticas ordinarias (Cache-First)
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request);
    })
  );
});
3. SincronizaciÃ³n en Segundo Plano (Background Sync) mediante IndexedDB
Para las recepcionistas que gestionan la agenda de las cabinas en mostrador, un micro-corte de internet local no puede congelar la operaciÃ³n del negocio. La PWA utiliza IndexedDB como un motor de persistencia transaccional del lado del cliente.

3.1. Arquitectura de Encolamiento de Reservas Locales
Plaintext
[Formulario de Cita] ---> Â¿Hay Red? --- (SÃ­) ---> [API Post Directo] ---> 201 Created
                               |
                             (No)
                               v
                     [Persiste en IndexedDB]
                     [Registra Evento Sync]
                               v
               (El navegador recupera la conexiÃ³n)
                               v
            [Service Worker despierta Background Sync]
            [Lee cola en IndexedDB -> EnvÃ­a DTOs a la API]
            [Limpia la base de datos local de forma atÃ³mica]
3.2. ImplementaciÃ³n de la Capa de Datos Local (src/core/database.js)
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
          // Tabla/AlmacÃ©n indexado por clave autoincremental
          db.createObjectStore('appointment_queue', { keyPath: 'id', autoIncrement: true });
        }
      };

      request.onsuccess = (event: any) => resolve(event.target.result);
      request.onerror = (event: any) => reject(event.target.error);
    });
  }

  /**
   * Encola un DTO de reserva de cita en el almacenamiento local fuera de lÃ­nea.
   */
  public async enqueueAppointment(appointmentDTO: object): Promise<void> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction('appointment_queue', 'readwrite');
      const store = transaction.objectStore('appointment_queue');
      
      const payload = {
        ...appointmentDTO,
        captured_at: new Date().toISOString(),
        correlation_id: crypto.randomUUID() // PropagaciÃ³n del ID correlativo forense desde el frontend
      };

      const request = store.add(payload);
      request.onsuccess = () => resolve();
      request.onerror = (event: any) => reject(event.target.error);
    });
  }
}
4. UI/UX Estricto y Control de Estado Global
4.1. Manifiesto de la AplicaciÃ³n (public/manifest.json)
Este archivo habilita los criterios de instalaciÃ³n nativa de cromo en dispositivos Android, iOS y Escritorio.

JSON
{
  "short_name": "CarolinaMora",
  "name": "EstÃ©tica Carolina Mora - Antigravity PWA",
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
4.2. GestiÃ³n de Estado Global Inmutable Reactivo (src/core/store.js)
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
    return () => this.listeners.delete(listener); // FunciÃ³n de desuscripciÃ³n
  }
}

export const store = new GlobalStore();
