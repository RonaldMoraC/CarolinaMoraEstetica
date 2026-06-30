/**
 * Módulo Principal del Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Arquitectura JS modular separada del backend PHP.
 * Comunica con la API REST mediante fetch + JWT.
 * Incluye: ApiClient, Store, AlertManager, ModalManager
 */

'use strict';

/* ============================================================
   1. CLIENTE API REST
   ============================================================ */
class ApiClient {
    constructor(baseUrl = null) {
        // Auto-detectar el subdirectorio base del proyecto.
        // Ejemplo: si SCRIPT_NAME es /CarolinaMoraEstetica/public/index.php,
        // el baseUrl debe ser /CarolinaMoraEstetica/api/v1
        if (baseUrl === null) {
            const scriptName = document.currentScript?.src || '';
            // Alternativa: detectar desde la URL actual de la página
            const currentPath = window.location.pathname;
            // Buscar el patrón /public/ o /admin/ para determinar el proyecto root
            const publicIndex = currentPath.indexOf('/public');
            const adminIndex = currentPath.indexOf('/admin');
            const cutIndex = Math.min(
                publicIndex !== -1 ? publicIndex : currentPath.length,
                adminIndex !== -1 ? adminIndex : currentPath.length
            );
            const projectRoot = cutIndex > 0 ? currentPath.substring(0, cutIndex) : '';
            this.baseUrl = projectRoot + '/api/v1';
        } else {
            this.baseUrl = baseUrl;
        }
    }

    /**
     * Obtiene el token JWT almacenado
     * @returns {string|null}
     */
    getToken() {
        return localStorage.getItem('auth_token');
    }

    /**
     * Construye headers base con autenticación
     * @param {object} extraHeaders
     * @returns {object}
     */
    buildHeaders(method, extraHeaders = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...extraHeaders
        };

        // Agregar override para métodos que no son GET/POST
        if (!['GET', 'POST'].includes(method.toUpperCase())) {
            headers['X-HTTP-Method-Override'] = method.toUpperCase();
        }

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
            headers['X-Authorization'] = `Bearer ${token}`; // Fallback para Apache en XAMPP
        }

        return headers;
    }

    /**
     * Request genérico con manejo de errores estandarizado
     * @param {string} method
     * @param {string} endpoint
     * @param {object|null} body
     * @returns {Promise<object>}
     */
    async request(method, endpoint, body = null) {
        const url = endpoint.startsWith('http')
            ? endpoint
            : `${this.baseUrl}${endpoint}`;

        // Técnica de Method Spoofing (Skill 10: Robustez Perimetral)
        // Si el método es PUT, PATCH o DELETE, lo enviamos como POST para evitar que 
        // Apache/XAMPP bloquee la petición o elimine las cabeceras de autorización.
        // El Router.php detectará la intención real mediante X-HTTP-Method-Override.
        const fetchMethod = ['GET', 'POST'].includes(method.toUpperCase()) ? method.toUpperCase() : 'POST';

        const config = {
            method: fetchMethod,
            headers: this.buildHeaders(method)
        };

        if (body !== null && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            config.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(url, config);

            // Manejo de errores HTTP
            if (response.status === 401) {
                // Token expirado o inválido
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_role');
                
                // Detectar subcarpeta del proyecto para redirección correcta
                const projectRoot = this.baseUrl.replace('/api/v1', '');
                window.location.href = projectRoot + '/login';
                
                throw new Error('Sesión expirada. Redirigiendo al login...');
            }

            if (response.status === 403) {
                throw new Error('No tienes permisos para realizar esta acción.');
            }

            const data = await response.json();

            if (!response.ok) {
                const errorMsg = data.detail || data.title || `Error ${response.status}`;
                throw new Error(errorMsg);
            }

            return data;
        } catch (error) {
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Error de conexión. Verifica tu red e intenta nuevamente.');
            }
            throw error;
        }
    }

    // Métodos semánticos
    async get(endpoint) {
        return this.request('GET', endpoint);
    }

    async post(endpoint, body) {
        return this.request('POST', endpoint, body);
    }

    async put(endpoint, body) {
        return this.request('PUT', endpoint, body);
    }

    async patch(endpoint, body) {
        return this.request('PATCH', endpoint, body);
    }

    async delete(endpoint) {
        return this.request('DELETE', endpoint);
    }
}

