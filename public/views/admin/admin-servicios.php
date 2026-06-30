<?php declare(strict_types=1);
/**
 * Gestión de Servicios - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * CRUD de servicios del catálogo de la estética.
 * Todos los datos provienen de la base de datos real vía API.
 * NO hay datos simulados. Si la BD está vacía → se muestra "No hay servicios".
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Inventario de Servicios';
$pageSubtitle = 'Crea, edita o administra la oferta comercial visible para los clientes.';
$activeModule = 'servicios';

$extraCSS = <<<'CSS'
.service-form-aside { position: sticky; top: 24px; }
.search-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; }
.search-bar input { flex: 1; }
.pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding: 8px 0; }
.pagination-bar .page-info { font-size: 0.9em; color: var(--color-muted); }
.pagination-bar .page-controls { display: flex; gap: 8px; }
.pagination-bar .page-controls button { padding: 6px 14px; }
CSS;

ob_start();
?>

<!-- Barra de búsqueda -->
<div class="search-bar">
    <input type="text" id="searchInput" class="form-control" placeholder="Buscar servicio por nombre...">
    <button type="button" class="btn btn--primary btn--sm" onclick="buscarServicios()">Buscar</button>
    <button type="button" class="btn btn--outline btn--sm" onclick="limpiarBusqueda()">Limpiar</button>
</div>

<div class="layout-split">
    <!-- Tabla de servicios -->
    <section class="card" style="flex: 2;">
        <div class="table-container">
            <table class="table" id="tablaServicios">
                <thead>
                    <tr>
                        <th>Nombre del Servicio</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="serviciosTbody">
                    <tr>
                        <td colspan="6" class="text-center text-muted p-5">Cargando servicios...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pagination-bar" id="paginationBar">
            <span class="page-info" id="pageInfo">—</span>
            <div class="page-controls" id="pageControls"></div>
        </div>
    </section>

    <!-- Formulario lateral -->
    <aside class="card service-form-aside" style="flex: 1;">
        <h3 class="card__title" id="formTitle">Agregar Nuevo Servicio</h3>
        <form id="servicioForm" novalidate>
            <input type="hidden" id="servicio_id" value="">

            <div class="form-group">
                <label for="nombre" class="form-label">Nombre del Servicio</label>
                <input type="text" id="nombre" class="form-control" placeholder="Ej. Pedicure Spa" required maxlength="150">
            </div>

            <div class="form-group">
                <label for="categoria" class="form-label">Categoría</label>
                <select id="categoria" class="form-control" required>
                    <option value="">Seleccionar categoría...</option>
                </select>
            </div>

            <div class="form-group">
                <label for="precio" class="form-label">Precio ($)</label>
                <input type="number" step="0.01" min="0" id="precio" class="form-control" placeholder="Ej. 20.00" required>
            </div>

            <div class="form-group">
                <label for="duracion" class="form-label">Duración (Minutos)</label>
                <select id="duracion" class="form-control" required>
                    <option value="">Seleccionar duración...</option>
                    <option value="15">15 minutos</option>
                    <option value="30">30 minutos</option>
                    <option value="45">45 minutos</option>
                    <option value="60">60 minutos</option>
                    <option value="90">90 minutos</option>
                    <option value="120">120 minutos</option>
                </select>
            </div>

            <div class="form-group">
                <label for="descripcion" class="form-label">Descripción (opcional)</label>
                <textarea id="descripcion" class="form-control" rows="3" placeholder="Breve descripción del servicio..." maxlength="500"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn--outline" style="flex: 1;" onclick="limpiarFormulario()">Limpiar</button>
                <button type="submit" class="btn btn--primary" style="flex: 1;" id="btnGuardar">Guardar</button>
            </div>
        </form>
    </aside>
</div>

<?php
$pageContent = ob_get_clean();

// ── JS específico — Se inyecta via $extraJS dentro del <body> ─────
$extraJS = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    // ── Estado local ────────────────────────────────────────────
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let perPage = 15;
    let currentSearch = '';
    let editandoId = null;

    // ── Cargar datos al iniciar ─────────────────────────────────
    cargarCategorias(); // Cargar categorías primero
    cargarServicios();

    // ── Debounce en la barra de búsqueda ──────────────────────
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(window._searchTimeout);
        window._searchTimeout = setTimeout(() => {
            currentSearch = e.target.value.trim();
            currentPage = 1;
            cargarServicios();
        }, 400);
    });

    // ── Submit del formulario ──────────────────────────────────
    document.getElementById('servicioForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!api) { if (alerts) alerts.error('No hay conexión con la API.'); return; }

        const payload = {
            name: document.getElementById('nombre').value.trim(),
            category_id: parseInt(document.getElementById('categoria').value, 10),
            base_price: parseFloat(document.getElementById('precio').value),
            duration_minutes: parseInt(document.getElementById('duracion').value, 10),
            description: document.getElementById('descripcion').value.trim()
        };

        if (!payload.name) { alerts.error('El nombre del servicio es obligatorio.'); return; }
        if (!payload.category_id) { alerts.error('Seleccione una categoría.'); return; }
        if (isNaN(payload.base_price) || payload.base_price <= 0) { alerts.error('El precio debe ser mayor a 0.'); return; }
        if (!payload.duration_minutes || payload.duration_minutes <= 0) { alerts.error('Seleccione una duración válida.'); return; }

        try {
            let response;
            if (editandoId) {
                response = await api.put('/catalog/services/' + editandoId, payload);
            } else {
                response = await api.post('/catalog/services', payload);
            }
            
            if (!response.success) {
                throw new Error(response.detail || 'No se pudo actualizar el servicio.');
            }

            alerts.success(editandoId ? 'Servicio actualizado correctamente.' : 'Nuevo servicio creado.');
            limpiarFormulario();
            await cargarServicios();

        } catch (error) {
            alerts.error(error.message || 'Error al guardar el servicio.');
        }
    });

    // ── Funciones de carga ──────────────────────────────────────

    async function cargarServicios() {
        try {
            if (!api) { renderEmptyState(); return; }

            const queryParams = '?page=' + currentPage + '&per_page=' + perPage + '&search=' + encodeURIComponent(currentSearch);
            const response = await api.get('/catalog/services' + queryParams);

            if (response.success) {
                const servicios = response.data ?? [];
                const meta = response.meta ?? {};
                totalPages = meta.total_pages ?? 1;
                totalRecords = meta.total_records ?? 0;
                currentPage = meta.current_page ?? 1;
                renderServicios(servicios);
                renderPaginacion();
                return;
            }
        } catch (error) {
            console.warn('[Servicios] Error:', error.message);
        }
        renderEmptyState();
    }

    // ── Cargar categorías para el select ──────────────────────
    let categorias = []; // Almacenar categorías para uso posterior
    async function cargarCategorias() {
        try {
            if (!api) { console.warn('[Servicios] API no disponible para categorías.'); return; }
            const response = await api.get('/catalog/categories');
            if (response.success && response.data) {
                categorias = response.data;
                const select = document.getElementById('categoria');
                select.innerHTML = '<option value="">Seleccionar categoría...</option>'; // Limpiar opciones existentes
                categorias.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.category_id;
                    option.textContent = escapeHtml(cat.name);
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('[Servicios] Error al cargar categorías:', error.message);
            alerts.error('Error al cargar las categorías de servicios.');
        }
    }

    // ── Renderizado ────────────────────────────────────────────

    function renderServicios(servicios) {
        const tbody = document.getElementById('serviciosTbody');

        if (!servicios || !servicios.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay servicios registrados.</td></tr>';
            return;
        }

        const statusMap = { 1: { label: 'Activo', class: 'badge--success' }, 0: { label: 'Inactivo', class: 'badge--neutral' } };

        tbody.innerHTML = servicios.map(s => {
            const status = statusMap[s.is_active ?? 0] || { label: '—', class: 'badge--neutral' };
            const price = parseFloat(s.base_price ?? 0).toFixed(2);
            const dur = (s.duration_minutes ?? 0) + ' min';
            const catName = escapeHtml(s.category_name ?? '—');

            return '<tr id="service-row-' + s.service_id + '">' +
                '<td class="fw-bold">' + escapeHtml(s.name ?? '') + '</td>' +
                '<td>' + catName + '</td>' +
                '<td>$' + price + '</td>' +
                '<td>' + dur + '</td>' +
                '<td><span class="badge ' + status.class + '">' + status.label + '</span></td>' +
                '<td style="text-align: center;">' +
                    '<button class="btn btn--ghost btn--sm" onclick="editarServicio(' + s.service_id + ')" title="Editar">✏️</button>' +
                    '<button class="btn btn--ghost btn--sm" onclick="toggleEstadoServicio(' + s.service_id + ', ' + (s.is_active ? 0 : 1) + ')" title="Cambiar estado">' + (s.is_active ? '⏸️' : '▶️') + '</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderPaginacion() {
        const info = document.getElementById('pageInfo');
        const controls = document.getElementById('pageControls');

        if (totalRecords === 0) { info.textContent = '— Sin resultados'; controls.innerHTML = ''; return; }

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalRecords);
        info.textContent = 'Mostrando ' + start + '–' + end + ' de ' + totalRecords + ' servicios';

        let html = '<button class="btn btn--outline btn--sm" onclick="irPagina(' + (currentPage - 1) + ')" ' + (currentPage <= 1 ? 'disabled' : '') + '>← Anterior</button>';
        html += '<button class="btn btn--outline btn--sm" onclick="irPagina(' + (currentPage + 1) + ')" ' + (currentPage >= totalPages ? 'disabled' : '') + '>Siguiente →</button>';
        controls.innerHTML = html;
    }

    function renderEmptyState() {
        document.getElementById('serviciosTbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay servicios registrados.</td></tr>';
        document.getElementById('pageInfo').textContent = '— Sin resultados';
        document.getElementById('pageControls').innerHTML = '';
    }

    // ── Acciones del usuario ────────────────────────────────────

    window.buscarServicios = function() {
        currentSearch = document.getElementById('searchInput').value.trim();
        currentPage = 1;
        cargarServicios();
    };

    window.limpiarBusqueda = function() {
        document.getElementById('searchInput').value = '';
        currentSearch = '';
        currentPage = 1;
        cargarServicios();
    };

    window.irPagina = function(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        cargarServicios();
    };

    window.editarServicio = async function(id) {
        if (!api) return;
        try {
            const response = await api.get('/catalog/services/' + id);
            if (response.success && response.data) {
                const s = response.data;
                editandoId = s.service_id;
                document.getElementById('servicio_id').value = s.service_id;
                document.getElementById('nombre').value = s.name ?? '';
                document.getElementById('categoria').value = s.category_id ?? '';
                document.getElementById('precio').value = s.base_price ?? '';
                document.getElementById('duracion').value = s.duration_minutes ?? '';
                document.getElementById('descripcion').value = s.description ?? '';
                document.getElementById('formTitle').textContent = 'Modificar Servicio';
                document.getElementById('btnGuardar').textContent = 'Actualizar';
                document.getElementById('nombre').focus();
                
                // Scroll suave al formulario para mejor UX
                document.querySelector('.service-form-aside').scrollIntoView({ behavior: 'smooth' });
            }
        } catch (error) {
            console.error('[Servicios] Error al obtener detalle:', error);
            if (alerts) alerts.error('Error al cargar el servicio para editar.');
        }
    };

    window.toggleEstadoServicio = async function(id, nuevoEstado) {
        if (!api) return;
        try {
            const response = await api.patch('/catalog/services/' + id, { is_active: nuevoEstado });
            if (response.success) {
                if (alerts) alerts.success(nuevoEstado ? 'Servicio activado.' : 'Servicio desactivado.');
                await cargarServicios();
            } else {
                throw new Error(response.detail || 'No se pudo cambiar el estado.');
            }
        } catch (error) {
            if (alerts) alerts.error(error.message || 'Error al cambiar el estado.');
        }
    };

    window.limpiarFormulario = function() {
        document.getElementById('servicioForm').reset();
        document.getElementById('servicio_id').value = '';
        editandoId = null;
        document.getElementById('formTitle').textContent = 'Agregar Nuevo Servicio';
        document.getElementById('btnGuardar').textContent = 'Guardar';
    };

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
JS;

// ── Incluir el layout — $extraJS ya está configurado ──────────────
require_once __DIR__ . '/../layouts/admin-layout.php';
