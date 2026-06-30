<?php declare(strict_types=1);
/**
 * Gestión de Reseñas - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Visualización y moderación de calificaciones y comentarios de clientes.
 * Consume: /api/v1/crm/ratings
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Reseñas de Clientes';
$pageSubtitle = 'Revise los testimonios, comentarios y el puntaje de satisfacción asignado por sus usuarios.';
$activeModule = 'resenas';

$extraCSS = <<<CSS
.rating-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 768px) {
    .rating-summary {
        grid-template-columns: 1fr;
    }
}
.rating-summary-card {
    text-align: center;
    padding: 20px;
}
.rating-summary-card .big-score {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--color-text-dark);
}
.rating-summary-card .stars-big {
    font-size: 1.5rem;
    color: #f1c40f;
    letter-spacing: 3px;
}
.reseña-comment {
    font-style: italic;
    color: var(--color-text-muted);
    font-weight: 500;
    max-width: 400px;
}
CSS;

ob_start();
?>

<!-- Resumen de calificaciones -->
<section class="rating-summary">
    <div class="card rating-summary-card">
        <div class="metric-card__label">Calificación Global</div>
        <div class="big-score" id="ratingGlobal">0.0</div>
        <div class="stars-big" id="starsGlobal"></div>
        <small class="text-muted"><span id="totalResenas">0</span> reseñas totales</small>
    </div>
    <div class="card rating-summary-card">
        <div class="metric-card__label">Reseñas este Mes</div>
        <div class="big-score" id="resenasMes">0</div>
        <div class="metric-card__trend text-success" id="resenasMesTrend">—</div>
    </div>
    <div class="card rating-summary-card">
        <div class="metric-card__label">Satisfacción 5★</div>
        <div class="big-score" id="porcentaje5">0%</div>
        <div class="metric-card__trend" style="color:#f1c40f;">⭐ Clientes muy satisfechos</div>
    </div>
</section>

<!-- Filtros -->
<div class="d-flex align-center gap-3" style="margin-bottom:16px; flex-wrap:wrap;">
    <div>
        <select id="filtroProfesional" class="form-control" style="min-width:220px;">
            <option value="todos">Todos los profesionales</option>
        </select>
    </div>
    <div>
        <select id="filtroEstrellas" class="form-control" style="min-width:180px;">
            <option value="todos">Todas las calificaciones</option>
            <option value="5">5 estrellas</option>
            <option value="4">4 estrellas</option>
            <option value="3">3 estrellas</option>
            <option value="2">2 estrellas</option>
            <option value="1">1 estrella</option>
        </select>
    </div>
</div>

<!-- Tabla de reseñas -->
<section class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Profesional</th>
                    <th>Servicio</th>
                    <th>Calificación</th>
                    <th style="width:35%;">Comentario</th>
                    <th style="text-align:center;">Acción</th>
                </tr>
            </thead>
            <tbody id="tablaResenas">
                <tr><td colspan="6" class="text-center text-muted p-5">Cargando reseñas...</td></tr>
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

    let reseñas = [];
    let todosResenas = [];

    // Cargar datos
    cargarResenas();

    // Filtros
    document.getElementById('filtroProfesional').addEventListener('change', filtrarResenas);
    document.getElementById('filtroEstrellas').addEventListener('change', filtrarResenas);

    async function cargarResenas() {
        try {
            if (api) {
                const res = await api.get('/crm/ratings');
                if (res.success && res.data) {
                    todosResenas = res.data.ratings;
                    renderResumen(res.data.summary);
                    cargarProfesionalesEnFiltro(res.data.professionals);
                    reseñas = [...todosResenas];
                    renderResenas();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Resenas] API no disponible:', error.message);
        }

        // Datos simulados
        todosResenas = [
            { rating_id: 1, client_name: 'Diana Restrepo', professional_name: 'Carolina Mora', service_name: 'Corte de Cabello', score: 5, comments: '¡Excelente atención! El corte quedó idéntico a la referencia. Súper recomendada.', is_visible: 1, created_at: '2026-06-10' },
            { rating_id: 2, client_name: 'Milena Castro', professional_name: 'Andrea Gómez', service_name: 'Manicure Premium', score: 4, comments: 'Muy buen manicure, los materiales son de excelente calidad. Volveré pronto.', is_visible: 1, created_at: '2026-06-09' },
            { rating_id: 3, client_name: 'Carlos Pérez', professional_name: 'Carolina Mora', service_name: 'Limpieza Facial', score: 5, comments: 'La limpieza facial fue muy profesional. El ambiente de la estética es impecable.', is_visible: 1, created_at: '2026-06-08' },
            { rating_id: 4, client_name: 'Sandra Muñoz', professional_name: 'Carolina Mora', service_name: 'Balayage', score: 5, comments: 'El balayage quedó espectacular, exactamente como lo pedí.', is_visible: 1, created_at: '2026-06-07' },
            { rating_id: 5, client_name: 'Patricia Ortiz', professional_name: 'Andrea Gómez', service_name: 'Pedicure Spa', score: 3, comments: 'El servicio estuvo bien, pero hubo un poco de demora en la atención.', is_visible: 1, created_at: '2026-06-06' },
        ];

        renderResumen({
            rating_promedio: 4.4,
            total_resenas: 5,
            resenas_mes: 3,
            porcentaje_5: 60
        });

        cargarProfesionalesEnFiltro([
            { id: 1, nombre: 'Carolina Mora' },
            { id: 2, nombre: 'Andrea Gómez' },
        ]);

        reseñas = [...todosResenas];
        renderResenas();
    }

    function renderResumen(summary) {
        document.getElementById('ratingGlobal').textContent = (summary.rating_promedio ?? 0).toFixed(1);
        document.getElementById('starsGlobal').textContent = generarEstrellas(Math.round(summary.rating_promedio ?? 0));
        document.getElementById('totalResenas').textContent = summary.total_resenas ?? 0;
        document.getElementById('resenasMes').textContent = summary.resenas_mes ?? 0;
        document.getElementById('resenasMesTrend').textContent = summary.resenas_mes > 0 ? '▲ Nuevas reseñas este mes' : 'Sin actividad';
        document.getElementById('porcentaje5').textContent = \`\${summary.porcentaje_5 ?? 0}%\`;
    }

    function cargarProfesionalesEnFiltro(profesionales) {
        const select = document.getElementById('filtroProfesional');
        profesionales.forEach(p => {
            const option = document.createElement('option');
            option.value = p.nombre || p.first_name + ' ' + p.last_name;
            option.textContent = p.nombre || \`\${p.first_name} \${p.last_name}\`;
            select.appendChild(option);
        });
    }

    function renderResenas() {
        const tbody = document.getElementById('tablaResenas');

        if (!reseñas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay reseñas que coincidan con los filtros.</td></tr>';
            return;
        }

        tbody.innerHTML = reseñas.filter(r => r.is_visible !== 0).map(r => \`
            <tr id="resena-row-\${r.rating_id}">
                <td class="fw-bold">\${escapeHtml(r.client_name)}</td>
                <td>\${escapeHtml(r.professional_name)}</td>
                <td>\${escapeHtml(r.service_name)}</td>
                <td><span class="stars">\${generarEstrellas(r.score)}</span></td>
                <td class="reseña-comment">"\${escapeHtml(r.comments)}"</td>
                <td style="text-align:center;">
                    <button class="btn btn--outline-danger btn--sm" onclick="ocultarResena(\${r.rating_id})">Ocultar</button>
                </td>
            </tr>
        \`).join('');
    }

    function generarEstrellas(score) {
        let s = '';
        for (let i = 1; i <= 5; i++) {
            s += i <= score ? '★' : '☆';
        }
        return s;
    }

    function filtrarResenas() {
        const prof = document.getElementById('filtroProfesional').value;
        const estrellas = document.getElementById('filtroEstrellas').value;

        reseñas = todosResenas.filter(r => {
            const matchProf = prof === 'todos' || r.professional_name === prof;
            const matchEst = estrellas === 'todos' || r.score === parseInt(estrellas);
            return matchProf && matchEst;
        });

        renderResenas();
    }

    window.ocultarResena = async function(id) {
        if (!confirm('¿Desea ocultar este comentario de la vista pública? Permanecerá visible solo en auditorías.')) return;

        try {
            if (api) {
                const res = await api.patch(\`/crm/ratings/\${id}/hide\`);
                if (res.success) {
                    alerts.success('Comentario ocultado de la vista pública.');
                    reseñas = reseñas.filter(r => r.rating_id !== id);
                    todosResenas = todosResenas.filter(r => r.rating_id !== id);
                    renderResenas();
                    return;
                }
            }

            // Simulación
            reseñas = reseñas.filter(r => r.rating_id !== id);
            todosResenas = todosResenas.filter(r => r.rating_id !== id);
            renderResenas();
            alerts.success('Comentario ocultado (modo simulación).');
        } catch (error) {
            alerts.error(error.message || 'Error al ocultar la reseña.');
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