/* ============================================================
   2. STORE DE ESTADO GLOBAL (Reactivo con Proxy)
   ============================================================ */
class AdminStore {
    constructor() {
        this.listeners = new Set();

        this.state = new Proxy({
            user: null,
            role: null,
            isOnline: navigator.onLine,
            currentModule: null,
            loading: false,
            error: null,
            // Datos cacheados por módulo
            services: [],
            professionals: [],
            branches: [],
            appointments: [],
            promotions: [],
            reviews: [],
            users: []
        }, {
            set: (target, key, value) => {
                target[key] = value;
                this.notifyListeners();
                return true;
            }
        });

        // Listeners de conectividad
        window.addEventListener('online', () => {
            this.state.isOnline = true;
        });
        window.addEventListener('offline', () => {
            this.state.isOnline = false;
        });

        // Inicializar desde localStorage
        this.initFromStorage();
    }

    initFromStorage() {
        try {
            const storedUser = localStorage.getItem('user_data');
            if (storedUser) {
                this.state.user = JSON.parse(storedUser);
            }
            this.state.role = localStorage.getItem('user_role');
        } catch (e) {
            console.warn('Error al restaurar estado desde storage:', e);
        }
    }

    /**
     * Suscribirse a cambios de estado
     * @param {Function} listener
     * @returns {Function} unsubscribe
     */
    subscribe(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    notifyListeners() {
        this.listeners.forEach(listener => {
            try {
                listener(this.state);
            } catch (e) {
                console.error('Error en listener del store:', e);
            }
        });
    }

    getState() {
        return this.state;
    }

    /**
     * Actualizar un subconjunto del estado
     * @param {object} partialState
     */
    setState(partialState) {
        Object.assign(this.state, partialState);
    }

    /**
     * Verificar si el usuario tiene rol de administrador (role = 1)
     * @returns {boolean}
     */
    isAdmin() {
        const role = this.state.role;
        return role === 'SUPER_ADMIN' || role === 'BRANCH_ADMIN' || role === '1';
    }

    /**
     * Verificar permisos específicos
     * @param {string} requiredRole
     * @returns {boolean}
     */
    hasRole(requiredRole) {
        return this.state.role === requiredRole;
    }
}

/* ============================================================
   3. GESTOR DE ALERTAS
   ============================================================ */
class AlertManager {
    /**
     * Muestra una alerta temporal
     * @param {string} message
     * @param {'success'|'warning'|'danger'|'info'} type
     * @param {number} duration - milisegundos visibles
     */
    show(message, type = 'info', duration = 3000) {
        // Buscar contenedor de alertas existente
        let alertEl = document.querySelector('.alert');

        // Si no existe, crear uno
        if (!alertEl) {
            alertEl = document.createElement('div');
            alertEl.className = 'alert';
            // Insertar al inicio del main content
            const main = document.querySelector('.admin-main') || document.querySelector('main');
            if (main) {
                main.insertBefore(alertEl, main.firstChild);
            } else {
                document.body.insertBefore(alertEl, document.body.firstChild);
            }
        }

        // Configurar la alerta
        alertEl.className = `alert alert--${type} alert--visible`;
        alertEl.textContent = message;

        // Auto-ocultar
        setTimeout(() => {
            alertEl.classList.remove('alert--visible');
            setTimeout(() => {
                alertEl.textContent = '';
            }, 300);
        }, duration);
    }

    success(message, duration = 3000) {
        this.show(message, 'success', duration);
    }

    warning(message, duration = 4000) {
        this.show(message, 'warning', duration);
    }

    error(message, duration = 5000) {
        this.show(message, 'danger', duration);
    }

    info(message, duration = 3000) {
        this.show(message, 'info', duration);
    }
}

/* ============================================================
   4. GESTOR DE MODALES
   ============================================================ */
class ModalManager {
    constructor() {
        this.modalEl = null;
    }

