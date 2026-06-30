/**
 * Módulo Principal del Panel Cliente (PWA)
 * Estética Carolina Mora - Antigravity v2.0
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

    async request(method, endpoint, body = null) {
        const url = endpoint.startsWith('http') ? endpoint : this.baseUrl + endpoint;
        const config = { method: method.toUpperCase(), headers: this.buildHeaders() };
        if (body !== null && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            config.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(url, config);

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
            cabello: '💇‍♀️', unas: '💅', facial: '🧖‍♀️', cuidado: '🧴',
            colorimetria: '🎨', estilismo: '✂️', cejas: '👁️',
            'estetica facial y avanzada': '🧖‍♀️',
            micropigmentacion: '🎨',
            'cejas pestañas y cera': '👁️',
            depilacion: '🧴',
            laser: '⚡',
            default: '✨'
        };

        this.CATEGORY_ICONS = {
            cabello: '💇‍♀️', unas: '💅', facial: '🧖‍♀️', cuidado: '🧴',
            colorimetria: '🎨', estilismo: '✂️', cejas: '👁️',
            'estetica facial y avanzada': '🧖‍♀️',
            micropigmentacion: '🎨',
            'cejas pestañas y cera': '👁️',
            depilacion: '🧴',
            laser: '⚡',
            default: '✨'
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

        // "Todos" button — spans full width
        const todosBtn = document.createElement('button');
        todosBtn.className = 'cat-filter col-span-2 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 text-sm font-semibold text-slate-700 dark:text-slate-300 text-center'
            + (this.currentCategory === 'todos' ? ' active' : '');
        todosBtn.setAttribute('data-cat', 'todos');
        todosBtn.textContent = '✨ Todos los servicios';
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

        const today = new Date();
        const minDate = today.toISOString().split('T')[0];
        const maxDate = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        const timeSlots = this.generateTimeSlots();

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
                    <div class="grid grid-cols-1 gap-2" id="bookingProfessionals">
                        ${professionals.map(prof => `
                            <button class="professional-btn px-3 py-2 border border-slate-200 dark:border-white/10 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-brand-cyanLight hover:text-brand-cyan hover:border-brand-cyan transition text-left"
                                data-prof-id="${prof.professional_profile_id}" onclick="AppClient.catalogView.selectProfessional(${prof.professional_profile_id}, '${escapeHtml(prof.display_name)}')">
                                👩‍⚕️ ${escapeHtml(prof.display_name)}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            professionalsHTML = `
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Profesional</label>
                    <p class="text-xs text-slate-400 py-2">No hay profesionales disponibles para este servicio en este momento.</p>
                </div>
            `;
        }

        const bodyHTML = `
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-brand-cyanLight rounded-xl">
                    <span class="text-2xl">${this.SERVICE_ICONS[(this.store.getState().services.find(s => (s.id || s.service_id) === id)?.category || '').toLowerCase()] || '✨'}</span>
                    <div>
                        <strong class="text-slate-900 dark:text-white">${escapeHtml(name)}</strong>
                        <p class="text-xs text-slate-400">$${escapeHtml(price)}</p>
                    </div>
                </div>
                ${professionalsHTML}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Fecha</label>
                    <input type="date" id="bookingDate" min="${minDate}" max="${maxDate}" value="${minDate}"
                        class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Hora</label>
                    <div class="grid grid-cols-4 gap-2" id="bookingTimeSlots">
                        ${timeSlots.map(slot => `
                            <button class="time-slot-btn px-3 py-2 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-brand-cyanLight hover:text-brand-cyan hover:border-brand-cyan transition"
                                data-time="${slot}" onclick="AppClient.catalogView.selectTimeSlot('${slot}')">
                                ${slot}
                            </button>
                        `).join('')}
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Notas (opcional)</label>
                    <textarea id="bookingNotes" rows="2" placeholder="Observaciones especiales..."
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
                { label: 'Cancelar', class: 'px-4 py-2 border-2 border-slate-300 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-100 transition' },
                {
                    label: 'Confirmar Cita',
                    class: 'px-4 py-2 bg-brand-cyan text-white font-semibold text-sm rounded-xl hover:bg-brand-cyanHover transition',
                    onClick: () => this.confirmBooking(id, name, price),
                    close: false
                }
            ]
        });
    }

    selectProfessional(profId, profName) {
        this.selectedProfessionalId = profId;
        document.querySelectorAll('.professional-btn').forEach(btn => {
            const isActive = parseInt(btn.getAttribute('data-prof-id')) === profId;
            btn.classList.toggle('bg-brand-cyan', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-brand-cyan', isActive);
            btn.classList.toggle('bg-brand-cyanLight', !isActive);
        });
    }

    generateTimeSlots() {
        const slots = [];
        for (let h = 8; h <= 18; h++) {
            for (let m = 0; m < 60; m += 30) {
                if (h === 18 && m > 0) break;
                slots.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
            }
        }
        return slots;
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
            this.alerts.warning('Por favor selecciona una hora.');
            return;
        }

        this.modal.close();
        this.alerts.info('Creando cita...');

        try {
            const result = await this.api.post('/booking/appointments', {
                service_id: serviceId,
                professional_profile_id: this.selectedProfessionalId,
                branch_id: 1,
                scheduled_date: dateInput.value,
                scheduled_time: this.selectedBookingTime,
                notes: notesInput ? notesInput.value.trim() : null
            });

            this.alerts.success('¡Cita creada exitosamente! Fecha: ' + escapeHtml(result.scheduled_date) + ' a las ' + escapeHtml(result.scheduled_time));

            sessionStorage.removeItem('servicio_seleccionado_id');
            sessionStorage.removeItem('servicio_seleccionado_nombre');
            sessionStorage.removeItem('servicio_seleccionado_precio');
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
            detailsDiv.className = 'text-xs text-slate-500 dark:text-slate-400 font-semibold space-y-1 mb-3';

            const dateP = document.createElement('p');
            dateP.textContent = '📅 ' + date;
            const timeP = document.createElement('p');
            timeP.textContent = '⏰ ' + time;
            const priceP = document.createElement('p');
            priceP.className = 'text-slate-700 dark:text-slate-300';
            priceP.textContent = '💰 Costo: $' + price;

            detailsDiv.appendChild(dateP);
            detailsDiv.appendChild(timeP);
            detailsDiv.appendChild(priceP);
            card.appendChild(detailsDiv);

            // Actions for non-past appointments
            if (!isPast) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-white/10';

                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'px-4 py-2 border-2 border-red-400 text-red-500 text-sm font-semibold rounded-xl hover:bg-red-500 hover:text-white transition';
                cancelBtn.textContent = 'Cancelar';
                cancelBtn.addEventListener('click', () => this.cancelAppointment(id));

                const rescheduleBtn = document.createElement('button');
                rescheduleBtn.className = 'px-4 py-2 bg-brand-cyan text-white text-sm font-semibold rounded-xl hover:bg-brand-cyanHover transition';
                rescheduleBtn.textContent = 'Reprogramar';
                rescheduleBtn.addEventListener('click', () => this.rescheduleAppointment(id));

                actionsDiv.appendChild(cancelBtn);
                actionsDiv.appendChild(rescheduleBtn);
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

    cancelAppointment(id) {
        this.modal.open({
            title: 'Cancelar Cita',
            body: '<p class="text-slate-600 dark:text-slate-400">¿Está seguro de que desea cancelar esta cita? Esta acción no se puede revertir.</p>',
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

    rescheduleAppointment(id) {
        sessionStorage.setItem('reprogramar_cita_id', String(id));
        AppClient.getRouter().navigate('Catalog');
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

            localStorage.setItem('user_name', name);
            localStorage.setItem('user_email', email);

            this.store.setState({ profile: user, user: { name, email } });
            this.profileLoaded = true;
        } catch (error) {
            // Fallback to localStorage if API fails
            const name = localStorage.getItem('user_name') || '';
            const email = localStorage.getItem('user_email') || '';

            document.getElementById('profileFirstName').value = name.split(' ')[0] || '';
            document.getElementById('profileLastName').value = name.split(' ').slice(1).join(' ') || '';
            document.getElementById('profileCorreo').value = email;
            document.getElementById('profileName').textContent = name || 'Cliente';
            document.getElementById('headerUserName').textContent = name.split(' ')[0] || 'Cliente';

            this.profileLoaded = true;
        }
    }

    async save() {
        const btnGuardar = document.getElementById('btnGuardarPerfil');
        const newFirstName = document.getElementById('profileFirstName').value.trim();
        const newLastName = document.getElementById('profileLastName').value.trim();
        const newPhone = document.getElementById('profileTelefono').value.trim();
        const newEmail = document.getElementById('profileCorreo').value.trim();
        const newName = (newFirstName + ' ' + newLastName).trim();

        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Actualizando...';

        try {
            await this.api.patch('/auth/me', { first_name: newFirstName, last_name: newLastName, email: newEmail, phone: newPhone });

            localStorage.setItem('user_name', newName);
            localStorage.setItem('user_email', newEmail);
            document.getElementById('profileName').textContent = newName;
            document.getElementById('headerUserName').textContent = newFirstName || newName;

            this.alerts.success('¡Perfil actualizado con éxito!');
        } catch (error) {
            // Fallback: save locally if API not available
            localStorage.setItem('user_name', newName);
            localStorage.setItem('user_email', newEmail);
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
        this.bar.textContent = 'Sin conexión — Los datos pueden no estar actualizados';
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
            logoutBtns.forEach(btn => btn.addEventListener('click', () => {
                localStorage.clear();
                window.location.href = basePath + '/login';
            }));

            // Initial load
            router.navigate('Catalog');

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
