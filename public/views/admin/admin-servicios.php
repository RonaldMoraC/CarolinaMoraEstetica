<?php declare(strict_types=1);
/**
 * GestiÃ³n de Servicios - Panel Administrativo
 * EstÃ©tica Carolina Mora - CarolinaMoraEstetica
 *
 * CRUD de servicios del catÃ¡logo de la estÃ©tica.
 * Todos los datos provienen de la base de datos real vÃ­a API.
 * NO hay datos simulados. Si la BD estÃ¡ vacÃ­a â†’ se muestra "No hay servicios".
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

<!-- Barra de bÃºsqueda -->
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
                        <th>CategorÃ­a</th>
                        <th>Precio</th>
                        <th>DuraciÃ³n</th>
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

        <!-- PaginaciÃ³n -->
        <div class="pagination-bar" id="paginationBar">
            <span class="page-info" id="pageInfo">â€”</span>
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
                <label for="categoria" class="form-label">CategorÃ­a</label>
                <select id="categoria" class="form-control" required>
                    <option value="">Seleccionar categorÃ­a...</option>
                </select>
            </div>

            <div class="form-group">
                <label for="precio" class="form-label">Precio ($)</label>
                <input type="number" step="0.01" min="0" id="precio" class="form-control" placeholder="Ej. 20.00" required oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? parseFloat(this.value).toFixed(2) : null">
            </div>

            <div class="form-group">
                <label for="duracion" class="form-label">DuraciÃ³n (Minutos)</label>
                <select id="duracion" class="form-control" required>
                    <option value="">Seleccionar duraciÃ³n...</option>
                    <option value="15">15 minutos</option>
                    <option value="30">30 minutos</option>
                    <option value="45">45 minutos</option>
                    <option value="60">60 minutos</option>
                    <option value="90">90 minutos</option>
                    <option value="120">120 minutos</option>
                </select>
            </div>

            <div class="form-group">
                <label for="descripcion" class="form-label">DescripciÃ³n (opcional)</label>
                <textarea id="descripcion" class="form-control" rows="3" placeholder="Breve descripciÃ³n del servicio..." maxlength="500"></textarea>
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

// â”€â”€ JS especÃ­fico â€” Se inyecta via $extraJS dentro del <body> â”€â”€â”€â”€â”€
$extraJS = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    // â”€â”€ Estado local â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let perPage = 15;
    let currentSearch = '';
    let editandoId = null;

    // â”€â”€ Cargar datos al iniciar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    cargarCategorias(); // Cargar categorÃ­as primero
    cargarServicios();

    // â”€â”€ Debounce en la barra de bÃºsqueda â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(window._searchTimeout);
        window._searchTimeout = setTimeout(() => {
            currentSearch = e.target.value.trim();
            currentPage = 1;
            cargarServicios();
        }, 400);
    });

    // â”€â”€ Submit del formulario â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.getElementById('servicioForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!api) { if (alerts) alerts.error('No hay conexiÃ³n con la API.'); return; }

        const payload = {
            name: document.getElementById('nombre').value.trim(),
            category_id: parseInt(document.getElementById('categoria').value, 10),
            base_price: parseFloat(document.getElementById('precio').value),
            duration_minutes: parseInt(document.getElementById('duracion').value, 10),
            description: document.getElementById('descripcion').value.trim()
        };

        if (!payload.name) { alerts.error('El nombre del servicio es obligatorio.'); return; }
        if (!payload.category_id) { alerts.error('Seleccione una categorÃ­a.'); return; }
        if (isNaN(payload.base_price) || payload.base_price <= 0) { alerts.error('El precio debe ser mayor a 0.'); return; }
        if (!payload.duration_minutes || payload.duration_minutes <= 0) { alerts.error('Seleccione una duraciÃ³n vÃ¡lida.'); return; }

        const btn = document.getElementById('btnGuardar');
        btn.disabled = true;
        btn.textContent = 'Procesando...';

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
        } finally {
            btn.disabled = false;
            btn.textContent = editandoId ? 'Actualizar' : 'Guardar';
        }
    });

    // â”€â”€ Funciones de carga â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

                // Si por eliminaciÃ³n o paginaciÃ³n la pÃ¡gina actual supera el total de pÃ¡ginas, re-ajustar
                if (currentPage > totalPages && totalPages > 0) {
                    currentPage = totalPages;
                    await cargarServicios();
                    return;
                }

                renderServicios(servicios);
                renderPaginacion();
                return;
            }
        } catch (error) {
            console.warn('[Servicios] Error:', error.message);
        }
        renderEmptyState();
    }

    // â”€â”€ Cargar categorÃ­as para el select â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    let categorias = []; // Almacenar categorÃ­as para uso posterior
    async function cargarCategorias() {
        try {
            if (!api) { console.warn('[Servicios] API no disponible para categorÃ­as.'); return; }
            const response = await api.get('/catalog/categories');
            if (response.success && response.data) {
                categorias = response.data;
                const select = document.getElementById('categoria');
                select.innerHTML = '<option value="">Seleccionar categorÃ­a...</option>'; // Limpiar opciones existentes
                categorias.forEach(cat => {
                    const option = document.createElement('option');
                    // Usar acceso seguro a category_id || id
                    option.value = cat.category_id ?? cat.id;
                    option.textContent = escapeHtml(cat.name);
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('[Servicios] Error al cargar categorÃ­as:', error.message);
            alerts.error('Error al cargar las categorÃ­as de servicios.');
        }
    }

    // â”€â”€ Renderizado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    function renderServicios(servicios) {
        const tbody = document.getElementById('serviciosTbody');

        if (!servicios || !servicios.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay servicios registrados.</td></tr>';
            return;
        }

        const statusMap = { 1: { label: 'Activo', class: 'badge--success' }, 0: { label: 'Inactivo', class: 'badge--neutral' } };

        tbody.innerHTML = servicios.map(s => {
            const status = statusMap[s.is_active ?? 0] || { label: 'â€”', class: 'badge--neutral' };
            const price = new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0
            }).format(s.base_price ?? 0);
            const dur = (s.duration_minutes ?? 0) + ' min';
            const catName = escapeHtml(s.category_name ?? 'â€”');
            const isActive = !!s.is_active;

            return `<tr id="service-row-${s.service_id}">
                <td class="fw-bold">${escapeHtml(s.name ?? '')}</td>
                <td>${catName}</td>
                <td>${price}</td>
                <td>${dur}</td>
                <td><span class="badge ${status.class}">${status.label}</span></td>
                <td style="text-align: center;">
                    <button class="btn btn--ghost btn--sm" title="Editar" aria-label="Editar">âœï¸</button>
                    <button class="btn btn--ghost btn--sm" title="Cambiar estado" aria-label="Cambiar estado">${isActive ? 'â¸ï¸' : 'â–¶ï¸'}</button>
                </td>
            </tr>`;
        }).join('');
    }

    // Listener delegado para las acciones de la tabla
    document.getElementById('serviciosTbody').addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        const row = btn.closest('tr');
        const id = row.id.split('-')[2];
        const title = btn.getAttribute('title');

        if (title === 'Editar') {
            editarServicio(id);
        } else if (title === 'Cambiar estado') {
            const isActive = btn.textContent.trim() === 'â¸ï¸';
            toggleEstadoServicio(id, isActive ? 0 : 1);
        }
    });

    function renderPaginacion() {
        const info = document.getElementById('pageInfo');
        const controls = document.getElementById('pageControls');

        if (totalRecords === 0) { info.textContent = 'â€” Sin resultados'; controls.innerHTML = ''; return; }

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalRecords);
        info.textContent = 'Mostrando ' + start + 'â€“' + end + ' de ' + totalRecords + ' servicios';

        let html = '<button class="btn btn--outline btn--sm" onclick="irPagina(' + (currentPage - 1) + ')" ' + (currentPage <= 1 ? 'disabled' : '') + '>â† Anterior</button>';
        html += '<button class="btn btn--outline btn--sm" onclick="irPagina(' + (currentPage + 1) + ')" ' + (currentPage >= totalPages ? 'disabled' : '') + '>Siguiente â†’</button>';
        controls.innerHTML = html;
    }

    function renderEmptyState() {
        document.getElementById('serviciosTbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay servicios registrados.</td></tr>';
        document.getElementById('pageInfo').textContent = 'â€” Sin resultados';
        document.getElementById('pageControls').innerHTML = '';
    }

    // â”€â”€ Acciones del usuario â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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
        const action = nuevoEstado ? 'activar' : 'desactivar';
        if (!confirm(`Â¿EstÃ¡ seguro de que desea ${action} este servicio?`)) return;

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

// â”€â”€ Incluir el layout â€” $extraJS ya estÃ¡ configurado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
require_once __DIR__ . '/../layouts/admin-layout.php';

