<?php declare(strict_types=1);
/**
 * AnalÃ­ticas y Reportes - Panel Administrativo
 * EstÃ©tica Carolina Mora - CarolinaMoraEstetica
 *
 * VisualizaciÃ³n de KPIs financieros, volumen operativo y demanda por categorÃ­a.
 * Consume: /api/v1/admin/metrics
 *          /api/v1/billing/cash-desk/closing
 */

define('ADMIN_LAYOUT_LOADED', true);

// AutorizaciÃ³n: MÃ³dulo restringido a nivel administrativo.
$canViewAnalytics = isset($userRole) && in_array($userRole, ['SUPER_ADMIN', 'MANAGER'], true);
if (!$canViewAnalytics) {
    header('Location: /CarolinaMoraEstetica/admin/dashboard');
    exit;
}

$pageTitle = 'AnalÃ­ticas del Negocio';
$pageSubtitle = 'Consulte el rendimiento financiero y el volumen operativo acumulado.';
$activeModule = 'analiticas';

$extraCSS = <<<CSS
.chart-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: 24px;
}
@media (max-width: 992px) {
    .chart-section {
        grid-template-columns: 1fr;
    }
}
.export-actions {
    display: flex;
    gap: 8px;
}
CSS;

ob_start();
?>

<!-- KPIs principales -->
<section class="metrics-grid">
    <div class="card metric-card" style="border-left-color: #2ecc71;">
        <span class="metric-card__label">Ingresos Mensuales</span>
        <div class="metric-card__value" id="kpiIngresos">$0.00</div>
        <span class="metric-card__trend text-success" id="kpiIngresosTrend">â€”</span>
    </div>
    <div class="card metric-card" style="border-left-color: var(--color-accent);">
        <span class="metric-card__label">Citas Totales (Mes)</span>
        <div class="metric-card__value" id="kpiCitas">0</div>
        <span class="metric-card__trend text-info" id="kpiCitasTrend">â€”</span>
    </div>
    <div class="card metric-card" style="border-left-color: #9b59b6;">
        <span class="metric-card__label">Clientes Recurrentes</span>
        <div class="metric-card__value" id="kpiRecurrentes">0%</div>
        <span class="metric-card__trend" style="color:#9b59b6;" id="kpiRecurrentesTrend">â€”</span>
    </div>
    <div class="card metric-card" style="border-left-color: #e67e22;">
        <span class="metric-card__label">Ticket Promedio</span>
        <div class="metric-card__value" id="kpiTicket">$0.00</div>
        <span class="metric-card__trend text-warning" id="kpiTicketTrend">â€”</span>
    </div>
</section>

<!-- GrÃ¡ficos -->
<div class="chart-section">
    <!-- Volumen por categorÃ­a -->
    <section class="chart-container">
        <h3 class="card__title">Volumen de Citas por CategorÃ­a</h3>
        <p class="text-muted mb-4" style="font-size:0.9rem;">Demanda comercial acumulada en el periodo vigente.</p>
        <div class="bar-chart" id="chartCategorias">
            <!-- Barras renderizadas por JS -->
        </div>
    </section>

    <!-- Desglose por mÃ©todo de pago -->
    <section class="chart-container">
        <h3 class="card__title">Desglose por MÃ©todo de Pago</h3>
        <p class="text-muted mb-4" style="font-size:0.9rem;">DistribuciÃ³n de ingresos segÃºn forma de cobro.</p>
        <div class="bar-chart" id="chartMetodosPago">
            <!-- Barras renderizadas por JS -->
        </div>
    </section>
</div>

