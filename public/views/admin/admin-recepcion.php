<?php declare(strict_types=1);
/**
 * Control de Recepción - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Monitoreo y transición de estados de citas del día (Check-in, En Curso, Completado).
 * Consume: /api/v1/booking/appointments/{id}/check-in
 *          /api/v1/booking/appointments/{id}/complete
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Control de Recepción';
$pageSubtitle = 'Monitoreo y actualización del estado de las citas del día de hoy.';
$activeModule = 'recepcion';

$extraCSS = <<<CSS
.recepcion-header {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.fecha-badge {
    background-color: var(--color-info-light);
    color: var(--color-info);
    padding: 8px 16px;
    border-radius: var(--radius-md);
    font-weight: var(--font-weight-semibold);
    font-size: var(--font-size-sm);
}
.btn-flujo {
    padding: 6px 14px;
    font-size: 0.8rem;
    font-weight: var(--font-weight-bold);
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    transition: all var(--transition-fast);
    font-family: var(--font-primary);
}
.btn-flujo--llego {
    background-color: var(--color-primary-light);
    color: var(--color-accent);
    border-color: var(--color-accent);
}
.btn-flujo--llego:hover { background-color: var(--color-accent); color: white; }
.btn-flujo--atencion {
    background-color: var(--color-warning-light);
    color: #d4a373;
    border-color: #d4a373;
}
.btn-flujo--atencion:hover { background-color: #d4a373; color: white; }
.btn-flujo--completado {
    background-color: var(--color-success-light);
    color: var(--color-success);
    border-color: var(--color-success);
}
.btn-flujo--completado:hover { background-color: var(--color-success); color: white; }
.btn-flujo--cancelar {
    background-color: var(--color-danger-light);
    color: var(--color-danger);
    border-color: var(--color-danger);
}
.btn-flujo--cancelar:hover { background-color: var(--color-danger); color: white; }
CSS;

ob_start();
?>

<!-- Indicadores rápidos del día -->
<section class="metrics-grid" style="margin-bottom: 24px;">
    <div class="card metric-card" style="border-left-color: var(--color-accent);">
        <span class="metric-card__label">Citas Hoy</span>
        <div class="metric-card__value" id="totalHoy">0</div>
    </div>
    <div class="card metric-card" style="border-left-color: #d4a373;">
        <span class="metric-card__label">En Atención</span>
        <div class="metric-card__value" id="enAtencion">0</div>
    </div>
    <div class="card metric-card" style="border-left-color: var(--color-success);">
        <span class="metric-card__label">Completadas</span>
        <div class="metric-card__value" id="completadas">0</div>
    </div>
    <div class="card metric-card" style="border-left-color: var(--color-danger);">
        <span class="metric-card__label">No Asistieron</span>
        <div class="metric-card__value" id="noAsistieron">0</div>
    </div>
</section>

<!-- Tabla de citas del día -->
<section class="card">
    <div class="recepcion-header" style="margin-bottom: 16px;">
        <h3 class="card__title" style="margin: 0; border: none; padding: 0;">Agenda del Día</h3>
        <span class="fecha-badge" id="fechaHoy"></span>
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
                    <th style="text-align: center;">Acción de Flujo</th>
                </tr>
            </thead>
            <tbody id="tablaRecepcion">
                <tr>
                    <td colspan="6" class="text-center text-muted p-5">Cargando citas del día...</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php
$pageContent = ob_get_clean();

$extraJS = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    const STATUS_MAP = {
        'PENDING':     { label: 'Programada',     class: 'badge--info',     flujo: 'llego' },
        'CONFIRMED':   { label: 'Confirmada',     class: 'badge--info',     flujo: 'llego' },
        'IN_PROGRESS': { label: 'En Atención',    class: 'badge--warning',  flujo: 'completado' },
        'COMPLETED':   { label: 'Completada',     class: 'badge--success',  flujo: null },
        'CANCELLED':   { label: 'Cancelada',      class: 'badge--danger',   flujo: null },
        'NOSHOW':      { label: 'No Asistió',     class: 'badge--neutral',  flujo: null },
    };

    let citas = [];

    // Mostrar fecha de hoy
    const hoy = new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('fechaHoy').textContent = '📆 ' + hoy;

    // Cargar citas
    cargarCitasHoy();

    /**
     * Carga las citas del día desde la API
     */
    async function cargarCitasHoy() {
        try {
            if (api) {
                const res = await api.get('/booking/appointments?date=today');
                if (res.success && res.data) {
                    citas = res.data;
                    renderCitas();
                    actualizarIndicadores();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Recepcion] API no disponible:', error.message);
        }

        // Datos simulados
        citas = [
            { appointment_id: 1, scheduled_timestamp: '2026-06-12 08:00:00', service_name: 'Corte de Cabello', client_name: 'Diana Restrepo', professional_name: 'Carolina Mora', appointment_status: 'CONFIRMED', total_price: 25.00 },
            { appointment_id: 2, scheduled_timestamp: '2026-06-12 09:15:00', service_name: 'Manicure Premium', client_name: 'Milena Castro', professional_name: 'Andrea Gómez', appointment_status: 'CONFIRMED', total_price: 15.00 },
            { appointment_id: 3, scheduled_timestamp: '2026-06-12 10:30:00', service_name: 'Limpieza Facial', client_name: 'Carlos Pérez', professional_name: 'Carolina Mora', appointment_status: 'IN_PROGRESS', total_price: 40.00 },
            { appointment_id: 4, scheduled_timestamp: '2026-06-12 14:00:00', service_name: 'Pedicure Spa', client_name: 'Patricia Ortiz', professional_name: 'Andrea Gómez', appointment_status: 'PENDING', total_price: 22.00 },
            { appointment_id: 5, scheduled_timestamp: '2026-06-12 15:30:00', service_name: 'Balayage', client_name: 'Sandra Muñoz', professional_name: 'Carolina Mora', appointment_status: 'PENDING', total_price: 120.00 },
        ];
        renderCitas();
        actualizarIndicadores();
    }

    /**
     * Renderiza la tabla de citas
     */
    function renderCitas() {
        const tbody = document.getElementById('tablaRecepcion');

        if (!citas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay citas programadas para hoy.</td></tr>';
            return;
        }

        tbody.innerHTML = citas.map(cita => {
            const status = STATUS_MAP[cita.appointment_status] || { label: cita.appointment_status, class: 'badge--neutral', flujo: null };
            const hora = new Date(cita.scheduled_timestamp.replace(' ', 'T')).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

            let accionesHtml = '';
            if (status.flujo === 'llego') {
                accionesHtml = \`
                    <button class="btn-flujo btn-flujo--llego" onclick="transicionarCita(\${cita.appointment_id}, 'check-in')">
                        Llegó
                    </button>
                    <button class="btn-flujo btn-flujo--cancelar" style="margin-left:4px;" onclick="transicionarCita(\${cita.appointment_id}, 'cancel')">
                        Cancelar
                    </button>
                \`;
            } else if (status.flujo === 'completado') {
                accionesHtml = \`
                    <button class="btn-flujo btn-flujo--completado" onclick="transicionarCita(\${cita.appointment_id}, 'complete')">
                        Finalizar
                    </button>
                    <button class="btn-flujo btn-flujo--cancelar" style="margin-left:4px;" onclick="transicionarCita(\${cita.appointment_id}, 'noshow')">
                        No Asistió
                    </button>
                \`;
            } else {
                accionesHtml = '<span class="text-muted" style="font-size:0.85rem;">✅ Atendido</span>';
            }

            return \`
                <tr id="recepcion-row-\${cita.appointment_id}">
                    <td class="fw-bold">\${hora}</td>
                    <td>\${escapeHtml(cita.client_name)}</td>
                    <td>\${escapeHtml(cita.service_name)}</td>
                    <td>\${escapeHtml(cita.professional_name)}</td>
                    <td><span class="badge \${status.class}">\${status.label}</span></td>
                    <td style="text-align:center;">\${accionesHtml}</td>
                </tr>
            \`;
        }).join('');
    }

    /**
     * Actualiza los indicadores de la parte superior
     */
    function actualizarIndicadores() {
        const total = citas.length;
        const enAtencion = citas.filter(c => c.appointment_status === 'IN_PROGRESS').length;
        const completadas = citas.filter(c => c.appointment_status === 'COMPLETED').length;
        const noAsistieron = citas.filter(c => c.appointment_status === 'NOSHOW').length;

        document.getElementById('totalHoy').textContent = total;
        document.getElementById('enAtencion').textContent = enAtencion;
        document.getElementById('completadas').textContent = completadas;
        document.getElementById('noAsistieron').textContent = noAsistieron;
    }

    /**
     * Ejecuta una transición de estado sobre una cita
     * @param {number} citaId
     * @param {string} accion - check-in, complete, cancel, noshow
     */
    window.transicionarCita = async function(citaId, accion) {
        const endpointMap = {
            'check-in': \`/booking/appointments/\${citaId}/check-in\`,
            'complete': \`/booking/appointments/\${citaId}/complete\`,
            'cancel':   \`/booking/appointments/\${citaId}/cancel\`,
            'noshow':   \`/booking/appointments/\${citaId}/noshow\`,
        };

        const bodyMap = {
            'check-in': {},
            'complete': {},
            'cancel': { change_reason: 'Cancelación por recepción' },
            'noshow': { change_reason: 'Cliente no se presentó en la sucursal' },
        };

        try {
            if (api) {
                const res = await api.patch(endpointMap[accion], bodyMap[accion]);
                if (res.success) {
                    const mensajes = {
                        'check-in': 'Cliente registrado en recepción. Atención iniciada.',
                        'complete': 'Servicio finalizado. Proceder al cobro en caja.',
                        'cancel': 'Cita cancelada exitosamente.',
                        'noshow': 'Cita marcada como No Asistió.',
                    };
                    alerts.success(mensajes[accion]);
                    await cargarCitasHoy();
                    return;
                }
            }

            // Simulación local
            const statusMap = { 'check-in': 'IN_PROGRESS', 'complete': 'COMPLETED', 'cancel': 'CANCELLED', 'noshow': 'NOSHOW' };
            const cita = citas.find(c => c.appointment_id === citaId);
            if (cita) cita.appointment_status = statusMap[accion];
            renderCitas();
            actualizarIndicadores();
            alerts.success('Estado actualizado (modo simulación).');
        } catch (error) {
            alerts.error(error.message || 'Error al actualizar el estado de la cita.');
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
