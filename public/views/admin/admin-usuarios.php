<?php declare(strict_types=1);
/**
 * Control de Usuarios y Roles - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Administración de niveles de autorización y RBAC para el personal.
 * Consume: /api/v1/iam/users
 *          /api/v1/iam/roles
 */

define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Control de Usuarios y Roles';
$pageSubtitle = 'Administre los niveles de autorización y el acceso a módulos para el personal de la estética.';
$activeModule = 'usuarios';

$extraCSS = <<<CSS
.select-role {
    padding: 6px 12px;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    background-color: #fff;
    cursor: pointer;
    min-width: 180px;
}
.select-role:focus {
    border-color: var(--color-accent);
    outline: none;
    box-shadow: 0 0 0 3px var(--color-primary-light);
}
.user-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}
.user-status::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.user-status--active::before { background-color: var(--color-success); }
.user-status--inactive::before { background-color: var(--color-danger); }

/* Modal System Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: none; /* Oculto por defecto */
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.modal-overlay--visible {
    display: flex;
    opacity: 1;
}
.modal {
    background: white;
    width: 100%;
    max-width: 550px;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}
CSS;

ob_start();
?>

<!-- Indicadores de usuarios -->
<section class="metrics-grid" style="margin-bottom:24px;">
    <div class="card metric-card" style="border-left-color: var(--color-accent);">
        <span class="metric-card__label">Usuarios Totales</span>
        <div class="metric-card__value" id="totalUsuarios">0</div>
    </div>
    <div class="card metric-card" style="border-left-color: var(--color-success);">
        <span class="metric-card__label">Personal Activo</span>
        <div class="metric-card__value" id="personalActivo">0</div>
    </div>
    <div class="card metric-card" style="border-left-color: #9b59b6;">
        <span class="metric-card__label">Administradores</span>
        <div class="metric-card__value" id="totalAdmins">0</div>
    </div>
</section>

<!-- Tabla de usuarios del personal -->
<section class="card">
    <div class="d-flex justify-between align-center" style="margin-bottom:16px;">
        <h3 class="card__title" style="margin:0;border:none;padding:0;">Personal del Sistema</h3>
        <button class="btn btn--primary" onclick="abrirModalNuevoUsuario()">➕ Nuevo Usuario</button>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Completo</th>
                    <th>Correo</th>
                    <th>Rol Actual</th>
                    <th>Estado</th>
                    <th style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaUsuarios">
                <tr><td colspan="6" class="text-center text-muted p-5">Cargando usuarios...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Modal para nuevo usuario / editar rol -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title" id="modalUsuarioTitulo">Nuevo Usuario</h3>
            <button class="modal__close" onclick="cerrarModalUsuario()">&times;</button>
        </div>
        <div class="modal__body">
            <form id="formUsuario" novalidate>
                <input type="hidden" id="usuarioId" value="">
                <div class="form-group">
                    <label for="inputNombre" class="form-label">Nombre Completo</label>
                    <input type="text" id="inputNombre" class="form-control" required maxlength="200">
                </div>
                <div class="form-group">
                    <label for="inputEmail" class="form-label">Correo Electrónico</label>
                    <input type="email" id="inputEmail" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="inputPhone" class="form-label">Teléfono (WhatsApp)</label>
                    <input type="tel" id="inputPhone" class="form-control" required placeholder="+573000000000">
                </div>
                <div class="form-group" id="groupPassword">
                    <label for="inputPassword" class="form-label">Contraseña</label>
                    <input type="password" id="inputPassword" class="form-control" minlength="8" autocomplete="new-password">
                    <span class="form-hint">Mínimo 8 caracteres. Dejar vacío para no cambiar.</span>
                </div>
                <div class="form-group">
                    <label for="inputRol" class="form-label">Asignar Rol</label>
                    <select id="inputRol" class="form-control" required>
                        <option value="" disabled selected>-- Seleccione un Rol --</option>
                        <option value="SUPER_ADMIN">Administrador Global</option>
                        <option value="BRANCH_ADMIN">Administrador de Sucursal</option>
                        <option value="RECEPCIONIST">Recepcionista</option>
                        <option value="PROFESSIONAL">Especialista Técnico</option>
                        <option value="CLIENT">Cliente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" id="inputActivo" checked> Usuario Activo
                    </label>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button class="btn btn--outline" onclick="cerrarModalUsuario()">Cancelar</button>
            <button class="btn btn--primary" onclick="guardarUsuario()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal para habilidades técnicas (Staff) -->
