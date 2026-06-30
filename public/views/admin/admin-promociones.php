<?php declare(strict_types=1);
/**
 * Gestión de Promociones - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Creación y administración de campañas de descuento y códigos promocionales.
 * Consume: /api/v1/catalog/promotions
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Estrategias y Promociones';
$pageSubtitle = 'Diseñe campañas de marketing digital y códigos de descuento para el autoservicio del cliente.';
$activeModule = 'promociones';

$extraCSS = <<<CSS
.promo-form-section {
    flex: 1;
    height: fit-content;
    position: sticky;
    top: 24px;
}
.promo-code {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--color-accent);
    background-color: var(--color-primary-light);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
}
CSS;

ob_start();
?>

<div class="layout-split">
    <!-- Formulario de creación -->
    <section class="card promo-form-section">
        <h3 class="card__title">Crear Nueva Campaña</h3>
        <form id="promoForm" novalidate>
            <div class="form-group">
                <label for="codigo" class="form-label">Código de Descuento</label>
                <input type="text" id="codigo" class="form-control" placeholder="Ej. MADRES2026" style="text-transform:uppercase; font-weight:700; letter-spacing:1px;" required maxlength="50">
            </div>

            <div class="form-group">
                <label for="porcentaje" class="form-label">Porcentaje de Descuento (%)</label>
                <input type="number" id="porcentaje" class="form-control" min="1" max="100" step="0.01" placeholder="Ej. 15" required>
            </div>

            <div class="form-group">
                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                <input type="date" id="fecha_inicio" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="fecha_fin" class="form-label">Fecha de Finalización</label>
                <input type="date" id="fecha_fin" class="form-control" required>
                <span class="form-hint">La campaña caducará automáticamente al finalizar este día.</span>
            </div>

            <div class="form-group">
                <label for="serviciosSelect" class="form-label">Servicios Asociados</label>
                <select id="serviciosSelect" class="form-control" multiple size="4">
                    <!-- Opciones cargadas por JS -->
                </select>
                <span class="form-hint">Mantenga Ctrl/Cmd para seleccionar múltiples servicios.</span>
            </div>

            <button type="submit" class="btn btn--primary btn--block mt-4">Crear Campaña</button>
        </form>
    </section>

    <!-- Historial de promociones -->
    <section class="card" style="flex:2;">
        <div class="d-flex justify-between align-center" style="margin-bottom:16px;">
            <h3 class="card__title" style="margin:0;border:none;padding:0;">Historial de Campañas</h3>
            <div>
                <select id="filtroEstado" class="form-control" style="width:auto;">
                    <option value="todos">Todas</option>
                    <option value="activas">Activas</option>
                    <option value="caducadas">Caducadas</option>
                    <option value="futuras">Futuras</option>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descuento</th>
                        <th>Servicios</th>
                        <th>Periodo de Validez</th>
                        <th>Estado</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaPromociones">
                    <tr><td colspan="6" class="text-center text-muted p-5">Cargando promociones...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php
$pageContent = ob_get_clean();

$extraJS = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    let promociones = [];
    let servicios = [];

    // Inicializar
    cargarServiciosParaSelect();
    cargarPromociones();

    // Submit del formulario
    document.getElementById('promoForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const codigo = document.getElementById('codigo').value.toUpperCase().trim();
        const porcentaje = parseFloat(document.getElementById('porcentaje').value);
        const inicio = document.getElementById('fecha_inicio').value;
        const fin = document.getElementById('fecha_fin').value;

        if (!codigo || isNaN(porcentaje) || !inicio || !fin) {
            alerts.error('Complete todos los campos obligatorios.');
            return;
        }
        if (porcentaje < 1 || porcentaje > 100) {
            alerts.error('El descuento debe estar entre 1% y 100%.');
            return;
        }
        if (new Date(fin) < new Date(inicio)) {
            alerts.error('La fecha de finalización no puede ser anterior a la de inicio.');
            return;
        }

        // Obtener servicios seleccionados
        const selectEl = document.getElementById('serviciosSelect');
        const selectedServices = Array.from(selectEl.selectedOptions).map(o => parseInt(o.value));

        const payload = {
            name: codigo,
            discount_percentage: porcentaje,
            start_date: inicio,
            end_date: fin,
            associated_services: selectedServices
        };

        try {
            if (api) {
                const res = await api.post('/catalog/promotions', payload);
                if (res.success) {
                    alerts.success(\`Campaña "\${codigo}" registrada exitosamente.\`);
                    document.getElementById('promoForm').reset();
                    await cargarPromociones();
                    return;
                }
            }

            // Simulación
            const nuevaPromo = {
                promotion_id: promociones.length + 10,
                name: codigo,
                discount_percentage: porcentaje,
                start_date: inicio,
                end_date: fin,
                services_count: selectedServices.length,
                status: calcularEstado(inicio, fin)
            };
            promociones.unshift(nuevaPromo);
            renderPromociones();
            document.getElementById('promoForm').reset();
            alerts.success(\`Campaña "\${codigo}" registrada (simulación).\`);
        } catch (error) {
            alerts.error(error.message || 'Error al crear la campaña.');
        }
    });

    // Filtro de estado
    document.getElementById('filtroEstado').addEventListener('change', () => renderPromociones());

    /**
     * Carga servicios disponibles para el select
     */
    async function cargarServiciosParaSelect() {
        try {
            if (api) {
                const res = await api.get('/catalog/services');
                if (res.success && res.data) {
                    servicios = res.data;
                    renderServiciosSelect();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Promociones] API servicios no disponible');
        }

        servicios = [
            { service_id: 1, name: 'Corte de Cabello' },
            { service_id: 2, name: 'Manicure Premium' },
            { service_id: 3, name: 'Limpieza Facial Completa' },
            { service_id: 4, name: 'Pedicure Spa' },
            { service_id: 5, name: 'Balayage Profesional' },
        ];
        renderServiciosSelect();
    }

    function renderServiciosSelect() {
        const select = document.getElementById('serviciosSelect');
        select.innerHTML = servicios.map(s =>
            \`<option value="\${s.service_id}">\${escapeHtml(s.name)}</option>\`
        ).join('');
    }

    /**
     * Carga las promociones desde la API
     */
    async function cargarPromociones() {
        try {
            if (api) {
                const res = await api.get('/catalog/promotions');
                if (res.success && res.data) {
                    promociones = res.data.map(p => ({
                        ...p,
                        status: calcularEstado(p.start_date, p.end_date),
                        services_count: p.associated_services_count || p.services_count || 0
                    }));
                    renderPromociones();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Promociones] API no disponible:', error.message);
        }

        // Datos simulados
        promociones = [
            { promotion_id: 1, name: 'PRIMERACITA', discount_percentage: 10, start_date: '2026-01-01', end_date: '2026-12-31', services_count: 5, status: 'activa' },
            { promotion_id: 2, name: 'NAVIDADCM', discount_percentage: 25, start_date: '2025-12-15', end_date: '2025-12-30', services_count: 3, status: 'caducada' },
            { promotion_id: 3, name: 'VERANOCM26', discount_percentage: 15, start_date: '2026-07-01', end_date: '2026-08-31', services_count: 4, status: 'futura' },
            { promotion_id: 4, name: 'AMIGAS20', discount_percentage: 20, start_date: '2026-06-01', end_date: '2026-06-30', services_count: 2, status: 'activa' },
        ];
        renderPromociones();
    }

    /**
     * Calcula el estado de una promoción según sus fechas
     * @param {string} inicio
     * @param {string} fin
     * @returns {string}
     */
    function calcularEstado(inicio, fin) {
        const hoy = new Date();
        const ini = new Date(inicio);
        const fn = new Date(fin + 'T23:59:59');

        if (hoy < ini) return 'futura';
        if (hoy > fn) return 'caducada';
        return 'activa';
    }

    /**
     * Renderiza la tabla de promociones
     */
    function renderPromociones() {
        const tbody = document.getElementById('tablaPromociones');
        const filtro = document.getElementById('filtroEstado').value;

        let filtradas = promociones;
        if (filtro !== 'todos') {
            filtradas = promociones.filter(p => p.status === filtro);
        }

        if (!filtradas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay promociones que coincidan con el filtro.</td></tr>';
            return;
        }

        const statusMap = {
            'activa':   { label: 'Activa',   class: 'badge--success' },
            'caducada': { label: 'Caducada', class: 'badge--neutral' },
            'futura':   { label: 'Futura',   class: 'badge--info' },
        };

        tbody.innerHTML = filtradas.map(p => {
            const status = statusMap[p.status] || { label: p.status, class: 'badge--neutral' };
            const fechaInicio = new Date(p.start_date).toLocaleDateString('es-CO');
            const fechaFin = new Date(p.end_date).toLocaleDateString('es-CO');

            return \`
                <tr id="promo-row-\${p.promotion_id}">
                    <td><span class="promo-code">\${escapeHtml(p.name)}</span></td>
                    <td class="fw-bold">\${p.discount_percentage}% OFF</td>
                    <td class="text-muted">\${p.services_count} servicios</td>
                    <td class="text-muted" style="font-size:0.85rem;">\${fechaInicio} — \${fechaFin}</td>
                    <td><span class="badge \${status.class}">\${status.label}</span></td>
                    <td style="text-align:center;">
                        <button class="btn btn--ghost btn--sm" onclick="eliminarPromocion(\${p.promotion_id})" title="Eliminar">🗑️</button>
                    </td>
                </tr>
            \`;
        }).join('');
    }

    /**
     * Elimina una promoción (baja lógica)
     * @param {number} id
     */
    window.eliminarPromocion = async function(id) {
        const promo = promociones.find(p => p.promotion_id === id);
        if (!promo) return;

        if (!confirm(\`¿Eliminar la campaña "\${promo.name}"? Esta acción desactivará el código de descuento permanentemente.\`)) return;

        try {
            if (api) {
                const res = await api.delete(\`/catalog/promotions/\${id}\`);
                if (res.success) {
                    alerts.success(\`Campaña "\${promo.name}" eliminada correctamente.\`);
                    promociones = promociones.filter(p => p.promotion_id !== id);
                    renderPromociones();
                    return;
                }
            }

            // Simulación
            promociones = promociones.filter(p => p.promotion_id !== id);
            renderPromociones();
            alerts.success(\`Campaña "\${promo.name}" eliminada (simulación).\`);
        } catch (error) {
            alerts.error(error.message || 'Error al eliminar la promoción.');
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
JS;

require_once __DIR__ . '/../layouts/admin-layout.php';
