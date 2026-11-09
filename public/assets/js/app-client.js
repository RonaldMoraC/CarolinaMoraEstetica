/**
 * Módulo Principal del Panel Cliente (PWA)
 * Estética Carolina Mora - CarolinaMoraEstetica
 *
 * Arquitectura JS modular separada del backend PHP.
 * Comunica con la API REST mediante fetch + JWT.
 * Incluye: ApiClient, AppStore, AlertManager, ModalManager,
 *          CatalogView, CitasView, ProfileView, AppRouter
 */

'use strict';

/* ============================================================
   0. HTML SANITIZATION (XSS Prevention)
   ============================================================ */
function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/* ============================================================
   1. CLIENTE API REST
   ============================================================ */
class ApiClient {
    constructor(baseUrl = null) {
        if (baseUrl === null) {
            const currentPath = window.location.pathname;
            const segments = currentPath.split('/').filter(Boolean);
            const projectIdx = segments.indexOf('CarolinaMoraEstetica');
            const projectRoot = projectIdx !== -1 ? '/' + segments.slice(0, projectIdx + 1).join('/') : '';
            this.baseUrl = projectRoot + '/api/v1';
        } else {
            this.baseUrl = baseUrl;
        }
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }

    buildHeaders(extraHeaders = {}) {
        const headers = { 'Content-Type': 'application/json', ...extraHeaders };
        const token = this.getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        return headers;
    }

    async request(method, endpoint, body = null, isRetry = false) {
        const url = endpoint.startsWith('http') ? endpoint : this.baseUrl + endpoint;
        const config = { method: method.toUpperCase(), headers: this.buildHeaders() };
        if (body !== null && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            config.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(url, config);

            // Manejo de expiración o token no válido con intento de Refresh Token automático
            if (response.status === 401 && !isRetry && !endpoint.includes('/auth/refresh') && !endpoint.includes('/login')) {
                const refreshed = await this.tryRefreshToken();
                if (refreshed) {
                    return this.request(method, endpoint, body, true);
                }
            }

            if (response.status === 401) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_role');
                localStorage.removeItem('user_name');
                localStorage.removeItem('user_email');
                window.location.href = basePath + '/login';
                return new Promise(() => {});
            }

            if (response.status === 403) {
                throw new Error('No tienes permisos para realizar esta acción.');
            }

            const data = await response.json();

            if (!response.ok) {
                const errorMsg = data.detail || data.title || 'Error ' + response.status;
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

    async tryRefreshToken() {
        try {
            const response = await fetch(this.baseUrl + '/auth/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include' // Envía la cookie HTTP-Only con el Refresh Token
            });

            if (response.ok) {
                const data = await response.json();
                if (data && data.access_token) {
                    localStorage.setItem('auth_token', data.access_token);
                    return true;
                }
            }
            return false;
        } catch (e) {
            return false;
        }
    }

    async get(endpoint) { return this.request('GET', endpoint); }
    async post(endpoint, body) { return this.request('POST', endpoint, body); }
    async put(endpoint, body) { return this.request('PUT', endpoint, body); }
    async patch(endpoint, body) { return this.request('PATCH', endpoint, body); }
    async delete(endpoint) { return this.request('DELETE', endpoint); }
}

/* ============================================================
   2. STORE DE ESTADO GLOBAL
   ============================================================ */
class AppStore {
    constructor() {
        this.listeners = new Set();
        this.state = {
            user: null,
            role: localStorage.getItem('user_role'),
            isOnline: navigator.onLine,
            activeSection: 'Catalog',
            loading: false,
            error: null,
            services: [],
            categories: [],
            appointments: [],
            profile: null
        };

        window.addEventListener('online', () => this.state.isOnline = true);
        window.addEventListener('offline', () => this.state.isOnline = false);
        this.initFromStorage();
    }

    initFromStorage() {
        try {
            const storedName = localStorage.getItem('user_name');
            const storedEmail = localStorage.getItem('user_email');
            if (storedName) this.state.user = { name: storedName, email: storedEmail || '' };
        } catch (e) {
            console.warn('Error al restaurar estado:', e);
        }
    }

    subscribe(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    notifyListeners() {
        this.listeners.forEach(listener => {
            try { listener(this.state); } catch (e) { console.error('Error en listener:', e); }
        });
    }

    setState(partialState) {
        Object.assign(this.state, partialState);
        this.notifyListeners();
    }

    getState() { return this.state; }
}

/* ============================================================
   3. GESTOR DE ALERTAS (Toast)
   ============================================================ */
class AlertManager {
    show(message, type = 'info', duration = 3000) {
        let alertEl = document.getElementById('globalAlert');
        if (!alertEl) return;

        alertEl.className = 'app-alert app-alert--' + type + ' app-alert--visible';
        alertEl.textContent = message;

        setTimeout(() => {
            alertEl.classList.remove('app-alert--visible');
            setTimeout(() => { alertEl.textContent = ''; }, 300);
        }, duration);
    }

    success(message, duration = 3000) { this.show(message, 'success', duration); }
    warning(message, duration = 4000) { this.show(message, 'warning', duration); }
    error(message, duration = 5000) { this.show(message, 'danger', duration); }
    info(message, duration = 3000) { this.show(message, 'info', duration); }
}

/* ============================================================
   4. GESTOR DE MODALES
   ============================================================ */
class ModalManager {
    constructor() { this.modalEl = null; }

    open({ title, body, buttons = [], closeOnOverlay = true }) {
        if (!this.modalEl) {
            this.modalEl = document.createElement('div');
            this.modalEl.className = 'app-modal-overlay';
            this.modalEl.innerHTML = `
                <div class="app-modal">
                    <div class="app-modal__header">
                        <h3 class="app-modal__title"></h3>
                        <button class="app-modal__close" aria-label="Cerrar">&times;</button>
                    </div>
                    <div class="app-modal__body"></div>
                    <div class="app-modal__footer"></div>
                </div>
            `;
            this.modalEl.querySelector('.app-modal__close').addEventListener('click', () => this.close());
            this.modalEl.addEventListener('click', (e) => {
                if (e.target === this.modalEl && closeOnOverlay) this.close();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen()) this.close();
            });
            document.body.appendChild(this.modalEl);
        }

        this.modalEl.querySelector('.app-modal__title').textContent = title;
        this.modalEl.querySelector('.app-modal__body').innerHTML = body;

        const footer = this.modalEl.querySelector('.app-modal__footer');
        footer.innerHTML = '';
        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.className = btn.class || 'px-4 py-2 bg-brand-cyan text-white font-semibold text-sm rounded-xl hover:bg-brand-cyanHover transition';
            button.textContent = btn.label;
            button.addEventListener('click', () => {
                if (btn.onClick) btn.onClick();
                if (btn.close !== false) this.close();
            });
            footer.appendChild(button);
        });

        this.modalEl.classList.add('app-modal-overlay--visible');
        document.body.style.overflow = 'hidden';
    }

    close() {
        if (this.modalEl) {
            this.modalEl.classList.remove('app-modal-overlay--visible');
            document.body.style.overflow = '';
        }
    }

    isOpen() {
        return this.modalEl && this.modalEl.classList.contains('app-modal-overlay--visible');
    }
}

