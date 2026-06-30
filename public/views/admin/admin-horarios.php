<?php declare(strict_types=1);
/**
 * Configuración de Horarios - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Gestión de jornadas laborales por profesional.
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Configuración de Horarios';
$pageSubtitle = 'Establezca y modifique las jornadas laborables y de descanso de cada profesional.';
$activeModule = 'horarios';

// CSS específico
$extraCSS = <<<CSS
.day-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: var(--color-bg-card);
    padding: 12px 16px;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    transition: all var(--transition-fast);
}
.day-item--disabled {
    background-color: var(--color-bg-input-disabled);
    opacity: 0.7;
}
.time-range-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.time-range-group input[type="time"] {
    width: 110px;
}
CSS;

// Contenido
ob_start();
?>

<div id="horario-alert-container"></div>

<div class="layout-split">
    <!-- Selector de profesional -->
    <section class="card" style="flex: 1;">
        <h3 class="card__title">1. Seleccionar Profesional</h3>
        <div class="form-group">
            <label for="profesionalSelect" class="form-label">Especialista disponible</label>
            <select id="profesionalSelect" class="form-control" required>
                <option value="" disabled selected>-- Seleccione un miembro del personal --</option>
            </select>
        </div>
        <p class="form-hint">💡 Al cambiar de profesional, se cargarán sus configuraciones de días vigentes.</p>

        <!-- Sección de excepciones de agenda -->
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--color-border);">
            <h3 class="card__title">2. Excepciones de Agenda</h3>
            <p class="form-hint mb-3">Registre vacaciones, incapacidades o cierres especiales.</p>
            <button class="btn btn--outline btn--block" onclick="abrirModalExcepcion()">
                ➕ Agregar Excepción
            </button>
            <div id="listaExcepciones" style="margin-top: 16px;"></div>
        </div>
    </section>

    <!-- Definición de jornada semanal -->
    <section class="card" style="flex: 2;">
        <h3 class="card__title">3. Definir Jornada Semanal</h3>
        <p class="text-muted mb-4">Marque los días activos y asigne la hora de apertura y cierre.</p>

        <div id="daysList" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
            <!-- Días generados por JS -->
        </div>

        <div class="text-center">
            <button type="button" class="btn btn--primary" style="min-width: 200px;" onclick="guardarHorarios()">
                Guardar Horario
            </button>
        </div>
    </section>
</div>

<!-- Modal para excepciones -->
<div class="modal-overlay" id="modalExcepcion">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Agregar Excepción de Agenda</h3>
            <button class="modal__close" onclick="cerrarModalExcepcion()">&times;</button>
        </div>
        <div class="modal__body">
            <div class="form-group">
                <label for="excFecha" class="form-label">Fecha</label>
                <input type="date" id="excFecha" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="excDescripcion" class="form-label">Motivo</label>
                <input type="text" id="excDescripcion" class="form-control" placeholder="Ej. Vacaciones, Cierre por mantenimiento..." required maxlength="150">
            </div>
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" id="excGlobal"> Aplicar a toda la red de sucursales
                </label>
            </div>
        </div>
        <div class="modal__footer">
            <button class="btn btn--outline" onclick="cerrarModalExcepcion()">Cancelar</button>
            <button class="btn btn--primary" onclick="guardarExcepcion()">Guardar</button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

// JS específico
$extraJS = <<<JS
function initHorarios() {
    var api    = window.adminApi;
    var alerts = window.adminAlerts;

    // Días de la semana (0 = Domingo, 1 = Lunes, etc.)
    var DIAS_SEMANA = [
        { key: 'lunes',     label: 'Lunes',                dayOfWeek: 1 },
        { key: 'martes',    label: 'Martes',               dayOfWeek: 2 },
        { key: 'miercoles', label: 'Miércoles',            dayOfWeek: 3 },
        { key: 'jueves',    label: 'Jueves',               dayOfWeek: 4 },
        { key: 'viernes',   label: 'Viernes',              dayOfWeek: 5 },
        { key: 'sabado',    label: 'Sábado',               dayOfWeek: 6 },
        { key: 'domingo',   label: 'Domingo (No Laborable)', dayOfWeek: 0 },
    ];

    var profesionales    = [];
    var horariosActuales = {};

    /* ------------------------------------------------------------------
     * toggleDia — Habilita/deshabilita los inputs de tiempo de un día
     * ------------------------------------------------------------------ */
    window.toggleDia = function(key) {
        var chk   = document.getElementById('check-' + key);
        var item  = document.getElementById('day-item-' + key);
        var times = item.querySelectorAll('input[type="time"]');

        times.forEach(function(t) { t.disabled = !chk.checked; });
        item.classList.toggle('day-item--disabled', !chk.checked);
    };

    // Inicializar días y cargar profesionales
    renderDias();
    cargarProfesionales();

    document.getElementById('profesionalSelect').addEventListener('change', function(e) {
        cargarHorariosProfesional(e.target.value);
    });

    /* ------------------------------------------------------------------
     * renderDias — Construye la grilla de días usando métodos DOM puros
     * ------------------------------------------------------------------ */
    function renderDias() {
        var container = document.getElementById('daysList');
        container.innerHTML = '';

        DIAS_SEMANA.forEach(function(dia) {
            var item = document.createElement('div');
            item.className = 'day-item';
            item.id = 'day-item-' + dia.key;

            // Checkbox + etiqueta
            var lbl      = document.createElement('label');
            lbl.className = 'checkbox-container';

            var chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.id   = 'check-' + dia.key;
            chk.setAttribute('data-day', dia.dayOfWeek);
            // Capturar diaKey en closure para evitar bug de referencia compartida
            var diaKey = dia.key;
            chk.addEventListener('change', function() { window.toggleDia(diaKey); });

            lbl.appendChild(chk);
            lbl.appendChild(document.createTextNode(' ' + dia.label));

            // Grupo de horas
            var grp = document.createElement('div');
            grp.className = 'time-range-group';

            var tInicio = document.createElement('input');
            tInicio.type = 'time';
            tInicio.id   = 'inicio-' + dia.key;
            tInicio.className = 'form-control';
            tInicio.value = '08:00';

            var sep = document.createElement('span');
            sep.className   = 'text-muted';
            sep.textContent = 'a';

            var tFin = document.createElement('input');
            tFin.type = 'time';
            tFin.id   = 'fin-' + dia.key;
            tFin.className = 'form-control';
            tFin.value = '18:00';

            grp.appendChild(tInicio);
            grp.appendChild(sep);
            grp.appendChild(tFin);

            item.appendChild(lbl);
            item.appendChild(grp);
            container.appendChild(item);
        });

        // Domingo inactivo por defecto
        document.getElementById('check-domingo').checked = false;
        window.toggleDia('domingo');
    }

    /* ------------------------------------------------------------------
     * cargarProfesionales — Llena el <select> desde la API REST
     * ------------------------------------------------------------------ */
    function cargarProfesionales() {
        var select = document.getElementById('profesionalSelect');
        select.innerHTML = '<option value="" disabled selected>-- Seleccione un miembro del personal --</option>';

        if (!api) {
            usarProfesionalesFallback(select);
            return;
        }

        api.get('/staffing/professionals')
            .then(function(res) {
                console.log('[Horarios] Respuesta API profesionales:', res);
                if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                    profesionales = res.data;
                    profesionales.forEach(function(p) {
                        var opt = document.createElement('option');
                        opt.value       = p.professional_profile_id;
                        opt.textContent = p.first_name + ' ' + p.last_name
                                        + ' — ' + (p.public_biography || 'Esteticista');
                        select.appendChild(opt);
                    });
                } else {
                    console.warn('[Horarios] API sin profesionales. Activando fallback.');
                    usarProfesionalesFallback(select);
                }
            })
            .catch(function(err) {
                console.warn('[Horarios] Error al consultar API:', err.message);
                usarProfesionalesFallback(select);
            });
    }

    function usarProfesionalesFallback(select) {
        profesionales = [
            { professional_profile_id: 1, first_name: 'Carolina', last_name: 'Mora',  public_biography: 'Estilista Principal'     },
            { professional_profile_id: 2, first_name: 'Andrea',   last_name: 'Gómez', public_biography: 'Especialista en Uñas'    },
        ];
        profesionales.forEach(function(p) {
            var opt = document.createElement('option');
            opt.value       = p.professional_profile_id;
            opt.textContent = p.first_name + ' ' + p.last_name
                            + ' — ' + (p.public_biography || 'Esteticista');
            select.appendChild(opt);
        });
    }

    /* ------------------------------------------------------------------
     * cargarHorariosProfesional — Carga los horarios del profesional
     * ------------------------------------------------------------------ */
    function cargarHorariosProfesional(profesionalId) {
        if (!api) { usarHorariosFallback(); return; }

        api.get('/staffing/professionals/' + profesionalId + '/schedules')
            .then(function(res) {
                if (res.success && res.data) {
                    aplicarHorariosDesdeAPI(res.data);
                } else {
                    usarHorariosFallback();
                }
            })
            .catch(function(err) {
                console.warn('[Horarios] Error cargando horarios:', err.message);
                usarHorariosFallback();
            });
    }

    function usarHorariosFallback() {
        aplicarHorariosDesdeAPI([
            { day_of_week: 1, start_time: '08:00:00', end_time: '18:00:00' },
            { day_of_week: 2, start_time: '08:00:00', end_time: '18:00:00' },
            { day_of_week: 3, start_time: '08:00:00', end_time: '18:00:00' },
            { day_of_week: 4, start_time: '08:00:00', end_time: '18:00:00' },
            { day_of_week: 5, start_time: '08:00:00', end_time: '19:00:00' },
            { day_of_week: 6, start_time: '09:00:00', end_time: '16:00:00' },
        ]);
    }

    /* ------------------------------------------------------------------
     * aplicarHorariosDesdeAPI — Refleja los datos en el formulario
     * ------------------------------------------------------------------ */
    function aplicarHorariosDesdeAPI(horarios) {
        horariosActuales = {};
        horarios.forEach(function(h) { horariosActuales[h.day_of_week] = h; });

        DIAS_SEMANA.forEach(function(dia) {
            var chk    = document.getElementById('check-'  + dia.key);
            var inicio = document.getElementById('inicio-' + dia.key);
            var fin    = document.getElementById('fin-'    + dia.key);
            var dato   = horariosActuales[dia.dayOfWeek];

            chk.checked = !!dato;
            inicio.value = dato ? dato.start_time.substring(0, 5) : '08:00';
            fin.value    = dato ? dato.end_time.substring(0, 5)   : '18:00';

            toggleDia(dia.key);
        });
    }

    /* ------------------------------------------------------------------
     * guardarHorarios — Persiste la jornada semanal en el servidor
     * ------------------------------------------------------------------ */
    window.guardarHorarios = function() {
        var profesionalId = document.getElementById('profesionalSelect').value;
        if (!profesionalId) {
            alerts.error('Debe seleccionar un profesional primero.');
            return;
        }

        var agenda = DIAS_SEMANA.map(function(dia) {
            var activo = document.getElementById('check-' + dia.key).checked;
            return {
                day_of_week: dia.dayOfWeek,
                active:      activo,
                start_time:  activo ? document.getElementById('inicio-' + dia.key).value + ':00' : null,
                end_time:    activo ? document.getElementById('fin-'    + dia.key).value + ':00' : null,
            };
        });

        var payload = {
            professional_profile_id: parseInt(profesionalId),
            branch_id: 1,
            schedules: agenda
        };

        if (!api) {
            alerts.success('Horarios guardados. (modo simulación)');
            return;
        }

        api.post('/staffing/schedules', payload)
            .then(function(res) {
                if (res.success) {
                    alerts.success('Horarios semanales guardados y sincronizados correctamente.');
                } else {
                    alerts.error(res.message || 'Error al guardar los horarios.');
                }
            })
            .catch(function(err) {
                alerts.error(err.message || 'Error al guardar los horarios.');
            });
    };

    /* ------------------------------------------------------------------
     * Modal de excepciones de agenda
     * ------------------------------------------------------------------ */
    window.abrirModalExcepcion = function() {
        document.getElementById('modalExcepcion').classList.add('modal-overlay--visible');
    };

    window.cerrarModalExcepcion = function() {
        document.getElementById('modalExcepcion').classList.remove('modal-overlay--visible');
        document.getElementById('excFecha').value        = '';
        document.getElementById('excDescripcion').value  = '';
        document.getElementById('excGlobal').checked     = false;
    };

    window.guardarExcepcion = function() {
        var fecha       = document.getElementById('excFecha').value;
        var descripcion = document.getElementById('excDescripcion').value.trim();

        if (!fecha || !descripcion) {
            alerts.error('Complete todos los campos de la excepción.');
            return;
        }

        var payload = {
            branch_id:             1,
            holiday_date:          fecha,
            description:           descripcion,
            block_entire_network:  document.getElementById('excGlobal').checked ? 1 : 0
        };

        if (!api) {
            alerts.success('Excepción registrada para el ' + fecha + '. (modo simulación)');
            cerrarModalExcepcion();
            return;
        }

        api.post('/staffing/exceptions', payload)
            .then(function() {
                alerts.success('Excepción registrada para el ' + fecha + '.');
                cerrarModalExcepcion();
            })
            .catch(function(err) {
                alerts.error(err.message || 'Error al registrar la excepción.');
            });
    };
}

// Arrancar cuando AdminApp esté listo
if (window.adminApi) {
    initHorarios();
} else {
    window.addEventListener('admin-app-ready', initHorarios, { once: true });
}
JS;

require_once __DIR__ . '/../layouts/admin-layout.php';
