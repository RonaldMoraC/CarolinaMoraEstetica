<?php declare(strict_types=1);
/**
 * Dashboard Principal del Panel Administrativo
 * Estética Carolina Mora
 *
 * Todos los datos provienen de la base de datos real vía API.
 * Si la BD está vacía → muestra $0.00, 0 citas, 0 clientes — lo cual es fidedigno.
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Dashboard';
$pageSubtitle = 'Resumen general del negocio y accesos rápidos a los módulos del sistema.';
$activeModule = 'dashboard';

ob_start();
?>

<!-- KPIs principales -->
<section class="metrics-grid">
    <div class="card metric-card" style="border-left-color: #2ecc71;">
        <span class="metric-card__label">Ingresos del Mes</span>
        <div class="metric-card__value" id="kpiIngresos">$0.00</div>
        <span class="metric-card__trend text-success" id="kpiIngresosTrend">—</span>
    </div>

    <div class="card metric-card" style="border-left-color: var(--color-accent);">
        <span class="metric-card__label">Citas del Mes</span>
        <div class="metric-card__value" id="kpiCitas">0</div>
        <span class="metric-card__trend text-info" id="kpiCitasTrend">—</span>
    </div>

    <div class="card metric-card" style="border-left-color: #9b59b6;">
        <span class="metric-card__label">Clientes Activos</span>
        <div class="metric-card__value" id="kpiClientes">0</div>
        <span class="metric-card__trend" style="color: #9b59b6;" id="kpiClientesTrend">—</span>
    </div>

    <div class="card metric-card" style="border-left-color: #f1c40f;">
        <span class="metric-card__label">Calificación Promedio</span>
        <div class="metric-card__value" id="kpiRating">0.0</div>
        <span class="metric-card__trend text-warning" id="kpiRatingTrend">—</span>
    </div>
</section>

<!-- Citas de hoy -->
<div class="layout-split">
    <section class="card" style="flex: 2;">
        <h3 class="card__title">Citas Programadas para Hoy</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Especialista</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="tablaCitasHoy">
                    <tr>
                        <td colspan="5" class="text-center text-muted p-5">
                            No hay citas programadas para hoy.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php
$pageContent = ob_get_clean();

// ── JS del Dashboard — inyectado via $extraJS dentro del <body> ──
$extraJS = <<<'JS'
async function loadDashboard() {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    try {
        if (!api) {
            console.warn('[Dashboard] AdminApp no inicializado. Mostrando estado vacío.');
            renderEmptyState();
            return;
        }
        
        // Pausa mínima para asegurar que los headers de Auth estén inyectados
        // tras el redireccionamiento del login.
        if (!window.dashboardInitialized) await new Promise(r => setTimeout(r, 100));
        window.dashboardInitialized = true;

        const metricsRes = await api.get('/admin/metrics');

        if (metricsRes && metricsRes.success) {
            const payload = metricsRes.data || {};
            const data = payload.metrics || {};
            const citasHoy = payload.appointments_today || [];

            document.getElementById('kpiIngresos').textContent = '$' + (data.total_ingresos ?? 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('kpiIngresosTrend').textContent = data.ingresos_trend ?? '—';
            document.getElementById('kpiCitas').textContent = (data.total_citas ?? 0).toLocaleString();
            document.getElementById('kpiCitasTrend').textContent = 'Ocupación del ' + (data.ocupacion ?? 0) + '%';
            document.getElementById('kpiClientes').textContent = (data.total_clientes ?? 0).toLocaleString();
            document.getElementById('kpiClientesTrend').textContent = data.clientes_trend ?? '—';
            document.getElementById('kpiRating').textContent = (data.rating_promedio ?? 0).toFixed(1);
            document.getElementById('kpiRatingTrend').textContent = (data.total_resenas ?? 0) + ' reseñas';
            renderCitasHoy(citasHoy);
        } else {
            renderEmptyState();
        }
    } catch (error) {
        // Si el error es 401 durante el arranque, es una sesión expirando o transicionando,
        // no mostramos el alert ruidoso, solo el estado vacío.
        if (error.status === 401) {
            renderEmptyState();
        } else {
            console.error('[Dashboard] Error:', error);
            if (alerts) alerts.error('Error al cargar los datos del dashboard.');
            renderEmptyState();
        }
    }
}

// Esperar a que AdminApp esté inicializado antes de cargar datos
if (window.adminApi) {
    loadDashboard();
} else {
    window.addEventListener('admin-app-ready', loadDashboard, { once: true });
}

function renderCitasHoy(citas) {
    const tbody = document.getElementById('tablaCitasHoy');
    if (!citas || !citas.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-5">No hay citas programadas para hoy.</td></tr>';
        return;
    }
    const statusMap = {
        'PENDING': { label: 'Programada', class: 'badge--info' },
        'CONFIRMED': { label: 'Confirmada', class: 'badge--success' },
        'IN_PROGRESS': { label: 'En Curso', class: 'badge--warning' },
        'COMPLETED': { label: 'Completada', class: 'badge--success' },
        'CANCELLED': { label: 'Cancelada', class: 'badge--danger' },
        'NOSHOW': { label: 'No Asistió', class: 'badge--neutral' }
    };
    tbody.innerHTML = citas.map(cita => {
        const status = statusMap[cita.appointment_status] || { label: cita.appointment_status, class: 'badge--neutral' };
        const hora = cita.scheduled_timestamp 
            ? new Date(cita.scheduled_timestamp).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
            : '--:--';
            
        return '<tr>' +
            '<td class="fw-bold">' + hora + '</td>' +
            '<td>' + escapeHtml(cita.client_name ?? '—') + '</td>' +
            '<td>' + escapeHtml(cita.service_name ?? '—') + '</td>' +
            '<td>' + escapeHtml(cita.professional_name ?? '—') + '</td>' +
            '<td><span class="badge ' + status.class + '">' + status.label + '</span></td>' +
        '</tr>';
    }).join('');
}

function renderEmptyState() {
    document.getElementById('kpiIngresos').textContent = '$0.00';
    document.getElementById('kpiIngresosTrend').textContent = '— Sin datos comparativos';
    document.getElementById('kpiCitas').textContent = '0';
    document.getElementById('kpiCitasTrend').textContent = 'Ocupación del 0%';
    document.getElementById('kpiClientes').textContent = '0';
    document.getElementById('kpiClientesTrend').textContent = '—';
    document.getElementById('kpiRating').textContent = '0.0';
    document.getElementById('kpiRatingTrend').textContent = '0 reseñas';
    document.getElementById('tablaCitasHoy').innerHTML = '<tr><td colspan="5" class="text-center text-muted p-5">No hay citas programadas para hoy.</td></tr>';
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
JS;

require_once __DIR__ . '/../layouts/admin-layout.php';