    /**
     * Abre un modal con contenido dinámico
     * @param {object} options
     * @param {string} options.title
     * @param {string} options.body - HTML del cuerpo
     * @param {Array<object>} options.buttons - [{ label, class, onClick }]
     * @param {boolean} options.closeOnOverlay
     */
    open({ title, body, buttons = [], closeOnOverlay = true }) {
        // Crear o reutilizar el modal
        if (!this.modalEl) {
            this.modalEl = document.createElement('div');
            this.modalEl.className = 'modal-overlay';
            this.modalEl.innerHTML = `
                <div class="modal">
                    <div class="modal__header">
                        <h3 class="modal__title"></h3>
                        <button class="modal__close" aria-label="Cerrar">&times;</button>
                    </div>
                    <div class="modal__body"></div>
                    <div class="modal__footer"></div>
                </div>
            `;

            // Evento cerrar
            this.modalEl.querySelector('.modal__close').addEventListener('click', () => this.close());

            // Click en overlay
            this.modalEl.addEventListener('click', (e) => {
                if (e.target === this.modalEl && closeOnOverlay) {
                    this.close();
                }
            });

            // Tecla Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen()) {
                    this.close();
                }
            });

            document.body.appendChild(this.modalEl);
        }

        // Llenar contenido
        this.modalEl.querySelector('.modal__title').textContent = title;
        this.modalEl.querySelector('.modal__body').innerHTML = body;

        // Botones del footer
        const footer = this.modalEl.querySelector('.modal__footer');
        footer.innerHTML = '';
        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.className = `btn ${btn.class || 'btn--primary'}`;
            button.textContent = btn.label;
            button.addEventListener('click', () => {
                if (btn.onClick) btn.onClick();
                if (btn.close !== false) this.close();
            });
            footer.appendChild(button);
        });

        // Mostrar
        this.modalEl.classList.add('modal-overlay--visible');
        document.body.style.overflow = 'hidden';
    }

    close() {
        if (this.modalEl) {
            this.modalEl.classList.remove('modal-overlay--visible');
            document.body.style.overflow = '';
        }
    }

    isOpen() {
        return this.modalEl && this.modalEl.classList.contains('modal-overlay--visible');
    }
}

/* ============================================================
   5. TABLA DINÁMICA
   ============================================================ */
class DynamicTable {
    /**
     * @param {string} selector - Selector del tbody
     */
    constructor(selector) {
        this.tbody = document.querySelector(selector);
        if (!this.tbody) {
            console.warn(`DynamicTable: No se encontró tbody con selector "${selector}"`);
        }
    }

    /**
     * Renderiza filas desde datos
     * @param {Array<object>} data
     * @param {Function} renderRow - (item, index) => string HTML
     */
    render(data, renderRow) {
        if (!this.tbody) return;

        if (!data || data.length === 0) {
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="100%" class="text-center text-muted p-5">
                        No hay registros disponibles.
                    </td>
                </tr>
            `;
            return;
        }

        this.tbody.innerHTML = data.map((item, index) => renderRow(item, index)).join('');
    }

    /**
     * Agrega una fila al inicio
     * @param {string} html
     */
    prependRow(html) {
        if (!this.tbody) return;
        this.tbody.insertAdjacentHTML('afterbegin', html);
    }

    /**
     * Elimina una fila por ID del elemento
     * @param {string} rowId
     */
    removeRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            row.style.transition = 'all 0.3s ease';
            setTimeout(() => row.remove(), 300);
        }
    }
}

/* ============================================================
   6. INICIALIZADOR GLOBAL
   ============================================================ */
const AdminApp = (() => {
    // Instancias singleton
    let api = null;
    let store = null;
    let alerts = null;
    let modal = null;

    return {
        init() {
            api = new ApiClient();
            store = new AdminStore();
            alerts = new AlertManager();
            modal = new ModalManager();

            // Exponer en window para scripts inline
            window.adminApi = api;
            window.adminStore = store;
            window.adminAlerts = alerts;
            window.adminModal = modal;

            // Verificar autenticación
            this.checkAuth();

            console.log('[AdminApp] Panel administrativo inicializado');
        },

        checkAuth() {
            const token = localStorage.getItem('auth_token');
            const role = localStorage.getItem('user_role');

            // Roles de staff válidos para acceso al admin
            const staffRoles = ['SUPER_ADMIN', 'BRANCH_ADMIN', 'RECEPCIONIST', '1'];

            if (!token || !staffRoles.includes(role)) {
                console.warn('[AdminApp] Sin autenticación válida, redirigiendo...');
                // No redirigir inmediatamente para permitir carga de páginas estáticas
                // window.location.href = '/login';
            }
        },

        getApi() { return api; },
        getStore() { return store; },
        getAlerts() { return alerts; },
        getModal() { return modal; }
    };
})();

// Auto-inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    AdminApp.init();
    window.dispatchEvent(new CustomEvent('admin-app-ready'));
});
