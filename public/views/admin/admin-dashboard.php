<?php
use App\Infrastructure\Shared\Utils\AppPath;

/**
 * Dashboard Principal del Panel Administrativo
 * Estética Carolina Mora - CarolinaMoraEstetica
 *
 * Vista adaptada para supervisión operativa y del rol de Recepción.
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Dashboard Operativo';
$pageSubtitle = 'Resumen de la actividad diaria y acceso rápido al control de recepción.';
$activeModule = 'dashboard';

$extraCSS = <<<CSS
.dashboard-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.btn-action-quick {
    background-color: var(--color-primary-light);
    color: var(--color-accent);
    padding: 10px 20px;
    border-radius: var(--radius-md);
    font-weight: var(--font-weight-bold);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all var(--transition-fast);
}
.btn-action-quick:hover {
    background-color: var(--color-accent);
    color: white;
}
CSS;

ob_start();
?>

<!-- Accesos Rápidos Operativos -->
<div class="dashboard-actions">
    <a href="<?php echo AppPath::url('/admin/recepcion'); ?>" class="btn-action-quick">
        <span>📋 Ir al Control de Recepción y Check-in</span>
    </a>
    <a href="<?php echo AppPath::url('/admin/calendario'); ?>" class="btn-action-quick" style="background-color: var(--color-info-light); color: var(--color-info);">
        <span>📅 Ver Calendario General</span>
    </a>
</div>

<!-- KPIs Operativos y Principales -->
<section class="metrics-grid">
    <div class="card metric-card" style="border-left-color: #2ecc71;">
        <span class="metric-card__label">Ingresos del Mes</span>
        <div class="metric-card__value" id="kpiIngresos">$0.00</div>
        <span class="metric-card__trend text-success" id="kpiIngresosTrend">—</span>
    </div>

    <div class="card metric-card" style="border-left-color: var(--color-accent);">
        <span class="metric-card__label">Citas del Día</span>
        <div class="metric-card__value" id="kpiCitas">0</div>
        <span class="metric-card__trend text-info" id="kpiCitasTrend">Programadas para hoy</span>
    </div>

    <div class="card metric-card" style="border-left-color: #9b59b6;">
        <span class="metric-card__label">Clientes Activos</span>
        <div class="metric-card__value" id="kpiClientes">0</div>
        <span class="metric-card__trend" style="color: #9b59b6;" id="kpiClientesTrend">Registrados</span>
    </div>

    <div class="card metric-card" style="border-left-color: #f1c40f;">
        <span class="metric-card__label">Calificación Promedio</span>
        <div class="metric-card__value" id="kpiRating">0.0</div>
        <span class="metric-card__trend text-warning" id="kpiRatingTrend">Estrellas</span>
    </div>
</section>

<!-- Citas de hoy con acceso operativo -->
<div class="layout-split">
    <section class="card" style="flex: 2;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 class="card__title" style="margin:0; border:none; padding:0;">Citas Programadas para Hoy</h3>
            <a href="<?php echo AppPath::url('/admin/recepcion'); ?>" style="font-size: 0.85rem; font-weight: 600; color: var(--color-accent); text-decoration: none;">Gestionar Estados →</a>
        </div>
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
                            Cargando citas del día...
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
        if (!window.dashboardInitialized) await new Promise(r => setTimeout(r, 100));
        window.dashboardInitialized = true;

        const metricsRes = await api.get('/admin/metrics');

        if (metricsRes && metricsRes.success) {
            const payload = metricsRes.data || {};
            const data = payload.metrics || {};
            const citasHoy = payload.appointments_today || [];

            // Actualizar KPIs con seguridad contra XSS
            document.getElementById('kpiIngresos').textContent = '$' + parseFloat(data.monthly_revenue || 0).toFixed(2);
            document.getElementById('kpiCitas').textContent = data.monthly_appointments || 0;
            document.getElementById('kpiClientes').textContent = data.active_clients || 0;
            document.getElementById('kpiRating').textContent = parseFloat(data.average_rating || 0).toFixed(1);

            // Renderizar tabla de citas de hoy
            const tbody = document.getElementById('tablaCitasHoy');
            tbody.innerHTML = '';

            if (citasHoy.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-5">No hay citas programadas para hoy.</td></tr>';
                return;
            }

            citasHoy.forEach(cita => {
                const tr = document.createElement('tr');

                const tdHora = document.createElement('td');
                tdHora.textContent = cita.time || cita.hora || '—';

                const tdCliente = document.createElement('td');
                tdCliente.textContent = cita.client_name || cita.cliente || 'Cliente';

                const tdServicio = document.createElement('td');
                tdServicio.textContent = cita.service_name || cita.servicio || 'Servicio';

                const tdEspecialista = document.createElement('td');
                tdEspecialista.textContent = cita.professional_name || cita.especialista || 'General';

                const tdEstado = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'badge';
                const status = (cita.status || cita.estado || 'pending').toLowerCase();
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                tdEstado.appendChild(badge);

                tr.appendChild(tdHora);
                tr.appendChild(tdCliente);
                tr.appendChild(tdServicio);
                tr.appendChild(tdEspecialista);
                tr.appendChild(tdEstado);
                tbody.appendChild(tr);
            });
        }
    } catch (error) {
        if (alerts) alerts.error('Error al cargar métricas del dashboard: ' + error.message);
        renderEmptyState();
    }
}

function renderEmptyState() {
    document.getElementById('kpiIngresos').textContent = '$0.00';
    document.getElementById('kpiCitas').textContent = '0';
    document.getElementById('kpiClientes').textContent = '0';
    document.getElementById('kpiRating').textContent = '0.0';
}

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});
JS;

include __DIR__ . '/../layouts/admin-layout.php';