/* ============================================================
   5. SPINNER HELPER
   ============================================================ */
function createSpinner() {
    const wrapper = document.createElement('div');
    wrapper.className = 'app-spinner';
    wrapper.innerHTML = '<svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="20"/></svg>';
    return wrapper;
}

/* ============================================================
   6. CATALOG VIEW
   ============================================================ */
class CatalogView {
    constructor(api, store, alerts, modal) {
        this.api = api;
        this.store = store;
        this.alerts = alerts;
        this.modal = modal;
        this.catalogLoaded = false;
        this.categoriesLoaded = false;
        this.currentCategory = 'todos';
        this.allCategories = [];

        this.SERVICE_ICONS = {
            cabello: '', unas: '', facial: '', cuidado: '',
            colorimetria: '', estilismo: '️', cejas: '',
            'estetica facial y avanzada': '',
            micropigmentacion: '',
            'cejas pestañas y cera': '',
            depilacion: '',
            laser: 'âa¡',
            default: ''
        };

        this.CATEGORY_ICONS = {
            cabello: '', unas: '', facial: '', cuidado: '',
            colorimetria: '', estilismo: '️', cejas: '',
            'estetica facial y avanzada': '',
            micropigmentacion: '',
            'cejas pestañas y cera': '',
            depilacion: '',
            laser: 'âa¡',
            default: ''
        };
    }

    async loadCategories() {
        if (this.categoriesLoaded) return this.allCategories;

        try {
            const result = await this.api.get('/catalog/categories');
            const categories = Array.isArray(result) ? result : (result.data || []);
            this.allCategories = categories;
            this.categoriesLoaded = true;
            this.store.setState({ categories });
            return categories;
        } catch (error) {
            // Fallback: extract from services if categories API fails
            return [];
        }
    }

    async load() {
        const grid = document.getElementById('servicesGrid');
        if (this.catalogLoaded) return;

        grid.innerHTML = '';
        grid.appendChild(createSpinner());

        try {
            // Load categories first (for filter buttons)
            await this.loadCategories();

            const result = await this.api.get('/catalog/services');
            const services = Array.isArray(result) ? result : (result.data || []);
            this.store.setState({ services });

            if (services.length === 0) {
                grid.innerHTML = '<p class="text-center text-slate-400 dark:text-slate-500 py-8 text-sm">No hay servicios disponibles.</p>';
                this.renderCategoryFilters();
                return;
            }

            this.renderCategoryFilters();
            this.render(services);
            this.catalogLoaded = true;
        } catch (error) {
            grid.innerHTML = '<p class="text-center text-red-400 py-8 text-sm">' + escapeHtml(error.message) + '</p>';
        }
    }

    renderCategoryFilters() {
        const container = document.getElementById('categoryFilters');
        container.innerHTML = '';

        // Short labels for categories with long names
        const SHORT_LABELS = {
            'estetica facial y avanzada': 'Facial',
            'micropigmentacion': 'Micropig.',
            'cejas pestañas y cera': 'Cejas/Pestañas',
            'depilacion laser': 'Láser',
        };

        // Normalize for matching: strip accents and commas
        function normalize(str) {
            return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/,/g, '').trim();
        }

        // "Todos" button  spans full width
        const todosBtn = document.createElement('button');
        todosBtn.className = 'cat-filter col-span-2 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 text-sm font-semibold text-slate-700 dark:text-slate-300 text-center'
            + (this.currentCategory === 'todos' ? ' active' : '');
        todosBtn.setAttribute('data-cat', 'todos');
        todosBtn.textContent = 'Todos los servicios';
        todosBtn.addEventListener('click', () => this.filterByCategory('todos'));
        container.appendChild(todosBtn);