<!-- Top servicios y profesionales -->
<div class="chart-section" style="margin-top:24px;">
    <section class="card">
        <h3 class="card__title">Top 5 Servicios MÃ¡s Demandados</h3>
        <div class="table-container">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Servicio</th>
                        <th style="text-align:right;">Citas</th>
                        <th style="text-align:right;">Ingreso</th>
                    </tr>
                </thead>
                <tbody id="tablaTopServicios">
                    <tr><td colspan="4" class="text-center text-muted p-4">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3 class="card__title">Rendimiento por Profesional</h3>
        <div class="table-container">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>Profesional</th>
                        <th style="text-align:right;">Citas</th>
                        <th style="text-align:right;">Ingreso</th>
                        <th style="text-align:right;">Rating</th>
                    </tr>
                </thead>
                <tbody id="tablaProfesionales">
                    <tr><td colspan="4" class="text-center text-muted p-4">Cargando...</td></tr>
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

    cargarMetricas();

    /**
     * Carga todas las mÃ©tricas desde la API
     */
    async function cargarMetricas() {
        try {
            if (api) {
                const res = await api.get('/admin/analytics');
                if (res.success && res.data) {
                    renderKPIS(res.data.kpis);
                    renderChartCategorias(res.data.categorias);
                    renderChartMetodosPago(res.data.metodos_pago);
                    renderTopServicios(res.data.top_servicios);
                    renderProfesionales(res.data.profesionales);
                    return;
                }
            }
        } catch (error) {
            console.warn('[Analiticas] API no disponible, usando fallback simulado:', error.message);
        }

        // Datos simulados
        renderKPIS({
            ingresos_mensuales: 4250.00, ingresos_trend: 'â–² +12% vs mes anterior',
            citas_mes: 186, citas_trend: 'ðŸŽ¯ OcupaciÃ³n del 84%',
            clientes_recurrentes_pct: 72.4, clientes_trend: 'â­ Alta fidelidad',
            ticket_promedio: 22.85, ticket_trend: 'â–² +$1.20 vs mes anterior'
        });

        renderChartCategorias([
            { nombre: 'âœ‚ï¸ Cabello / Estilo', citas: 85, porcentaje: 85, color: '' },
            { nombre: 'ðŸ’… UÃ±as / Manicure', citas: 60, porcentaje: 60, color: '#9b59b6' },
            { nombre: 'ðŸ§´ Cuidado Facial', citas: 32, porcentaje: 32, color: '#e67e22' },
            { nombre: 'âœ¨ Otros Tratamientos', citas: 9, porcentaje: 10, color: '#7f8c8d' },
        ]);

        renderChartMetodosPago([
            { nombre: 'ðŸ’µ Efectivo', monto: 450.00, porcentaje: 31, color: '#2ecc71' },
            { nombre: 'ðŸ’³ Tarjeta CrÃ©dito', monto: 800.00, porcentaje: 55, color: '#3498db' },
            { nombre: 'ðŸ¦ Transferencia', monto: 150.00, porcentaje: 10, color: '#9b59b6' },
            { nombre: 'ðŸ“± Pasarela Online', monto: 50.00, porcentaje: 4, color: '#e67e22' },
        ]);

        renderTopServicios([
            { nombre: 'Corte de Cabello', citas: 52, ingreso: 1300.00 },
            { nombre: 'Manicure Premium', citas: 41, ingreso: 615.00 },
            { nombre: 'Limpieza Facial', citas: 28, ingreso: 1120.00 },
            { nombre: 'Balayage Profesional', citas: 15, ingreso: 1800.00 },
            { nombre: 'Pedicure Spa', citas: 12, ingreso: 264.00 },
        ]);

        renderProfesionales([
            { nombre: 'Carolina Mora', citas: 98, ingreso: 3200.00, rating: 4.9 },
            { nombre: 'Andrea GÃ³mez', citas: 62, ingreso: 1800.00, rating: 4.7 },
            { nombre: 'Luis MartÃ­nez', citas: 26, ingreso: 650.00, rating: 4.6 },
        ]);
    }

    function renderKPIS(kpis) {
        document.getElementById('kpiIngresos').textContent = new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', minimumFractionDigits: 0}).format(kpis.ingresos_mensuales ?? 0);
        document.getElementById('kpiIngresosTrend').textContent = kpis.ingresos_trend || 'â€”';
        document.getElementById('kpiCitas').textContent = (kpis.citas_mes ?? 0).toLocaleString();
        document.getElementById('kpiCitasTrend').textContent = kpis.citas_trend || 'â€”';
        document.getElementById('kpiRecurrentes').textContent = (kpis.clientes_recurrentes_pct ?? 0) + '%';
        document.getElementById('kpiRecurrentesTrend').textContent = kpis.clientes_trend || 'â€”';
        document.getElementById('kpiTicket').textContent = new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', minimumFractionDigits: 0}).format(kpis.ticket_promedio ?? 0);
        document.getElementById('kpiTicketTrend').textContent = kpis.ticket_trend || 'â€”';
    }

    function renderChartCategorias(data) {
        const container = document.getElementById('chartCategorias');
        container.innerHTML = data.map(item => \`
            <div class="chart-row">
                <div class="bar-label">\${escapeHtml(item.nombre)}</div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:\${item.porcentaje}%; \${item.color ? 'background-color:' + item.color : ''}"></div>
                </div>
                <div class="bar-value">\${item.citas} citas</div>
            </div>
        \`).join('');
    }

    function renderChartMetodosPago(data) {
        const container = document.getElementById('chartMetodosPago');
        container.innerHTML = data.map(item => `
            <div class="chart-row">
                <div class="bar-label">${escapeHtml(item.nombre)}</div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:${item.porcentaje}%; ${item.color ? 'background-color:' + item.color : ''}"></div>
                </div>
                <div class="bar-value">${new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', minimumFractionDigits: 0}).format(item.monto)}</div>
            </div>
        `).join('');
    }

    function renderTopServicios(data) {
        const tbody = document.getElementById('tablaTopServicios');
        tbody.innerHTML = data.map((s, i) => `
            <tr>
                <td class="fw-bold">${i + 1}</td>
                <td>${escapeHtml(s.nombre)}</td>
                <td style="text-align:right;">${s.citas}</td>
                <td style="text-align:right;" class="text-success fw-bold">${new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', minimumFractionDigits: 0}).format(s.ingreso)}</td>
            </tr>
        `).join('');
    }

    function renderProfesionales(data) {
        const tbody = document.getElementById('tablaProfesionales');
        tbody.innerHTML = data.map(p => `
            <tr>
                <td>${escapeHtml(p.nombre)}</td>
                <td style="text-align:right;">${p.citas}</td>
                <td style="text-align:right;" class="text-success fw-bold">${new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', minimumFractionDigits: 0}).format(p.ingreso)}</td>
                <td style="text-align:right;"><span class="stars">â˜…</span> ${p.rating}</td>
            </tr>
        `).join('');
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
JS;

require_once __DIR__ . '/../layouts/admin-layout.php';

