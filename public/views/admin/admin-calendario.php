<?php declare(strict_types=1);
/**
 * Calendario Maestro - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Vista global semanal de ocupación de agenda por profesional.
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Calendario Maestro';
$pageSubtitle = 'Vista global y semanal de la ocupación física y técnica de la estética.';
$activeModule = 'calendario';

$extraCSS = <<<CSS
.calendar-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.calendar-controls select {
    max-width: 240px;
}
.panel-detalles {
    flex: 1;
    height: fit-content;
    position: sticky;
    top: 24px;
}
@media (max-width: 992px) {
    .calendar-layout {
        flex-direction: column;
    }
    .panel-detalles {
        position: static;
    }
}
CSS;

ob_start();
?>

<div class="layout-split calendar-layout">
    <!-- Calendario -->
    <section style="flex: 3;">
        <!-- Controles de filtro -->
        <div class="calendar-controls" style="margin-bottom: 16px;">
            <div>
                <select id="filtroProfesional" class="form-control">
                    <option value="todos">Todos los profesionales</option>
                </select>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn--outline btn--sm" onclick="cambiarSemana(-1)">← Anterior</button>
                <button class="btn btn--primary btn--sm" id="btnHoy">Hoy</button>
                <button class="btn btn--outline btn--sm" onclick="cambiarSemana(1)">Siguiente →</button>
            </div>
            <span class="text-muted fw-semibold" id="rangoSemanal" style="margin-left: auto;"></span>
        </div>

        <!-- Timeline del calendario -->
        <div class="calendar-timeline" id="calendarTimeline">
            <!-- Headers generados por JS -->
        </div>
    </section>

    <!-- Panel de detalles -->
    <aside class="card panel-detalles" id="panelDetalles">
        <h3 class="card__title">📋 Detalles de la Cita</h3>
        <div id="contenidoDetalles" class="text-muted text-center" style="padding: 20px 0;">
            <p>Seleccione una cita en el calendario para inspeccionar sus detalles.</p>
        </div>
    </aside>
</div>

<?php
$pageContent = ob_get_clean();

$extraJS = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    const HORAS = ['08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'];
    const DIAS_NOMBRES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    let semanaActual = new Date();
    let citasData = [];
    let profesionales = [];

    // Inicializar
    inicializarCalendario();
    cargarProfesionales();
    cargarCitasSemana();

    document.getElementById('filtroProfesional').addEventListener('change', filtrarCitas);
    document.getElementById('btnHoy').addEventListener('click', () => {
        semanaActual = new Date();
        inicializarCalendario();
        cargarCitasSemana();
    });

    /**
     * Inicializa la estructura del calendario con la semana actual
     */
    function inicializarCalendario() {
        const inicioSemana = obtenerInicioSemana(semanaActual);
        const timeline = document.getElementById('calendarTimeline');

        // Calcular días de la semana (Lun-Sáb, 6 días)
        const dias = [];
        for (let i = 1; i <= 6; i++) {
            const fecha = new Date(inicioSemana);
            fecha.setDate(fecha.getDate() + i);
            dias.push(fecha);
        }

        // Actualizar rango semanal
        const primerDia = dias[0];
        const ultimoDia = dias[dias.length - 1];
        document.getElementById('rangoSemanal').textContent =
            \`\${formatFecha(primerDia)} - \${formatFecha(ultimoDia)}\`;

        // Generar headers
        let html = '<div class="timeline-header">Hora</div>';
        dias.forEach(fecha => {
            const nombreDia = DIAS_NOMBRES[fecha.getDay()];
            const numDia = fecha.getDate();
            html += \`<div class="timeline-header">\${nombreDia} \${numDia}</div>\`;
        });

        // Generar filas de horas
        HORAS.forEach(hora => {
            html += \`<div class="time-cell">\${hora}</div>\`;
            dias.forEach((fecha, idx) => {
                html += \`<div class="calendar-cell" id="cell-\${idx}-\${hora.replace(/[:\\s]/g, '')}" data-day="\${idx}" data-hour="\${hora}"></div>\`;
            });
        });

        timeline.innerHTML = html;
    }

    /**
     * Obtiene el inicio de la semana (lunes)
     * @param {Date} fecha
     * @returns {Date}
     */
    function obtenerInicioSemana(fecha) {
        const d = new Date(fecha);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.setDate(diff));
    }

    /**
     * Formatea una fecha legible
     * @param {Date} fecha
     * @returns {string}
     */
    function formatFecha(fecha) {
        const opciones = { day: 'numeric', month: 'short' };
        return fecha.toLocaleDateString('es-CO', opciones);
    }

    /**
     * Cambia la semana visible
     * @param {number} delta - -1 anterior, +1 siguiente
     */
    window.cambiarSemana = function(delta) {
        semanaActual.setDate(semanaActual.getDate() + (delta * 7));
        inicializarCalendario();
        cargarCitasSemana();
    };

    /**
     * Carga los profesionales disponibles
     */
    async function cargarProfesionales() {
        try {
            if (api) {
                const res = await api.get('/staffing/professionals');
                if (res.success && res.data) {
                    profesionales = res.data;
                    const select = document.getElementById('filtroProfesional');
                    profesionales.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p.professional_profile_id;
                        option.textContent = \`\${p.first_name} \${p.last_name}\`;
                        select.appendChild(option);
                    });
                    return;
                }
            }
        } catch (error) {
            console.warn('[Calendario] API no disponible para profesionales');
        }

        profesionales = [
            { professional_profile_id: 1, first_name: 'Carolina', last_name: 'Mora' },
            { professional_profile_id: 2, first_name: 'Andrea', last_name: 'Gómez' },
        ];
        const select = document.getElementById('filtroProfesional');
        profesionales.forEach(p => {
            const option = document.createElement('option');
            option.value = p.professional_profile_id;
            option.textContent = \`\${p.first_name} \${p.last_name}\`;
            select.appendChild(option);
        });
    }

    /**
     * Carga las citas de la semana desde la API
     */
    async function cargarCitasSemana() {
        const filtro = document.getElementById('filtroProfesional').value;

        try {
            if (api) {
                const inicio = obtenerInicioSemana(semanaActual);
                const fin = new Date(inicio);
                fin.setDate(fin.getDate() + 7);

                const params = new URLSearchParams({
                    date_start: inicio.toISOString().split('T')[0],
                    date_end: fin.toISOString().split('T')[0],
                });
                if (filtro !== 'todos') params.append('professional_id', filtro);

                const res = await api.get(\`/booking/appointments?\${params}\`);
                if (res.success && res.data) {
                    citasData = res.data;
                    renderCitas();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Calendario] API no disponible:', error.message);
        }

        // Fallback simulado
        citasData = [
            { appointment_id: 1, scheduled_timestamp: '2026-06-15 08:00:00', service_name: 'Corte y Secado', client_name: 'Diana Restrepo', professional_name: 'Carolina Mora', total_price: 35.00, appointment_status: 'CONFIRMED', service_category: 'cabello' },
            { appointment_id: 2, scheduled_timestamp: '2026-06-16 08:00:00', service_name: 'Manicure Gel', client_name: 'Milena Castro', professional_name: 'Andrea Gómez', total_price: 15.00, appointment_status: 'CONFIRMED', service_category: 'nail' },
            { appointment_id: 3, scheduled_timestamp: '2026-06-17 10:00:00', service_name: 'Limpieza Facial', client_name: 'Carlos Pérez', professional_name: 'Carolina Mora', total_price: 40.00, appointment_status: 'PENDING', service_category: 'facial' },
            { appointment_id: 4, scheduled_timestamp: '2026-06-18 10:00:00', service_name: 'Balayage', client_name: 'Sandra Muñoz', professional_name: 'Carolina Mora', total_price: 120.00, appointment_status: 'CONFIRMED', service_category: 'cabello' },
            { appointment_id: 5, scheduled_timestamp: '2026-06-18 14:00:00', service_name: 'Pedicure Spa', client_name: 'Patricia Ortiz', professional_name: 'Andrea Gómez', total_price: 22.00, appointment_status: 'PENDING', service_category: 'nail' },
        ];
        renderCitas();
    }

    /**
     * Renderiza las citas en las celdas del calendario
     */
    function renderCitas() {
        // Limpiar celdas
        document.querySelectorAll('.calendar-cell').forEach(cell => cell.innerHTML = '');

        citasData.forEach(cita => {
            const fecha = new Date(cita.scheduled_timestamp.replace(' ', 'T'));
            const inicioSemana = obtenerInicioSemana(semanaActual);

            // Calcular índice del día (1=lun, 6=sáb)
            const dayIdx = fecha.getDay();
            if (dayIdx === 0 || dayIdx === 7) return; // Saltar domingos

            const cellIdx = dayIdx - 1;
            const hora = fecha.getHours();
            const horaStr = HORAS[hora - 8]; // Asumiendo inicio a las 8 AM
            if (!horaStr) return;

            const cellId = \`cell-\${cellIdx}-\${horaStr.replace(/[:\\s]/g, '')}\`;
            const cell = document.getElementById(cellId);
            if (!cell) return;

            const catClass = cita.service_category === 'nail' ? 'appointment-block--nail'
                           : cita.service_category === 'facial' ? 'appointment-block--facial' : '';

            const block = document.createElement('div');
            block.className = \`appointment-block \${catClass}\`;
            block.onclick = () => verDetallesCita(cita);
            block.innerHTML = \`
                <strong>\${escapeHtml(cita.client_name)}</strong><br>
                <span style="font-size:0.7rem;">\${escapeHtml(cita.service_name)}</span>
            \`;

            cell.appendChild(block);
        });
    }

    /**
     * Filtra las citas por profesional seleccionado
     */
    function filtrarCitas() {
        cargarCitasSemana();
    }

    /**
     * Muestra los detalles de una cita en el panel lateral
     * @param {object} cita
     */
    function verDetallesCita(cita) {
        const contenedor = document.getElementById('contenidoDetalles');
        const statusMap = {
            'PENDING': { label: 'Programada', class: 'badge--info' },
            'CONFIRMED': { label: 'Confirmada', class: 'badge--success' },
            'IN_PROGRESS': { label: 'En Curso', class: 'badge--warning' },
            'COMPLETED': { label: 'Completada', class: 'badge--success' },
            'CANCELLED': { label: 'Cancelada', class: 'badge--danger' },
            'NOSHOW': { label: 'No Asistió', class: 'badge--neutral' }
        };
        const status = statusMap[cita.appointment_status] || { label: cita.appointment_status, class: 'badge--neutral' };

        contenedor.innerHTML = \`
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">SERVICIO</label>
                <div class="fw-bold" style="font-size:1.1rem;">\${escapeHtml(cita.service_name)}</div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">CLIENTE</label>
                <div>\${escapeHtml(cita.client_name)}</div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">HORA PROGRAMADA</label>
                <div>⏰ \${cita.scheduled_timestamp}</div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">PROFESIONAL</label>
                <div>👤 \${escapeHtml(cita.professional_name)}</div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">ESTADO</label>
                <div><span class="badge \${status.class}">\${status.label}</span></div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:0.75rem;">VALOR</label>
                <div class="fw-bold text-success">\$\${parseFloat(cita.total_price).toFixed(2)}</div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn--outline btn--sm" style="flex:1;" onclick="reprogramarCita(\${cita.appointment_id})">Reasignar</button>
                <button class="btn btn--outline-danger btn--sm" style="flex:1;" onclick="cancelarCita(\${cita.appointment_id})">Cancelar</button>
            </div>
        \`;
    }

    /**
     * Abre flujo de reprogramación
     * @param {number} id
     */
    window.reprogramarCita = function(id) {
        alerts.info('Redirigiendo a edición de cita #' + id + '...');
    };

    /**
     * Cancela una cita
     * @param {number} id
     */
    window.cancelarCita = async function(id) {
        if (!confirm('¿Confirmar la cancelación de esta cita?')) return;

        try {
            if (api) {
                const res = await api.patch(\`/booking/appointments/\${id}/cancel\`, {
                    change_reason: 'Cancelación desde el calendario maestro por administrador.'
                });
                if (res.success) {
                    alerts.success('Cita cancelada exitosamente.');
                    cargarCitasSemana();
                    document.getElementById('contenidoDetalles').innerHTML = '<p class="text-center text-muted" style="padding:20px 0;">Seleccione una cita para ver sus detalles.</p>';
                }
            } else {
                alerts.success('Cita cancelada (modo simulación).');
            }
        } catch (error) {
            alerts.error(error.message || 'Error al cancelar la cita.');
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