        // Category buttons from API
        this.allCategories.forEach(cat => {
            const catName = normalize(cat.name || '');
            const catLabel = SHORT_LABELS[normalize(cat.name || '')] || SHORT_LABELS[catName] || cat.name || 'Categoría';
            const catIcon = this.CATEGORY_ICONS[catName] || this.CATEGORY_ICONS[normalize(cat.name || '')] || this.CATEGORY_ICONS.default;

            const btn = document.createElement('button');
            btn.className = 'cat-filter py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 text-sm font-semibold text-slate-700 dark:text-slate-300 text-center truncate'
                + (this.currentCategory === catName ? ' active' : '');
            btn.setAttribute('data-cat', catName);
            btn.textContent = catIcon + ' ' + catLabel;
            btn.addEventListener('click', () => this.filterByCategory(catName));
            container.appendChild(btn);
        });
    }

    render(services) {
        const grid = document.getElementById('servicesGrid');
        grid.innerHTML = '';

        services.forEach(service => {
            const catRaw = (service.category_name || service.category || service.categoria || '').toLowerCase();
            const cat = catRaw.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/,/g, '').trim();
            const catId = service.category_id || 0;
            const icon = this.SERVICE_ICONS[cat] || this.SERVICE_ICONS[catRaw] || this.SERVICE_ICONS.default;
            const name = service.name || service.nombre || service.service_name || 'Servicio';
            const price = parseFloat(service.base_price || service.price || service.precio || 0).toFixed(2);
            const id = service.service_id || service.id || 0;

            const card = document.createElement('article');
            card.className = 'service-card bg-white dark:bg-white/5 rounded-2xl shadow-sm border border-slate-200 dark:border-white/10 p-4 flex items-center justify-between gap-3';
            card.setAttribute('data-cat', cat);

            const leftDiv = document.createElement('div');
            leftDiv.className = 'flex items-center gap-3';

            const iconDiv = document.createElement('div');
            iconDiv.className = 'w-14 h-14 rounded-xl bg-slate-50 dark:bg-white/5 flex items-center justify-center text-2xl';
            iconDiv.textContent = icon;

            const infoDiv = document.createElement('div');
            const h3 = document.createElement('h3');
            h3.className = 'font-bold text-slate-900 dark:text-white text-sm';
            h3.textContent = name;
            const priceP = document.createElement('p');
            priceP.className = 'text-xs text-slate-400 dark:text-slate-500 font-semibold';
            priceP.textContent = '$' + price;
            infoDiv.appendChild(h3);
            infoDiv.appendChild(priceP);

            leftDiv.appendChild(iconDiv);
            leftDiv.appendChild(infoDiv);

            const reserveBtn = document.createElement('button');
            reserveBtn.className = 'px-4 py-2 bg-brand-cyan text-white font-semibold text-sm rounded-xl hover:bg-brand-cyanHover transition transform hover:-translate-y-0.5 whitespace-nowrap';
            reserveBtn.textContent = 'Reservar';
            reserveBtn.addEventListener('click', () => this.selectService(id, name, price));

            card.appendChild(leftDiv);
            card.appendChild(reserveBtn);
            grid.appendChild(card);
        });
    }

    filterByCategory(cat) {
        this.currentCategory = cat;
        document.querySelectorAll('.cat-filter').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-cat') === cat);
        });
        this.applyFilters();
    }

    filterBySearch() {
        this.applyFilters();
    }

    applyFilters() {
        const search = document.getElementById('searchService').value.toLowerCase().trim();
        const cards = document.querySelectorAll('#servicesGrid .service-card');
        cards.forEach(card => {
            const cardCat = card.getAttribute('data-cat');
            const text = card.querySelector('h3').textContent.toLowerCase();
            const matchCat = this.currentCategory === 'todos' || cardCat === this.currentCategory;
            const matchSearch = !search || text.includes(search);
            card.style.display = (matchCat && matchSearch) ? 'flex' : 'none';
        });
    }

    async selectService(id, name, price) {
        sessionStorage.setItem('servicio_seleccionado_id', String(id));
        sessionStorage.setItem('servicio_seleccionado_nombre', name);
        sessionStorage.setItem('servicio_seleccionado_precio', String(price));

        const service = (this.store.getState().services || []).find(s => (s.id || s.service_id) === id);
        const duration = (service && service.duration_minutes) ? parseInt(service.duration_minutes, 10) : 30;
        this.currentServiceDuration = duration;

        const today = new Date();
        const minDate = today.toISOString().split('T')[0];
        const maxDate = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        // Fetch professionals for this service
        let professionals = [];
        try {
            const result = await this.api.get('/catalog/services/' + id + '/professionals');
            const data = result.data || result;
            professionals = Array.isArray(data) ? data : (data.professionals || []);
        } catch (e) {
            // If API fails, proceed without professionals
        }

        // Build professionals selector HTML
        let professionalsHTML = '';
        if (professionals.length > 0) {
            professionalsHTML = `
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Profesional</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="bookingProfessionals">
                        ${professionals.map(prof => `
                            <button type="button" class="professional-btn px-3 py-2 border border-slate-200 dark:border-white/10 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-brand-cyanLight hover:text-brand-cyan hover:border-brand-cyan transition text-left flex items-center gap-2"
                                data-prof-id="${prof.professional_profile_id}" onclick="AppClient.catalogView.selectProfessional(${prof.professional_profile_id}, '${escapeHtml(prof.display_name)}')">
                                <i class="fas fa-user-md text-brand-cyan"></i>
                                <span class="truncate">${escapeHtml(prof.display_name)}</span>
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            professionalsHTML = `
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Profesional</label>
                    <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-2.5 rounded-xl border border-amber-200 dark:border-amber-800/30">
                        <i class="fas fa-info-circle mr-1"></i> No hay profesionales asignados para este servicio en este momento.
                    </p>
                </div>
            `;
        }

        const bodyHTML = `
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-brand-cyanLight dark:bg-brand-cyanLight/10 rounded-xl border border-brand-cyan/20">
                    <span class="text-2xl">${this.SERVICE_ICONS[(service?.category || '').toLowerCase()] || '✨'}</span>
                    <div class="flex-1 min-w-0">
                        <strong class="text-slate-900 dark:text-white block truncate">${escapeHtml(name)}</strong>
                        <p class="text-xs text-slate-500 dark:text-slate-400">$${escapeHtml(price)} • <i class="far fa-clock ml-1"></i> ${duration} min</p>
                    </div>
                </div>
                ${professionalsHTML}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Fecha</label>
                    <input type="date" id="bookingDate" min="${minDate}" max="${maxDate}" value="${minDate}"
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 font-medium">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Horarios Disponibles</label>
                        <span id="bookingDurationBadge" class="text-[11px] text-slate-400 font-normal">Turnos de ${duration} min</span>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-48 overflow-y-auto p-1" id="bookingTimeSlots">
                        <div class="col-span-3 sm:col-span-4 text-center py-4 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                            <i class="fas fa-hand-pointer mr-1.5 text-brand-cyan"></i> Selecciona un profesional para consultar horarios disponibles
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Notas (opcional)</label>
                    <textarea id="bookingNotes" rows="2" placeholder="Observaciones especiales, alergias o preferencias..."
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 resize-none"></textarea>
                </div>
            </div>
        `;

        this.selectedBookingTime = null;
        this.selectedProfessionalId = null;

        this.modal.open({
            title: 'Reservar: ' + escapeHtml(name),
            body: bodyHTML,
            buttons: [
                { label: 'Cancelar', class: 'px-4 py-2 border-2 border-slate-300 dark:border-white/20 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-100 dark:hover:bg-white/10 transition' },
                {
                    label: 'Confirmar Cita',
                    class: 'px-4 py-2 bg-brand-cyan text-white font-semibold text-sm rounded-xl hover:bg-brand-cyanHover transition shadow-sm',
                    onClick: () => this.confirmBooking(id, name, price),
                    close: false
                }
            ]
        });

        // Configurar listener reactivo para cambio de fecha
        const dateInput = document.getElementById('bookingDate');
        if (dateInput) {
            dateInput.addEventListener('change', () => {
                if (this.selectedProfessionalId) {
                    this.loadAvailableSlots(this.selectedProfessionalId, dateInput.value, this.currentServiceDuration);
                }
            });
        }

        // Si solo hay un profesional asignado, seleccionarlo automáticamente
        if (professionals.length === 1) {
            this.selectProfessional(professionals[0].professional_profile_id, professionals[0].display_name);
        }
    }

    selectProfessional(profId, profName) {
        this.selectedProfessionalId = profId;
        document.querySelectorAll('.professional-btn').forEach(btn => {
            const isActive = parseInt(btn.getAttribute('data-prof-id'), 10) === profId;
            btn.classList.toggle('bg-brand-cyan', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-brand-cyan', isActive);
            btn.classList.toggle('bg-brand-cyanLight', !isActive);
        });

        const dateInput = document.getElementById('bookingDate');
        const selectedDate = dateInput ? dateInput.value : '';
        this.loadAvailableSlots(profId, selectedDate, this.currentServiceDuration);
    }

    async loadAvailableSlots(profId, date, duration) {
        const container = document.getElementById('bookingTimeSlots');
        if (!container) return;

        if (!profId) {
            container.innerHTML = `
                <div class="col-span-3 sm:col-span-4 text-center py-4 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                    <i class="fas fa-hand-pointer mr-1.5 text-brand-cyan"></i> Selecciona un profesional para consultar horarios disponibles
                </div>
            `;
            return;
        }

        if (!date) {
            container.innerHTML = `
                <div class="col-span-3 sm:col-span-4 text-center py-4 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                    <i class="fas fa-calendar-alt mr-1.5 text-brand-cyan"></i> Selecciona una fecha para ver los horarios
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="col-span-3 sm:col-span-4 flex items-center justify-center gap-2 py-4 text-xs text-slate-500 dark:text-slate-400">
                <i class="fas fa-spinner fa-spin text-brand-cyan"></i> Consultando disponibilidad en tiempo real...
            </div>
        `;

        try {
            const endpoint = `/catalog/professionals/${profId}/schedules?date=${encodeURIComponent(date)}&duration=${duration || 30}&branch_id=1`;
            const response = await this.api.get(endpoint);
            const slots = (response && response.data && Array.isArray(response.data.slots))
                ? response.data.slots
                : ((response && Array.isArray(response.slots)) ? response.slots : []);

            if (slots.length === 0) {
                container.innerHTML = `
                    <div class="col-span-3 sm:col-span-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 rounded-xl text-center">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                            <i class="fas fa-clock mr-1"></i> Sin disponibilidad para esta fecha
                        </p>
                        <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">
                            El profesional no tiene turnos libres en este día. Por favor elige otra fecha u otro especialista.
                        </p>
                    </div>
                `;
                this.selectedBookingTime = null;
                return;
            }

            container.innerHTML = slots.map(slot => `
                <button type="button" class="time-slot-btn px-3 py-2 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-brand-cyanLight hover:text-brand-cyan hover:border-brand-cyan transition"
                    data-time="${slot}" onclick="AppClient.catalogView.selectTimeSlot('${slot}')">
                    ${slot}
                </button>
            `).join('');

            // Si ya se había seleccionado una hora y sigue estando disponible, mantenerla seleccionada
            if (this.selectedBookingTime && slots.includes(this.selectedBookingTime)) {
                this.selectTimeSlot(this.selectedBookingTime);
            } else {
                this.selectedBookingTime = null;
            }
        } catch (error) {
            container.innerHTML = `
                <div class="col-span-3 sm:col-span-4 p-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/30 rounded-xl text-center">
                    <p class="text-xs font-semibold text-rose-700 dark:text-rose-300">
                        <i class="fas fa-exclamation-triangle mr-1"></i> No se pudo cargar los horarios
                    </p>
                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">${escapeHtml(error.message)}</p>
                </div>
            `;
            this.selectedBookingTime = null;
        }
    }

    selectTimeSlot(time) {
        this.selectedBookingTime = time;
        document.querySelectorAll('.time-slot-btn').forEach(btn => {
            const isActive = btn.getAttribute('data-time') === time;
            btn.classList.toggle('bg-brand-cyan', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-brand-cyan', isActive);
            btn.classList.toggle('bg-brand-cyanLight', !isActive);
        });
    }

    async confirmBooking(serviceId, name, price) {
        const dateInput = document.getElementById('bookingDate');
        const notesInput = document.getElementById('bookingNotes');

        if (!this.selectedProfessionalId) {
            this.alerts.warning('Por favor selecciona un profesional.');
            return;
        }

        if (!dateInput || !dateInput.value) {
            this.alerts.warning('Por favor selecciona una fecha.');
            return;
        }

        if (!this.selectedBookingTime) {
            this.alerts.warning('Por favor selecciona un horario disponible.');
            return;
        }

        const scheduledDate = dateInput.value;
        const scheduledTime = this.selectedBookingTime;
        const notesVal = notesInput ? notesInput.value.trim() : null;

        this.modal.close();
        this.alerts.info('Asegurando tu cita...');

        try {
            const result = await this.api.post('/booking/appointments', {
                service_id: serviceId,
                professional_profile_id: this.selectedProfessionalId,
                branch_id: 1,
                scheduled_date: scheduledDate,
                scheduled_time: scheduledTime,
                notes: notesVal
            });

            const apptData = result.data || result;
            const apptId = apptData.appointment_id || 'OK';
            const sName = apptData.service_name || name;
            const pName = apptData.professional_name || 'Especialista Asignado';
            const bName = apptData.branch_name || 'Carolina Mora Estética y SPA';
            const dateStr = apptData.fecha_formateada || scheduledDate;
            const timeStr = apptData.scheduled_time || scheduledTime;
            const waUrl = apptData.whatsapp_direct_url || null;
            const notificationQueued = apptData.notification_enqueued;

            this.alerts.success(`¡Cita #${escapeHtml(String(apptId))} confirmada exitosamente!`);

            // Limpieza de estado de reserva
            if (AppClient && AppClient.citasView) {
                AppClient.citasView.citasLoaded = false;
            }
            sessionStorage.removeItem('servicio_seleccionado_id');
            sessionStorage.removeItem('servicio_seleccionado_nombre');
            sessionStorage.removeItem('servicio_seleccionado_precio');

            // Feedback en Tiempo Real (MEDIO-03): Modal de Confirmación y Resumen de Cita
            const successModalBody = `
                <div class="text-center space-y-4 py-2">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-500 mx-auto flex items-center justify-center text-3xl shadow-inner">
                        ✓
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white">¡Tu turno ha sido asegurado!</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Código de reserva: <strong class="text-brand-cyan">#${escapeHtml(String(apptId))}</strong>
                        </p>
                    </div>

                    <div class="bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5 rounded-2xl p-4 text-left space-y-2.5 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200/50 dark:border-white/5">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Tratamiento</span>
                            <span class="font-bold text-slate-800 dark:text-white">${escapeHtml(sName)}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Especialista</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">${escapeHtml(pName)}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Fecha y Hora</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">${escapeHtml(dateStr)} - ${escapeHtml(timeStr)}</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/50 dark:border-white/5">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Sede</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">${escapeHtml(bName)}</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed bg-brand-cyanLight/20 dark:bg-brand-cyan/10 p-3 rounded-xl border border-brand-cyanLight/40">
                        ${notificationQueued ? '📲 Hemos encolado la notificación de confirmación a tu WhatsApp registrado.' : '📲 Puedes abrir los detalles de tu confirmación en WhatsApp con nuestro equipo.'}
                    </p>
                </div>
            `;

            const modalButtons = [];

            if (waUrl) {
                modalButtons.push({
                    label: 'Abrir WhatsApp',
                    class: 'px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition shadow-sm flex items-center justify-center gap-1.5',
                    onClick: () => {
                        window.open(waUrl, '_blank', 'noopener,noreferrer');
                    },
                    close: false
                });
            }

            modalButtons.push({
                label: 'Ver en Mis Citas',
                class: 'px-4 py-2.5 bg-brand-cyan hover:bg-brand-cyanHover text-white font-semibold text-xs rounded-xl transition shadow-sm',
                onClick: () => {
                    if (AppClient && AppClient.router) {
                        AppClient.router.navigate('Citas');
                        if (AppClient.citasView) {
                            AppClient.citasView.load();
                        }
                    }
                }
            });

            modalButtons.push({
                label: 'Cerrar',
                class: 'px-4 py-2.5 border border-slate-200 dark:border-white/10 text-slate-500 text-xs font-semibold rounded-xl hover:bg-slate-100 dark:hover:bg-white/5 transition'
            });

            this.modal.open({
                title: 'Confirmación de Reserva',
                body: successModalBody,
                buttons: modalButtons
            });

        } catch (error) {
            this.alerts.error(escapeHtml(error.message));
        }
    }
}

/* ============================================================
   7. CITAS VIEW
   ============================================================ */
class CitasView {
    constructor(api, store, alerts, modal) {
        this.api = api;
        this.store = store;
        this.alerts = alerts;
        this.modal = modal;
        this.citasLoaded = false;
        this.currentAppTab = 'proximas';
    }

    async load() {
        const container = document.getElementById('appointmentsContainer');
        if (this.citasLoaded) return;

        container.innerHTML = '';
        container.appendChild(createSpinner());

        try {
            const result = await this.api.get('/app/appointments');
            const appointments = Array.isArray(result) ? result : (result.data || []);
            this.store.setState({ appointments });

            if (appointments.length === 0) {
                container.innerHTML = '<p class="text-center text-slate-400 dark:text-slate-500 py-8 text-sm">No registra ninguna cita en el sistema.</p>';
                return;
            }

            this.render(appointments);
            this.citasLoaded = true;
        } catch (error) {
            container.innerHTML = '<p class="text-center text-red-400 py-8 text-sm">' + escapeHtml(error.message) + '</p>';
        }
    }

    render(appointments) {
        const container = document.getElementById('appointmentsContainer');
        container.innerHTML = '';

        appointments.forEach(appt => {
            const status = (appt.status || appt.estado || '').toLowerCase();
            const isPast = ['completada', 'cancelada', 'completed', 'cancelled', 'done'].includes(status);
            const estado = isPast ? 'pasadas' : 'proximas';
            const displayName = appt.service_name || appt.servicio_nombre || appt.service || 'Servicio';
            const date = appt.date || appt.fecha || appt.fecha_formateada || '';
            const time = appt.time || appt.hora || '';
            const price = parseFloat(appt.price || appt.precio || 0).toFixed(2);
            const id = appt.id || appt.appointment_id || 0;

            const card = document.createElement('article');
            card.className = 'appointment-card bg-white dark:bg-white/5 rounded-2xl shadow-sm border border-slate-200 dark:border-white/10 p-5'
                + (isPast ? ' opacity-80' : '');
            card.setAttribute('data-estado', estado);

            // Header row
            const headerDiv = document.createElement('div');
            headerDiv.className = 'flex justify-between items-center mb-3';

            const h3 = document.createElement('h3');
            h3.className = 'font-bold text-slate-900 dark:text-white';
            h3.textContent = displayName;

            const badge = document.createElement('span');
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold ';

            if (status === 'confirmada' || status === 'confirmed') {
                badge.classList.add('bg-brand-cyanLight', 'text-brand-cyan');
                badge.textContent = 'Confirmada';
            } else if (status === 'completada' || status === 'completed') {
                badge.classList.add('bg-green-50', 'text-green-600');
                badge.textContent = 'Completada';
            } else if (status === 'cancelada' || status === 'cancelled') {
                badge.classList.add('bg-red-50', 'text-red-500');
                badge.textContent = 'Cancelada';
            } else {
                badge.classList.add('bg-yellow-50', 'text-yellow-600');
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            headerDiv.appendChild(h3);
            headerDiv.appendChild(badge);
            card.appendChild(headerDiv);

            // Details
            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'text-xs text-slate-600 dark:text-slate-300 space-y-2 mb-4';

            // Especialista / Profesional asignado (MEDIO-01)
            const profName = appt.professional_name || appt.profesional || 'Especialista Asignado';
            const profBio = appt.professional_bio ? ` · ${appt.professional_bio}` : '';
            const profDiv = document.createElement('div');
            profDiv.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5';
            profDiv.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-brand-cyanLight/40 dark:bg-brand-cyan/20 flex items-center justify-center text-brand-cyan font-bold text-sm flex-shrink-0">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Profesional Asignado</p>
                    <p class="font-bold text-slate-800 dark:text-white truncate text-xs">
                        ${escapeHtml(profName)}<span class="font-normal text-slate-400 text-[11px]">${escapeHtml(profBio)}</span>
                    </p>
                </div>
            `;
            detailsDiv.appendChild(profDiv);

            // Grid de información
            const gridInfo = document.createElement('div');
            gridInfo.className = 'grid grid-cols-2 gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 pt-1';
            
            const durationTxt = appt.duration_minutes ? ` (${appt.duration_minutes} min)` : '';
            gridInfo.innerHTML = `
                <div class="flex items-center gap-1.5">
                    <i class="far fa-calendar-alt text-brand-cyan text-xs"></i>
                    <span class="truncate">${escapeHtml(date)}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <i class="far fa-clock text-brand-cyan text-xs"></i>
                    <span>${escapeHtml(time)}${escapeHtml(durationTxt)}</span>
                </div>
                ${appt.branch_name ? `
                <div class="col-span-2 flex items-center gap-1.5 text-slate-400">
                    <i class="fas fa-map-marker-alt text-brand-gold text-xs"></i>
                    <span class="truncate">${escapeHtml(appt.branch_name)}</span>
                </div>
                ` : ''}
                <div class="col-span-2 flex items-center gap-1.5 text-slate-800 dark:text-slate-200 font-bold pt-1.5 border-t border-slate-100 dark:border-white/5">
                    <i class="fas fa-tag text-brand-gold text-xs"></i>
                    <span>Inversión: $${escapeHtml(price)}</span>
                </div>
            `;
            detailsDiv.appendChild(gridInfo);

            // Notas del cliente si existen (MEDIO-02)
            if (appt.notes && appt.notes.trim() !== '') {
                const notesDiv = document.createElement('div');
                notesDiv.className = 'text-[11px] text-slate-500 dark:text-slate-400 italic bg-amber-50/50 dark:bg-amber-950/10 border border-amber-200/40 dark:border-amber-900/30 rounded-lg p-2 flex items-start gap-1.5 mt-1';
                notesDiv.innerHTML = `<i class="fas fa-sticky-note text-amber-500 mt-0.5 text-xs flex-shrink-0"></i> <span class="break-words">Notas: ${escapeHtml(appt.notes)}</span>`;
                detailsDiv.appendChild(notesDiv);
            }

            card.appendChild(detailsDiv);

            // Actions
            if (!isPast) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-white/10 flex-wrap';

                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'px-4 py-2 border-2 border-red-400 text-red-500 text-sm font-semibold rounded-xl hover:bg-red-500 hover:text-white transition';
                cancelBtn.textContent = 'Cancelar';
                cancelBtn.addEventListener('click', () => this.cancelAppointment(id));

                const rescheduleBtn = document.createElement('button');
                rescheduleBtn.className = 'px-4 py-2 bg-brand-cyan text-white text-sm font-semibold rounded-xl hover:bg-brand-cyanHover transition flex items-center gap-1.5';
                rescheduleBtn.innerHTML = '<i class="fas fa-calendar-alt text-xs"></i> Reprogramar';
                // Pasar datos de la cita para precalcular la duración en el modal
                rescheduleBtn.addEventListener('click', () => this.rescheduleAppointment(id, appt));
                actionsDiv.appendChild(cancelBtn);
                actionsDiv.appendChild(rescheduleBtn);
                card.appendChild(actionsDiv);
            } else if (status === 'completada' || status === 'completed') {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-white/10';

                const rateBtn = document.createElement('button');
                rateBtn.className = 'px-4 py-2 bg-brand-gold/15 hover:bg-brand-gold text-brand-gold hover:text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5';
                rateBtn.innerHTML = '⭐ Calificar Servicio';
                rateBtn.addEventListener('click', () => this.openRatingModal(id, displayName));
                actionsDiv.appendChild(rateBtn);
                card.appendChild(actionsDiv);
            }

            container.appendChild(card);
        });

        this.switchTab(this.currentAppTab);
    }

    switchTab(tab) {
        this.currentAppTab = tab;
        const tabProximas = document.getElementById('tabProximas');
        const tabPasadas = document.getElementById('tabPasadas');

        if (tabProximas) tabProximas.classList.toggle('active', tab === 'proximas');
        if (tabPasadas) tabPasadas.classList.toggle('active', tab === 'pasadas');

        const cards = document.querySelectorAll('#appointmentsContainer .appointment-card');
        cards.forEach(card => {
            card.style.display = card.getAttribute('data-estado') === tab ? 'block' : 'none';
        });
    }

    openRatingModal(appointmentId, serviceName) {
        let selectedScore = 5;
        const bodyHTML = `
            <div class="space-y-4">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    ¿Cómo fue tu experiencia con <strong>${escapeHtml(serviceName)}</strong>?
                </p>
                <div class="flex items-center justify-center gap-2 py-2" id="starRatingContainer">
                    ${[1, 2, 3, 4, 5].map(star => `
                        <button type="button" class="star-btn text-2xl transition transform hover:scale-125" data-star="${star}">
                            ⭐
                        </button>
                    `).join('')}
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Comentario (opcional)</label>
                    <textarea id="ratingComments" rows="3" placeholder="Cuéntanos qué te pareció el resultado..."
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 resize-none"></textarea>
                </div>
            </div>
        `;

        this.modal.open({
            title: 'Calificar Cita #' + appointmentId,
            body: bodyHTML,
            buttons: [
                { label: 'Cancelar', class: 'px-4 py-2 border-2 border-slate-300 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-100 transition' },
                {
                    label: 'Enviar Calificación',
                    class: 'px-4 py-2 bg-brand-gold text-white font-semibold text-sm rounded-xl hover:brightness-110 transition',
                    onClick: async () => {
                        const comments = document.getElementById('ratingComments') ? document.getElementById('ratingComments').value.trim() : null;
                        this.modal.close();
                        try {
                            await this.api.post('/crm/ratings', {
                                appointment_id: appointmentId,
                                score: selectedScore,
                                comments: comments
                            });
                            this.alerts.success('¡Muchas gracias por calificar nuestro servicio!');
                        } catch (err) {
                            this.alerts.error(err.message || 'Error al registrar calificación.');
                        }
                    },
                    close: false
                }
            ]
        });

        setTimeout(() => {
            const stars = document.querySelectorAll('.star-btn');
            const updateStars = (val) => {
                selectedScore = val;
                stars.forEach((s, idx) => {
                    s.style.opacity = (idx < val) ? '1' : '0.3';
                });
            };
            stars.forEach((s) => {
                s.addEventListener('click', () => {
                    const val = parseInt(s.getAttribute('data-star'), 10);
                    updateStars(val);
                });
            });
            updateStars(5);
        }, 100);
    }

    cancelAppointment(id) {
        this.modal.open({
            title: 'Cancelar Cita',
            body: `<p class="text-slate-600 dark:text-slate-400">¿Está seguro de que desea cancelar la cita #${escapeHtml(String(id))}? Esta acción no se puede revertir.</p>`,
            buttons: [
                { label: 'No, mantener', class: 'px-4 py-2 border-2 border-slate-300 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-100 transition' },
                {
                    label: 'Sí, cancelar',
                    class: 'px-4 py-2 bg-red-500 text-white text-sm font-semibold rounded-xl hover:bg-red-600 transition',
                    onClick: () => this.doCancel(id),
                    close: false
                }
            ]
        });
    }

    async doCancel(id) {
        this.modal.close();
        this.alerts.info('Procesando cancelación...');

        try {
            await this.api.delete('/booking/appointments/' + id);
            this.alerts.success('Cita cancelada exitosamente.');
            this.citasLoaded = false;
            await this.load();
        } catch (error) {
            this.alerts.error(escapeHtml(error.message));
        }
    }

    rescheduleAppointment(id, apptData) {
        // Calcular la duración de la cita desde los datos disponibles
        const duration = (apptData && apptData.duration_minutes) ? parseInt(apptData.duration_minutes, 10) : 30;
        const serviceName = (apptData && (apptData.service_name || apptData.servicio_nombre || apptData.service)) || 'el servicio';
        const professionalId = (apptData && (apptData.professional_profile_id || apptData.professional_id)) || null;

        const today = new Date();
        const minDate = new Date(today.getTime() + 60000).toISOString().split('T')[0]; // min: hoy
        const maxDate = new Date(today.getTime() + 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]; // max: 60 días

        // Estado interno del modal de reprogramación
        let reschedSelectedTime = null;
        let reschedSelectedDate = minDate;

        const bodyHTML = `
            <div class="space-y-4">
                <!-- Información de la cita -->
                <div class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800/30">
                    <i class="fas fa-calendar-alt text-amber-500 text-xl flex-shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 truncate">Reprogramar: ${escapeHtml(serviceName)}</p>
                        <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">Selecciona una nueva fecha y hora disponible</p>
                    </div>
                </div>

                <!-- Selector de Fecha -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Nueva Fecha</label>
                    <input type="date" id="reschedDate" min="${minDate}" max="${maxDate}" value="${minDate}"
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 font-medium">
                </div>

                <!-- Horarios Disponibles -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Horarios Disponibles</label>
                        <span class="text-[11px] text-slate-400">Turnos de ${duration} min</span>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-44 overflow-y-auto p-1" id="reschedTimeSlots">
                        <div class="col-span-3 sm:col-span-4 flex items-center justify-center gap-2 py-4 text-xs text-slate-500 dark:text-slate-400">
                            <i class="fas fa-spinner fa-spin text-brand-cyan"></i> Consultando disponibilidad...
                        </div>
                    </div>
                </div>

                <!-- Motivo de reprogramación (opcional) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Motivo (opcional)</label>
                    <textarea id="reschedReason" rows="2" placeholder="Ej: Cambio en mis horarios laborales..."
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 resize-none"></textarea>
                </div>
            </div>
        `;

        this.modal.open({
            title: `<i class="fas fa-calendar-alt mr-2 text-brand-cyan"></i>Reprogramar Cita #${id}`,
            body: bodyHTML,
            buttons: [
                {
                    label: 'Cancelar',
                    class: 'px-4 py-2 border-2 border-slate-300 dark:border-white/20 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-100 dark:hover:bg-white/10 transition'
                },
                {
                    label: '<i class="fas fa-check mr-1.5"></i>Confirmar Reprogramación',
                    class: 'px-4 py-2 bg-brand-cyan text-white font-semibold text-sm rounded-xl hover:bg-brand-cyanHover transition shadow-sm',
                    onClick: async () => {
                        const dateVal  = document.getElementById('reschedDate')?.value || '';
                        const reasonEl = document.getElementById('reschedReason');
                        const reason   = reasonEl ? reasonEl.value.trim() : '';

                        if (!dateVal) {
                            this.alerts.warning('Por favor selecciona una fecha.');
                            return;
                        }
                        if (!reschedSelectedTime) {
                            this.alerts.warning('Por favor selecciona un horario disponible.');
                            return;
                        }

                        this.modal.close();
                        this.alerts.info('Reprogramando tu cita...');

                        try {
                            await this.api.patch('/booking/appointments/' + id + '/reschedule', {
                                scheduled_date: dateVal,
                                scheduled_time: reschedSelectedTime,
                                reason: reason || undefined
                            });

                            this.alerts.success(`¡Cita reprogramada para el ${escapeHtml(dateVal)} a las ${escapeHtml(reschedSelectedTime)}!`);
                            this.citasLoaded = false;
                            await this.load();
                        } catch (error) {
                            this.alerts.error(escapeHtml(error.message));
                        }
                    },
                    close: false
                }
            ]
        });

        // Función auxiliar interna para cargar slots de reprogramación
        const loadReschedSlots = async (date) => {
            const container = document.getElementById('reschedTimeSlots');
            if (!container) return;

            if (!professionalId || !date) {
                container.innerHTML = `
                    <div class="col-span-3 sm:col-span-4 p-3 text-center text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                        <i class="fas fa-info-circle mr-1 text-brand-cyan"></i> No se pudo identificar al profesional para consultar su disponibilidad.
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="col-span-3 sm:col-span-4 flex items-center justify-center gap-2 py-4 text-xs text-slate-500 dark:text-slate-400">
                    <i class="fas fa-spinner fa-spin text-brand-cyan"></i> Consultando disponibilidad en tiempo real...
                </div>
            `;

            try {
                const endpoint = `/catalog/professionals/${professionalId}/schedules?date=${encodeURIComponent(date)}&duration=${duration}&branch_id=1`;
                const response = await this.api.get(endpoint);
                const slots = (response && response.data && Array.isArray(response.data.slots))
                    ? response.data.slots
                    : ((response && Array.isArray(response.slots)) ? response.slots : []);

                if (slots.length === 0) {
                    container.innerHTML = `
                        <div class="col-span-3 sm:col-span-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 rounded-xl text-center">
                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                                <i class="fas fa-clock mr-1"></i> Sin disponibilidad para esta fecha
                            </p>
                            <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">El profesional no tiene turnos libres. Elige otra fecha.</p>
                        </div>
                    `;
                    reschedSelectedTime = null;
                    return;
                }

                container.innerHTML = slots.map(slot => `
                    <button type="button"
                        class="resched-slot-btn px-3 py-2 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-brand-cyanLight hover:text-brand-cyan hover:border-brand-cyan transition"
                        data-slot="${slot}"
                        onclick="CitasView._reschedSelectSlot('${slot}')">
                        ${slot}
                    </button>
                `).join('');

                // Mantener la hora previa seleccionada si sigue disponible
                if (reschedSelectedTime && slots.includes(reschedSelectedTime)) {
                    CitasView._reschedSelectSlot(reschedSelectedTime);
                } else {
                    reschedSelectedTime = null;
                }

            } catch (error) {
                container.innerHTML = `
                    <div class="col-span-3 sm:col-span-4 p-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/30 rounded-xl text-center">
                        <p class="text-xs font-semibold text-rose-700 dark:text-rose-300">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Error al consultar horarios
                        </p>
                        <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">${escapeHtml(error.message)}</p>
                    </div>
                `;
                reschedSelectedTime = null;
            }
        };

        // Función estática accesible desde el HTML inline generado dinámicamente
        CitasView._reschedSelectSlot = (slot) => {
            reschedSelectedTime = slot;
            document.querySelectorAll('.resched-slot-btn').forEach(btn => {
                const isActive = btn.getAttribute('data-slot') === slot;
                btn.classList.toggle('bg-brand-cyan', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('border-brand-cyan', isActive);
                btn.classList.toggle('bg-brand-cyanLight', !isActive);
                btn.classList.toggle('text-brand-cyan', !isActive && false); // reset
            });
        };

        // Cargar slots al abrir el modal (fecha inicial = hoy)
        setTimeout(() => {
            loadReschedSlots(minDate);

            // Listener reactivo para cambio de fecha
            const dateInput = document.getElementById('reschedDate');
            if (dateInput) {
                dateInput.addEventListener('change', (e) => {
                    reschedSelectedTime = null;
                    loadReschedSlots(e.target.value);
                });
            }
        }, 80);
    }
}

/* ============================================================
   8. PROFILE VIEW
   ============================================================ */
class ProfileView {
    constructor(api, store, alerts) {
        this.api = api;
        this.store = store;
        this.alerts = alerts;
        this.profileLoaded = false;
    }

    async load() {
        if (this.profileLoaded) return;

        try {
            const result = await this.api.get('/auth/me');
            const data = result.data || result;
            const user = data.user || data;

            const firstName = user.first_name || '';
            const lastName = user.last_name || '';
            const name = user.name || (firstName + ' ' + lastName).trim() || user.username || user.email || '';
            const email = user.email || '';
            const phone = user.phone || '';

            document.getElementById('profileFirstName').value = firstName;
            document.getElementById('profileLastName').value = lastName;
            document.getElementById('profileCorreo').value = email;
            document.getElementById('profileTelefono').value = phone;
            document.getElementById('profileName').textContent = name || 'Cliente';
            document.getElementById('headerUserName').textContent = firstName || name.split(' ')[0] || 'Cliente';

            // Cargar Ficha de Salud y Preferencias (MEDIO-04)
            const allergies = user.medical_notes_allergies || '';
            const preferences = user.preferences || user.consumption_preferences || '';
            const allergiesEl = document.getElementById('profileAllergies');
            const preferencesEl = document.getElementById('profilePreferences');
            if (allergiesEl) allergiesEl.value = allergies;
            if (preferencesEl) preferencesEl.value = preferences;

            localStorage.setItem('user_name', name);
            localStorage.setItem('user_email', email);
            localStorage.setItem('user_allergies', allergies);
            localStorage.setItem('user_preferences', preferences);

            this.store.setState({ profile: user, user: { name, email, allergies, preferences } });
            this.profileLoaded = true;
        } catch (error) {
            // Fallback to localStorage if API fails
            const name = localStorage.getItem('user_name') || '';
            const email = localStorage.getItem('user_email') || '';
            const allergies = localStorage.getItem('user_allergies') || '';
            const preferences = localStorage.getItem('user_preferences') || '';

            document.getElementById('profileFirstName').value = name.split(' ')[0] || '';
            document.getElementById('profileLastName').value = name.split(' ').slice(1).join(' ') || '';
            document.getElementById('profileCorreo').value = email;
            document.getElementById('profileName').textContent = name || 'Cliente';
            document.getElementById('headerUserName').textContent = name.split(' ')[0] || 'Cliente';

            const allergiesEl = document.getElementById('profileAllergies');
            const preferencesEl = document.getElementById('profilePreferences');
            if (allergiesEl) allergiesEl.value = allergies;
            if (preferencesEl) preferencesEl.value = preferences;

            this.profileLoaded = true;
        }
    }

    async save() {
        const btnGuardar = document.getElementById('btnGuardarPerfil');

        const newFirstName = document.getElementById('profileFirstName').value.trim();
        const newLastName = document.getElementById('profileLastName').value.trim();
        const newPhone = document.getElementById('profileTelefono').value.trim();
        const newEmail = document.getElementById('profileCorreo').value.trim();
        const allergiesEl = document.getElementById('profileAllergies');
        const preferencesEl = document.getElementById('profilePreferences');
        const newAllergies = allergiesEl ? allergiesEl.value.trim() : '';
        const newPreferences = preferencesEl ? preferencesEl.value.trim() : '';

        // Validaciones robustas del lado del cliente
        if (newFirstName.length < 2 || newLastName.length < 2) {
            this.alerts.error('El nombre y el apellido deben tener al menos 2 caracteres.');
            return;
        }

        if (newPhone && !/^[0-9+\-\s()]{7,20}$/.test(newPhone)) {
            this.alerts.error('El número de teléfono ingresado no tiene un formato válido.');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
            this.alerts.error('El correo electrónico ingresado no es válido.');
            return;
        }

        const newName = (newFirstName + ' ' + newLastName).trim();

        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Actualizando...';

        try {
            await this.api.patch('/auth/me', {
                first_name: newFirstName,
                last_name: newLastName,
                email: newEmail,
                phone: newPhone,
                medical_notes_allergies: newAllergies,
                preferences: newPreferences
            });

            localStorage.setItem('user_name', newName);
            localStorage.setItem('user_email', newEmail);
            localStorage.setItem('user_allergies', newAllergies);
            localStorage.setItem('user_preferences', newPreferences);
            document.getElementById('profileName').textContent = newName;
            document.getElementById('headerUserName').textContent = newFirstName || newName;

            this.alerts.success('¡Perfil y ficha estética actualizados con éxito!');
        } catch (error) {
            // Fallback: save locally if API not available
            localStorage.setItem('user_name', newName);
            localStorage.setItem('user_email', newEmail);
            localStorage.setItem('user_allergies', newAllergies);
            localStorage.setItem('user_preferences', newPreferences);
            document.getElementById('profileName').textContent = newName;
            document.getElementById('headerUserName').textContent = newFirstName || newName;

            this.alerts.warning('Perfil guardado localmente. La sincronización con el servidor estará disponible próximamente.');
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar Cambios';
        }
    }
}

/* ============================================================
   9. APP ROUTER (Section Navigation)
   ============================================================ */
class AppRouter {
    constructor(catalogView, citasView, profileView, store) {
        this.catalogView = catalogView;
        this.citasView = citasView;
        this.profileView = profileView;
        this.store = store;
        this.sections = {
            Catalog: document.getElementById('sectionCatalog'),
            Citas: document.getElementById('sectionCitas'),
            Perfil: document.getElementById('sectionPerfil')
        };
        this.titles = { Catalog: 'Servicios', Citas: 'Mis Citas', Perfil: 'Mi Perfil' };
    }

    navigate(section) {
        // Hide all sections
        Object.values(this.sections).forEach(s => {
            s.classList.remove('visible');
            s.classList.add('hidden');
        });

        // Show target
        if (this.sections[section]) {
            this.sections[section].classList.remove('hidden');
            this.sections[section].classList.add('visible');
        }

        // Update header
        const headerTitle = document.getElementById('headerTitle');
        if (headerTitle) headerTitle.textContent = this.titles[section] || 'Servicios';

        // Update nav
        const navItems = document.querySelectorAll('.nav-item');
        const sectionIndex = { Catalog: 0, Citas: 1, Perfil: 2 };
        navItems.forEach((item, i) => {
            item.classList.toggle('active', i === sectionIndex[section]);
        });

        // Lazy-load
        if (section === 'Catalog' && !this.catalogView.catalogLoaded) this.catalogView.load();
        if (section === 'Citas' && !this.citasView.citasLoaded) this.citasView.load();
        if (section === 'Perfil' && !this.profileView.profileLoaded) this.profileView.load();

        this.store.setState({ activeSection: section });
    }
}

/* ============================================================
   10. OFFLINE INDICATOR
   ============================================================ */
class OfflineIndicator {
    constructor() {
        this.bar = document.createElement('div');
        this.bar.className = 'app-offline-bar';
        this.bar.textContent = 'Sin conexión  Los datos pueden no estar actualizados';
        document.body.appendChild(this.bar);

        window.addEventListener('offline', () => this.show());
        window.addEventListener('online', () => this.hide());

        if (!navigator.onLine) this.show();
    }

    show() { this.bar.classList.add('app-offline-bar--visible'); }
    hide() { this.bar.classList.remove('app-offline-bar--visible'); }
}

/* ============================================================
   11. APP INITIALIZER (Singleton)
   ============================================================ */
const AppClient = (() => {
    let api, store, alerts, modal, catalogView, citasView, profileView, router, offline;

    return {
        init() {
            api = new ApiClient();
            store = new AppStore();
            alerts = new AlertManager();
            modal = new ModalManager();

            catalogView = new CatalogView(api, store, alerts, modal);
            citasView = new CitasView(api, store, alerts, modal);
            profileView = new ProfileView(api, store, alerts);
            router = new AppRouter(catalogView, citasView, profileView, store);
            offline = new OfflineIndicator();

            AppClient.catalogView = catalogView;
            AppClient.citasView = citasView;
            AppClient.profileView = profileView;

            // Bind bottom nav buttons
            document.querySelectorAll('.nav-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const section = btn.getAttribute('data-section');
                    if (section) router.navigate(section);
                });
            });

            // Bind tab buttons
            const tabProximas = document.getElementById('tabProximas');
            const tabPasadas = document.getElementById('tabPasadas');
            if (tabProximas) tabProximas.addEventListener('click', () => citasView.switchTab('proximas'));
            if (tabPasadas) tabPasadas.addEventListener('click', () => citasView.switchTab('pasadas'));

            // Bind search input
            const searchInput = document.getElementById('searchService');
            if (searchInput) searchInput.addEventListener('input', () => catalogView.filterBySearch());

            // Bind profile form
            const profileForm = document.getElementById('profileForm');
            if (profileForm) profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                profileView.save();
            });

            // Bind logout
            const logoutBtns = document.querySelectorAll('[data-action="logout"]');
            logoutBtns.forEach(btn => btn.addEventListener('click', async () => {
                try {
                    await api.post('/auth/logout', {});
                } catch (e) {
                    // Silencioso en caso de pérdida de conexión
                }
                localStorage.clear();
                window.location.href = basePath + '/login';
            }));

            // Initial load respetando data-active-section del layout
            const initialSection = document.body.getAttribute('data-active-section') || 'Catalog';
            router.navigate(initialSection);

            console.log('[AppClient] Panel cliente inicializado');
        },

        getApi() { return api; },
        getStore() { return store; },
        getAlerts() { return alerts; },
        getModal() { return modal; },
        getRouter() { return router; },
        catalogView: null,
        citasView: null,
        profileView: null
    };
})();

document.addEventListener('DOMContentLoaded', () => AppClient.init());