<div class="modal-overlay" id="modalHabilidades">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Habilidades Técnicas</h3>
            <button class="modal__close" onclick="cerrarModalHabilidades()">&times;</button>
        </div>
        <div class="modal__body" style="max-height: 400px; overflow-y: auto;">
            <p class="text-muted mb-4" style="font-size: 0.85rem;">Seleccione los servicios que este profesional está capacitado para realizar.</p>
            <input type="hidden" id="habilidadesUserId" value="">
            <div id="listaServiciosHabilidades" class="d-flex flex-column gap-2">
                <!-- Se llena vía JS -->
            </div>
        </div>
        <div class="modal__footer">
            <button class="btn btn--outline" onclick="cerrarModalHabilidades()">Cancelar</button>
            <button class="btn btn--primary" onclick="guardarHabilidades()">Guardar Cambios</button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJS = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;

    let usuarios = [];

    cargarUsuarios();

    async function cargarUsuarios() {
        try {
            if (api) {
                const res = await api.get('/iam/users?role=staff');
                if (res.success && res.data) {
                    usuarios = res.data;
                    renderUsuarios();
                    actualizarIndicadores();
                    return;
                }
            }
        } catch (error) {
            console.warn('[Usuarios] API no disponible:', error.message);
        }

        usuarios = [];
        renderUsuarios();
        actualizarIndicadores();
    }

    function actualizarIndicadores() {
        const activos = usuarios.filter(u => u.is_active === 1);
        const admins = usuarios.filter(u => u.role_name === 'SUPER_ADMIN' || u.role_name === 'BRANCH_ADMIN');
        document.getElementById('totalUsuarios').textContent = usuarios.length;
        document.getElementById('personalActivo').textContent = activos.length;
        document.getElementById('totalAdmins').textContent = admins.length;
    }

    function renderUsuarios() {
        const tbody = document.getElementById('tablaUsuarios');

        if (!usuarios.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-5">No hay usuarios registrados.</td></tr>';
            return;
        }

        const roleLabels = {
            'SUPER_ADMIN': { label: 'Admin Global', class: 'badge--danger' },
            'BRANCH_ADMIN': { label: 'Admin Sucursal', class: 'badge--warning' },
            'RECEPCIONIST': { label: 'Recepcionista', class: 'badge--info' },
            'PROFESSIONAL': { label: 'Especialista', class: 'badge--success' },
            'CLIENT': { label: 'Cliente', class: 'badge--neutral' },
        };

        tbody.innerHTML = usuarios.map(u => {
            const role = roleLabels[u.role_name] || { label: u.role_name || 'Sin Rol', class: 'badge--neutral' };
            const statusClass = u.is_active ? 'user-status--active' : 'user-status--inactive';
            const statusText = u.is_active ? 'Activo' : 'Inactivo';

            // Skill: Botón condicional para personal técnico/admin (Fase 2 corregida)
            const showHabilidades = ['SUPER_ADMIN', 'BRANCH_ADMIN', 'PROFESSIONAL'].includes(u.role_name);
            const habilidadesBtn = showHabilidades 
                ? `<button class="btn btn--ghost btn--sm" onclick="abrirModalHabilidades(${u.user_id})" title="Gestionar Habilidades Técnicas">💼</button>`
                : '';

            return `
                <tr id="user-row-${u.user_id}">
                    <td class="fw-bold">#${u.user_id}</td>
                    <td>${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</td>
                    <td class="text-muted">${escapeHtml(u.email)}</td>
                    <td><span class="badge ${role.class}">${role.label}</span></td>
                    <td><span class="user-status ${statusClass}">${statusText}</span></td>
                    <td style="text-align:center;">
                        ${habilidadesBtn}
                        <button class="btn btn--ghost btn--sm" onclick="editarUsuario(${u.user_id})" title="Editar">✏️</button>
                        <button class="btn btn--ghost btn--sm" onclick="toggleEstadoUsuario(${u.user_id})" title="Activar/Desactivar">
                            ${u.is_active ? '⏸️' : '▶️'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    window.abrirModalNuevoUsuario = function() {
        document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo Usuario';
        document.getElementById('usuarioId').value = '';
        document.getElementById('formUsuario').reset();
        document.getElementById('inputActivo').checked = true;
        document.getElementById('groupPassword').style.display = '';
        document.getElementById('inputPassword').required = true;
        document.getElementById('modalUsuario').classList.add('modal-overlay--visible');
    };

    window.editarUsuario = function(id) {
        const user = usuarios.find(u => u.user_id === id);
        if (!user) return;

        document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuario';
        document.getElementById('usuarioId').value = id;
        document.getElementById('inputNombre').value = `${user.first_name} ${user.last_name}`;
        document.getElementById('inputEmail').value = user.email;
        document.getElementById('inputPhone').value = user.auth_phone || '';
        document.getElementById('inputPassword').value = '';
        document.getElementById('inputPassword').required = false;
        document.getElementById('groupPassword').style.display = '';
        document.getElementById('inputRol').value = user.role_name || '';
        document.getElementById('inputActivo').checked = !!user.is_active;
        document.getElementById('modalUsuario').classList.add('modal-overlay--visible');
    };

    window.cerrarModalUsuario = function() {
        document.getElementById('modalUsuario').classList.remove('modal-overlay--visible');
    };

    window.guardarUsuario = async function() {
        const id = document.getElementById('usuarioId').value;
        const nombre = document.getElementById('inputNombre').value.trim();
        const email = document.getElementById('inputEmail').value.trim();
        const phone = document.getElementById('inputPhone').value.trim();
        const password = document.getElementById('inputPassword').value;
        const rol = document.getElementById('inputRol').value;
        const activo = document.getElementById('inputActivo').checked;

        if (!nombre || !email || !phone) {
            alerts.error('Nombre, correo y teléfono son obligatorios.');
            return;
        }

        const nameParts = nombre.split(' ');
        const firstName = nameParts[0];
        const lastName = nameParts.slice(1).join(' ') || '';

        const payload = {
            first_name: firstName,
            last_name: lastName,
            email: email,
            phone: phone,
            role: rol,
            is_active: activo ? 1 : 0,
        };

        try {
            if (api) {
                let res;
                if (id) {
                    // Actualizar
                    if (password) payload.password = password;
                    res = await api.put(`/iam/users/${id}`, payload);
                } else {
                    if (!password) {
                        alerts.error('La contraseña es obligatoria para nuevos usuarios.');
                        return;
                    }
                    payload.password = password;
                    payload.username = email.split('@')[0];
                    res = await api.post('/iam/users', payload);
                }

                if (!res.success) {
                    throw new Error(res.detail || 'El servidor no pudo procesar la actualización del usuario.');
                }

                alerts.success(id ? 'Usuario actualizado correctamente.' : 'Usuario creado exitosamente.');
                cerrarModalUsuario();
                await cargarUsuarios();
                return;
            }

            // Simulación
            alerts.success(id ? 'Usuario actualizado correctamente (Modo Desarrollo).' : 'Usuario creado exitosamente (Modo Desarrollo).');
            cerrarModalUsuario();
            
            if (id) {
                // Actualizar localmente para reflejar cambios en la UI
                const index = usuarios.findIndex(u => u.user_id == id);
                if (index !== -1) {
                    usuarios[index] = { 
                        ...usuarios[index], 
                        first_name: firstName, 
                        last_name: lastName, 
                        email: email, 
                        auth_phone: phone,
                        role_name: rol, 
                        is_active: activo ? 1 : 0 
                    };
                }
            } else {
                usuarios.push({
                    user_id: usuarios.length + 1,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    auth_phone: phone,
                    role_name: rol,
                    is_active: activo ? 1 : 0
                });
            }
            renderUsuarios();
            actualizarIndicadores();
        } catch (error) {
            alerts.error(error.message || 'Error al guardar el usuario.');
        }
    };

    window.toggleEstadoUsuario = async function(id) {
        const user = usuarios.find(u => u.user_id === id);
        if (!user) return;

        const nuevoEstado = user.is_active ? 0 : 1;
        const accion = nuevoEstado ? 'activar' : 'desactivar';

        if (!confirm(`¿Desea ${accion} al usuario ${user.first_name} ${user.last_name}?`)) return;

        try {
            if (api) {
                const res = await api.patch(`/iam/users/${id}`, { is_active: nuevoEstado });
                if (res.success) {
                    alerts.success(`Usuario ${nuevoEstado ? 'activado' : 'desactivado'} correctamente.`);
                    await cargarUsuarios();
                    return;
                }
            }

            user.is_active = nuevoEstado;
            renderUsuarios();
            actualizarIndicadores();
            alerts.success(`Usuario ${nuevoEstado ? 'activado' : 'desactivado'} (simulación).`);
        } catch (error) {
            alerts.error(error.message || 'Error al cambiar el estado del usuario.');
        }
    };

    // --- Gestión de Habilidades Técnicas (Fase 2 JS) ---
    window.abrirModalHabilidades = async function(id) {
        const user = usuarios.find(u => u.user_id === id);
        if (!user) return;

        document.getElementById('habilidadesUserId').value = id;
        const container = document.getElementById('listaServiciosHabilidades');
        container.innerHTML = '<p class="text-center p-4">Cargando catálogo...</p>';
        document.getElementById('modalHabilidades').classList.add('modal-overlay--visible');

        try {
            const res = await api.get(`/iam/professionals/${id}/services`);
            if (res.success && res.data) {
                const { all_services, assigned_ids } = res.data;
                
                if (all_services.length === 0) {
                    container.innerHTML = '<p class="text-center text-muted">No hay servicios activos en el catálogo.</p>';
                    return;
                }

                container.innerHTML = all_services.map(s => {
                    const isChecked = assigned_ids.includes(s.service_id) ? 'checked' : '';
                    return `
                        <label class="checkbox-container" style="padding: 8px; border-bottom: 1px solid var(--color-border-light);">
                            <input type="checkbox" name="servicio_habilidad" value="${s.service_id}" ${isChecked}>
                            ${escapeHtml(s.name)}
                        </label>
                    `;
                }).join('');
            }
        } catch (error) {
            alerts.error('Error al cargar servicios del profesional.');
            cerrarModalHabilidades();
        }
    };

    window.cerrarModalHabilidades = function() {
        document.getElementById('modalHabilidades').classList.remove('modal-overlay--visible');
    };

    window.guardarHabilidades = async function() {
        const id = document.getElementById('habilidadesUserId').value;
        const checkboxes = document.querySelectorAll('input[name="servicio_habilidad"]:checked');
        const serviceIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        try {
            const res = await api.post(`/iam/professionals/${id}/services`, { service_ids: serviceIds });
            if (res.success) {
                alerts.success('Habilidades técnicas actualizadas correctamente.');
                cerrarModalHabilidades();
            }
        } catch (error) {
            alerts.error(error.message || 'Error al guardar habilidades.');
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
