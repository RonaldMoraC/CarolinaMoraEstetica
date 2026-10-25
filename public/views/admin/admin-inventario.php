<?php

/**
 * Gestión de Inventario - Panel Administrativo
 * Estética Carolina Mora - CarolinaMoraEstetica
 *
 * Módulo para control integral de productos, existencias, trazabilidad de movimientos
 * y borrado lógico con protección de integridad referencial.
 */

define('ADMIN_LAYOUT_LOADED', true);

$userRole = $_COOKIE['user_role'] ?? 'CLIENT';
$canEdit = in_array($userRole, ['SUPER_ADMIN', 'MANAGER'], true);

$pageTitle = 'Gestión de Inventario';
$pageSubtitle = 'Control de catálogo de productos, existencias en tiempo real y trazabilidad de movimientos.';
$activeModule = 'inventario';

$extraCSS = <<<'CSS'
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.metric-card { background: var(--color-surface, #fff); padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 16px; }
.metric-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
.search-filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px; }
.search-filter-bar input, .search-filter-bar select { flex: 1; min-width: 170px; }
.badge-stock { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.badge-ok { background: #DCFCE7; color: #166534; }
.badge-bajo { background: #FEF3C7; color: #92400E; }
.badge-cero { background: #FEE2E2; color: #991B1B; }
.badge-inactivo { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
CSS;

ob_start();
?>

<!-- Métricas de Inventario -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon" style="background: #E0F2FE; color: #0369A1;">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
        </div>
        <div>
            <span class="text-muted" style="font-size: 0.85rem;">Total Productos Activos</span>
            <h3 id="metric-total" style="margin: 0; font-size: 1.5rem; font-weight: 700;">0</h3>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: #FEF3C7; color: #B45309;">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <span class="text-muted" style="font-size: 0.85rem;">Stock Bajo</span>
            <h3 id="metric-bajo" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #B45309;">0</h3>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: #DCFCE7; color: #166534;">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <span class="text-muted" style="font-size: 0.85rem;">Valoración Stock (Costo)</span>
            <h3 id="metric-valor" style="margin: 0; font-size: 1.5rem; font-weight: 700;">$0</h3>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon" style="background: #F3E8FF; color: #6B21A8;">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <span class="text-muted" style="font-size: 0.85rem;">Movimientos Hoy</span>
            <h3 id="metric-movs" style="margin: 0; font-size: 1.5rem; font-weight: 700;">0</h3>
        </div>
    </div>
</div>

<!-- Barra de Acciones Principales y Filtros -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <h3 style="margin: 0; font-size: 1.15rem;">Búsqueda y Filtros</h3>
        <?php if ($canEdit): ?>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn--outline" onclick="abrirModalMovimiento()">Registrar Movimiento</button>
            <button type="button" class="btn btn--primary" onclick="abrirModalProducto()">+ Nuevo Producto</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="search-filter-bar">
        <input type="text" id="filtro-busqueda" class="form-control" placeholder="Buscar por nombre, SKU o descripción..." oninput="filtrarConDebounce()">
        <select id="filtro-categoria" class="form-control" onchange="cargarProductos()">
            <option value="">Todas las categorías</option>
        </select>
        <select id="filtro-stock" class="form-control" onchange="cargarProductos()">
            <option value="">Todos los niveles de stock</option>
            <option value="ok">Stock OK</option>
            <option value="bajo">Stock Bajo (Alerta)</option>
            <option value="cero">Agotado (0 unidades)</option>
        </select>
        <select id="filtro-estado-activo" class="form-control" onchange="cargarProductos()">
            <option value="activos">Solo Productos Activos</option>
            <option value="todos">Todos (incluye archivados)</option>
            <option value="inactivos">Solo Archivados (Borrado Lógico)</option>
        </select>
        <button type="button" class="btn btn--outline" onclick="limpiarFiltros()">Limpiar</button>
    </div>
</div>

<!-- Pestañas de Navegación -->
<div style="display: flex; gap: 12px; margin-bottom: 16px; border-bottom: 2px solid var(--color-border, #e5e7eb);">
    <button type="button" id="tab-btn-productos" class="btn" style="background: none; border: none; border-bottom: 3px solid var(--color-primary, #0284c7); padding: 8px 16px; font-weight: 600; cursor: pointer;" onclick="cambiarTab('productos')">Catálogo de Productos</button>
    <button type="button" id="tab-btn-movimientos" class="btn" style="background: none; border: none; border-bottom: 3px solid transparent; padding: 8px 16px; font-weight: 600; cursor: pointer; color: #6b7280;" onclick="cambiarTab('movimientos')">Bitácora de Movimientos</button>
</div>

<!-- TAB 1: PRODUCTOS -->
<div id="tab-productos" class="card">
    <div class="table-container">
        <table class="table" id="tablaProductos">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Marca</th>
                    <th style="text-align: right;">Costo</th>
                    <th style="text-align: right;">Precio Venta</th>
                    <th style="text-align: center;">Stock</th>
                    <th style="text-align: center;">Estado</th>
                    <?php if ($canEdit): ?><th style="text-align: center;">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-productos">
                <tr><td colspan="<?= $canEdit ? 9 : 8 ?>" class="text-center text-muted p-4">Cargando catálogo de inventario...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB 2: MOVIMIENTOS -->
<div id="tab-movimientos" class="card" style="display: none;">
    <div class="table-container">
        <table class="table" id="tablaMovimientos">
            <thead>
                <tr>
                    <th># ID</th>
                    <th>Fecha / Hora</th>
                    <th>Producto</th>
                    <th>Tipo de Movimiento</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th>Motivo / Observación</th>
                </tr>
            </thead>
            <tbody id="tbody-movimientos">
                <tr><td colspan="6" class="text-center text-muted p-4">Cargando historial de movimientos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: PRODUCTO (CREAR / EDITAR) -->
<div id="modalProducto" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; padding: 24px; background: #fff;">
        <h3 id="modalProductoTitulo" style="margin-top: 0; margin-bottom: 16px;">Nuevo Producto</h3>
        <form id="formProducto" onsubmit="guardarProducto(event)">
            <input type="hidden" id="product_id">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label">SKU / Referencia</label>
                    <input type="text" id="sku" class="form-control" placeholder="Ej: PROD-001">
                </div>
                <div>
                    <label class="form-label">Nombre del Producto *</label>
                    <input type="text" id="inv_name" class="form-control" required placeholder="Ej: Gel Exfoliante Facial">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label">Descripción</label>
                <textarea id="inv_description" class="form-control" rows="2" placeholder="Detalle técnico o indicaciones..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label">Categoría</label>
                    <select id="category_id" class="form-control"></select>
                </div>
                <div>
                    <label class="form-label">Marca</label>
                    <select id="brand_id" class="form-control"></select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label">Proveedor</label>
                    <select id="supplier_id" class="form-control"></select>
                </div>
                <div id="wrapper-is-active" style="display: none;">
                    <label class="form-label">Estado de Vigencia</label>
                    <select id="is_active" class="form-control">
                        <option value="1">Activo (Disponible)</option>
                        <option value="0">Archivado (Borrado Lógico)</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label">Precio Costo ($)</label>
                    <input type="number" step="0.01" min="0" id="cost_price" class="form-control" value="0.00" required>
                </div>
                <div>
                    <label class="form-label">Precio Venta ($)</label>
                    <input type="number" step="0.01" min="0" id="unit_price" class="form-control" value="0.00" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label class="form-label" id="label-stock-inicial">Stock Inicial</label>
                    <input type="number" id="current_stock" class="form-control" value="0" required>
                    <small id="help-stock-inicial" class="text-muted" style="display: none; font-size: 0.75rem;">Para cambiar existencias use "Registrar Movimiento".</small>
                </div>
                <div>
                    <label class="form-label">Stock Mínimo Alerta</label>
                    <input type="number" id="minimum_stock" class="form-control" value="5" required>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn--outline" onclick="cerrarModalProducto()">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: REGISTRAR MOVIMIENTO -->
<div id="modalMovimiento" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 24px; background: #fff;">
        <h3 style="margin-top: 0; margin-bottom: 16px;">Registrar Movimiento de Stock</h3>
        <form id="formMovimiento" onsubmit="guardarMovimiento(event)">
            <div style="margin-bottom: 12px;">
                <label class="form-label">Producto *</label>
                <select id="mov_product_id" class="form-control" required></select>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label">Tipo de Movimiento *</label>
                <select id="movement_type" class="form-control" required>
                    <option value="ENTRADA_COMPRA">ENTRADA / COMPRA (+)</option>
                    <option value="SALIDA_VENTA">SALIDA / VENTA (-)</option>
                    <option value="CONSUMO_INTERNO">CONSUMO INTERNO / SERVICIO (-)</option>
                    <option value="MERMA">MERMA / MERMA O DAÑO (-)</option>
                    <option value="AJUSTE">AJUSTE DE INVENTARIO (+)</option>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label">Cantidad *</label>
                <input type="number" min="1" id="quantity" class="form-control" value="1" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label class="form-label">Motivo o Justificación</label>
                <textarea id="reason" class="form-control" rows="2" placeholder="Factura proveedor, merma, calibración..."></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn--outline" onclick="cerrarModalMovimiento()">Cancelar</button>
                <button type="submit" class="btn btn--primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJS = <<<'JS'
function iniciarModuloInventario() {
    cargarCatalogosIniciales();
    cargarProductos();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(iniciarModuloInventario, 50);
    });
} else {
    setTimeout(iniciarModuloInventario, 50);
}
window.addEventListener('admin-app-ready', iniciarModuloInventario);

function getApi() {
    return window.adminApi || null;
}

function getCookie(name) {
    const matches = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
    return matches ? decodeURIComponent(matches[1]) : null;
}

/**
 * Cliente HTTP unificado y resiliente para la API de Inventario
 */
async function requestInventory(method, action, params = {}, body = null) {
    let queryParts = [`action=${encodeURIComponent(action)}`];
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') {
            queryParts.push(`${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
        }
    }
    const queryString = queryParts.join('&');
    const endpoint = `/admin/inventory?${queryString}`;

    // 1. Usar window.adminApi si está disponible
    const activeApi = getApi();
    if (activeApi && typeof activeApi.request === 'function') {
        try {
            return await activeApi.request(method, endpoint, body);
        } catch (e) {
            console.warn('Fallo en adminApi, intentando fallback fetch directo:', e);
        }
    }

    // 2. Fallback resiliente con JWT token desde localStorage o cookie
    const token = localStorage.getItem('auth_token') || getCookie('auth_token') || '';
    const headers = {
        'Content-Type': 'application/json'
    };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
        headers['X-Authorization'] = `Bearer ${token}`;
    }

    const currentPath = window.location.pathname;
    const adminIndex = currentPath.indexOf('/admin');
    const projectRoot = adminIndex !== -1 ? currentPath.substring(0, adminIndex) : '/CarolinaMoraEstetica';
    const fullUrl = `${projectRoot}/api/v1${endpoint}`;

    const config = {
        method,
        headers,
        credentials: 'same-origin'
    };
    if (body && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
        config.body = JSON.stringify(body);
    }

    const resp = await fetch(fullUrl, config);
    return await resp.json();
}

let listaGlobalProductos = [];

async function cargarCatalogosIniciales() {
    try {
        const data = await requestInventory('GET', 'obtener_datos_iniciales');
        if (data && data.status === 'success') {
            poblarSelect('category_id', data.categories);
            poblarSelect('brand_id', data.brands);
            poblarSelect('supplier_id', data.suppliers);
        }
    } catch (err) {
        console.error('Error cargando catálogos iniciales:', err);
    }
}

function poblarSelect(id, items) {
    const sel = document.getElementById(id);
    if (!sel) return;
    let html = '<option value="">-- Seleccionar --</option>';
    if (Array.isArray(items)) {
        items.forEach(i => {
            html += `<option value="${i.id}">${escapeHtml(i.name)}</option>`;
        });
    }
    sel.innerHTML = html;
}

async function cargarProductos() {
    try {
        const busqueda = document.getElementById('filtro-busqueda').value.trim();
        const categoriaId = document.getElementById('filtro-categoria').value;
        const estadoStock = document.getElementById('filtro-stock').value;
        const estadoActivo = document.getElementById('filtro-estado-activo') ? document.getElementById('filtro-estado-activo').value : 'activos';

        const params = {};
        if (busqueda) params.busqueda = busqueda;
        if (categoriaId) params.categoria_id = categoriaId;
        if (estadoStock) params.estado_stock = estadoStock;

        if (estadoActivo === 'todos') {
            params.incluir_inactivos = '1';
        } else if (estadoActivo === 'inactivos') {
            params.estado_stock = 'inactivo';
            params.incluir_inactivos = '1';
        }

        const res = await requestInventory('GET', 'obtener_productos', params);

        if (res && res.status === 'success') {
            listaGlobalProductos = res.data || [];
            renderTablaProductos(listaGlobalProductos);
            actualizarMetricas(res.metrics || {});
            actualizarSelectProductos(listaGlobalProductos);
            poblarFiltroCategorias(res.data);
        } else {
            const tbody = document.getElementById('tbody-productos');
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted p-4">No se pudo cargar la lista de productos.</td></tr>';
        }
    } catch (err) {
        console.error('Error al cargar productos:', err);
        const tbody = document.getElementById('tbody-productos');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted p-4">Error de conexión al cargar productos.</td></tr>';
    }
}

function poblarFiltroCategorias(productos) {
    const sel = document.getElementById('filtro-categoria');
    if (!sel) return;
    const valorActual = sel.value;
    const cats = {};
    if (Array.isArray(productos)) {
        productos.forEach(p => {
            if (p.category_id && p.categoria_nombre) {
                cats[p.category_id] = p.categoria_nombre;
            }
        });
    }
    let html = '<option value="">Todas las categorías</option>';
    for (const [id, name] of Object.entries(cats)) {
        html += `<option value="${id}" ${valorActual == id ? 'selected' : ''}>${escapeHtml(name)}</option>`;
    }
    sel.innerHTML = html;
}

function actualizarMetricas(m) {
    const fmt = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
    document.getElementById('metric-total').innerText = m.total_productos || 0;
    document.getElementById('metric-bajo').innerText = m.stock_bajo || 0;
    document.getElementById('metric-valor').innerText = fmt.format(parseFloat(m.valor_inventario || 0));
    document.getElementById('metric-movs').innerText = m.movimientos_hoy || 0;
}

function renderTablaProductos(productos) {
    const tbody = document.getElementById('tbody-productos');
    if (!productos || productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted p-4">No se encontraron productos con los filtros aplicados.</td></tr>';
        return;
    }

    const fmt = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
    let html = '';

    productos.forEach(p => {
        const stock = parseInt(p.current_stock, 10) || 0;
        const min = parseInt(p.minimum_stock, 10) || 0;
        const isActive = p.is_active !== undefined ? (parseInt(p.is_active, 10) === 1) : true;

        let badgeClass = 'badge-ok';
        let badgeText = 'Stock OK';

        if (!isActive) {
            badgeClass = 'badge-inactivo';
            badgeText = 'Archivado';
        } else if (stock === 0) {
            badgeClass = 'badge-cero';
            badgeText = 'Agotado';
        } else if (stock <= min) {
            badgeClass = 'badge-bajo';
            badgeText = 'Stock Bajo';
        }

        const safeSku = escapeHtml(p.sku || '');
        const safeName = escapeHtml(p.name || '');
        const safeDesc = escapeHtml(p.description || '');
        const safeCat = escapeHtml(p.categoria_nombre || 'General');
        const safeBrand = escapeHtml(p.marca_nombre || '-');
        const costVal = fmt.format(parseFloat(p.cost_price || 0));
        const priceVal = fmt.format(parseFloat(p.unit_price || 0));

        const rowStyle = !isActive ? 'style="opacity: 0.65; background-color: #f9fafb;"' : '';
        const pJson = JSON.stringify(p).replace(/'/g, '&#39;');

        html += `
            <tr ${rowStyle}>
                <td>${safeSku ? `<code>${safeSku}</code>` : '<span class="text-muted">S/N</span>'}</td>
                <td>
                    <strong>${safeName}</strong>
                    ${safeDesc ? `<br><small class="text-muted">${safeDesc}</small>` : ''}
                </td>
                <td>${safeCat}</td>
                <td>${safeBrand}</td>
                <td style="text-align: right;">${costVal}</td>
                <td style="text-align: right;" class="fw-bold text-success">${priceVal}</td>
                <td style="text-align: center;"><strong>${stock}</strong></td>
                <td style="text-align: center;"><span class="badge-stock ${badgeClass}">${badgeText}</span></td>
                <?php if ($canEdit): ?>
                <td style="text-align: center; white-space: nowrap;">
                    <button type="button" class="btn btn--outline" style="padding: 4px 8px; font-size: 0.8rem;" onclick='editarProducto(${pJson})'>Editar</button>
                    <button type="button" class="btn" style="padding: 4px 8px; font-size: 0.8rem; background: #fee2e2; color: #991b1b; border: none; margin-left: 4px;" onclick='eliminarProducto(${p.id}, "${escapeJs(p.name)}")' title="Eliminar definitivamente o archivar con borrado lógico">Eliminar</button>
                </td>
                <?php else: ?>
                <td style="text-align: center;">-</td>
                <?php endif; ?>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function actualizarSelectProductos(productos) {
    const sel = document.getElementById('mov_product_id');
    if (!sel) return;
    let html = '<option value="">-- Seleccionar producto --</option>';
    if (Array.isArray(productos)) {
        productos.forEach(p => {
            const isActive = p.is_active !== undefined ? (parseInt(p.is_active, 10) === 1) : true;
            if (isActive) {
                html += `<option value="${p.id}">${escapeHtml(p.name)} (Stock actual: ${p.current_stock})</option>`;
            }
        });
    }
    sel.innerHTML = html;
}

let debounceTimer;
function filtrarConDebounce() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        cargarProductos();
    }, 350);
}

function limpiarFiltros() {
    document.getElementById('filtro-busqueda').value = '';
    document.getElementById('filtro-categoria').value = '';
    document.getElementById('filtro-stock').value = '';
    if (document.getElementById('filtro-estado-activo')) {
        document.getElementById('filtro-estado-activo').value = 'activos';
    }
    cargarProductos();
}

function cambiarTab(tab) {
    const btnProd = document.getElementById('tab-btn-productos');
    const btnMov = document.getElementById('tab-btn-movimientos');
    const paneProd = document.getElementById('tab-productos');
    const paneMov = document.getElementById('tab-movimientos');

    if (tab === 'productos') {
        btnProd.style.borderBottomColor = 'var(--color-primary, #0284c7)';
        btnProd.style.color = 'inherit';
        btnMov.style.borderBottomColor = 'transparent';
        btnMov.style.color = '#6b7280';
        paneProd.style.display = 'block';
        paneMov.style.display = 'none';
        cargarProductos();
    } else {
        btnMov.style.borderBottomColor = 'var(--color-primary, #0284c7)';
        btnMov.style.color = 'inherit';
        btnProd.style.borderBottomColor = 'transparent';
        btnProd.style.color = '#6b7280';
        paneMov.style.display = 'block';
        paneProd.style.display = 'none';
        cargarHistorialMovimientos();
    }
}

async function cargarHistorialMovimientos() {
    try {
        const res = await requestInventory('GET', 'obtener_movimientos');
        if (res && res.status === 'success') {
            const tbody = document.getElementById('tbody-movimientos');
            const movs = res.data || [];
            if (movs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-4">No hay movimientos registrados en la bitácora.</td></tr>';
                return;
            }
            let html = '';
            movs.forEach(m => {
                const safeProd = escapeHtml(m.producto_nombre || 'Producto eliminado');
                const safeType = escapeHtml(m.movement_type || '-');
                const safeReason = escapeHtml(m.reason || '-');
                const isEntrada = ['ENTRADA_COMPRA', 'AJUSTE'].includes(m.movement_type);
                const colorBadge = isEntrada ? '#166534' : '#991b1b';
                const sign = isEntrada ? '+' : '-';

                html += `
                    <tr>
                        <td>#${m.id}</td>
                        <td>${escapeHtml(m.movement_date || m.created_at || '')}</td>
                        <td><strong>${safeProd}</strong></td>
                        <td><span style="font-weight: 600; color: #0369a1;">${safeType}</span></td>
                        <td style="text-align: center;"><strong style="color: ${colorBadge};">${sign}${m.quantity}</strong></td>
                        <td>${safeReason}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    } catch (err) {
        console.error('Error cargando movimientos:', err);
    }
}

function abrirModalProducto() {
    document.getElementById('formProducto').reset();
    document.getElementById('product_id').value = '';
    document.getElementById('current_stock').disabled = false;
    document.getElementById('help-stock-inicial').style.display = 'none';
    document.getElementById('wrapper-is-active').style.display = 'none';
    document.getElementById('modalProductoTitulo').innerText = 'Nuevo Producto';
    document.getElementById('modalProducto').style.display = 'flex';
}

function cerrarModalProducto() {
    document.getElementById('modalProducto').style.display = 'none';
}

function editarProducto(p) {
    document.getElementById('product_id').value = p.id;
    document.getElementById('sku').value = p.sku || '';
    document.getElementById('inv_name').value = p.name || '';
    document.getElementById('inv_description').value = p.description || '';
    document.getElementById('category_id').value = p.category_id || '';
    document.getElementById('brand_id').value = p.brand_id || '';
    document.getElementById('supplier_id').value = p.supplier_id || '';
    document.getElementById('cost_price').value = p.cost_price || '0.00';
    document.getElementById('unit_price').value = p.unit_price || '0.00';
    document.getElementById('current_stock').value = p.current_stock || '0';
    document.getElementById('minimum_stock').value = p.minimum_stock || '5';

    // Al editar, el stock solo se modifica mediante la bitácora de movimientos
    document.getElementById('current_stock').disabled = true;
    document.getElementById('help-stock-inicial').style.display = 'block';

    // Mostrar estado de vigencia (Activo / Archivado)
    const wrapperActive = document.getElementById('wrapper-is-active');
    wrapperActive.style.display = 'block';
    document.getElementById('is_active').value = (p.is_active !== undefined) ? p.is_active : 1;

    document.getElementById('modalProductoTitulo').innerText = 'Editar Producto';
    document.getElementById('modalProducto').style.display = 'flex';
}

async function guardarProducto(e) {
    e.preventDefault();
    const productId = document.getElementById('product_id').value;
    const data = {
        product_id: productId,
        sku: document.getElementById('sku').value.trim(),
        name: document.getElementById('inv_name').value.trim(),
        description: document.getElementById('inv_description').value.trim(),
        category_id: document.getElementById('category_id').value,
        brand_id: document.getElementById('brand_id').value,
        supplier_id: document.getElementById('supplier_id').value,
        cost_price: document.getElementById('cost_price').value,
        unit_price: document.getElementById('unit_price').value,
        current_stock: document.getElementById('current_stock').value,
        minimum_stock: document.getElementById('minimum_stock').value,
        is_active: document.getElementById('is_active') ? document.getElementById('is_active').value : 1
    };

    if (!data.name) {
        mostrarNotificacion('El nombre del producto es obligatorio.', 'error');
        return;
    }

    try {
        const res = await requestInventory('POST', 'guardar_producto', {}, data);
        if (res && res.status === 'success') {
            cerrarModalProducto();
            document.getElementById('current_stock').disabled = false;
            cargarProductos();
            mostrarNotificacion(res.message || 'Producto guardado correctamente.', 'success');
        } else {
            const err = res?.message || res?.detail || 'Error al guardar el producto.';
            mostrarNotificacion(err, 'error');
        }
    } catch (err) {
        console.error('Error guardando producto:', err);
        mostrarNotificacion('Error de conexión al guardar el producto.', 'error');
    }
}

/**
 * Elimina un producto protegiendo la integridad referencial:
 * Si tiene movimientos asociados se realiza borrado lógico (is_active = 0).
 * Si no tiene movimientos se borra físicamente.
 */
async function eliminarProducto(id, nombre) {
    const msg = nombre
        ? `¿Confirmas la eliminación del producto "${nombre}"?\n\nNota de trazabilidad:\nSi el producto tiene movimientos registrados o historial contable, el sistema realizará automáticamente un borrado lógico (archivado) para proteger la integridad referencial.`
        : '¿Estás seguro de eliminar este producto?';

    if (!confirm(msg)) return;

    try {
        const res = await requestInventory('DELETE', 'eliminar_producto', { id });
        if (res && res.status === 'success') {
            cargarProductos();
            mostrarNotificacion(res.message, 'success');
        } else {
            const err = res?.message || res?.detail || 'Error al eliminar el producto.';
            mostrarNotificacion(err, 'error');
        }
    } catch (err) {
        console.error('Error eliminando producto:', err);
        mostrarNotificacion('Error de conexión al procesar la eliminación.', 'error');
    }
}

function abrirModalMovimiento() {
    document.getElementById('formMovimiento').reset();
    document.getElementById('modalMovimiento').style.display = 'flex';
}

function cerrarModalMovimiento() {
    document.getElementById('modalMovimiento').style.display = 'none';
}

async function guardarMovimiento(e) {
    e.preventDefault();
    const data = {
        product_id: document.getElementById('mov_product_id').value,
        movement_type: document.getElementById('movement_type').value,
        quantity: document.getElementById('quantity').value,
        reason: document.getElementById('reason').value.trim()
    };

    if (!data.product_id) {
        mostrarNotificacion('Por favor seleccione un producto.', 'error');
        return;
    }

    try {
        const res = await requestInventory('POST', 'registrar_movimiento', {}, data);
        if (res && res.status === 'success') {
            cerrarModalMovimiento();
            cargarProductos();
            mostrarNotificacion(res.message || 'Movimiento registrado con éxito.', 'success');
        } else {
            const err = res?.message || res?.detail || 'Error al registrar el movimiento.';
            mostrarNotificacion(err, 'error');
        }
    } catch (err) {
        console.error('Error registrando movimiento:', err);
        mostrarNotificacion('Error de conexión al registrar el movimiento.', 'error');
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    if (window.adminAlerts) {
        if (tipo === 'success') window.adminAlerts.success(mensaje);
        else if (tipo === 'error') window.adminAlerts.error(mensaje);
        else window.adminAlerts.info(mensaje);
    } else {
        alert(mensaje);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/"/g, '\\"').replace(/'/g, "\\'");
}
JS;

require __DIR__ . '/../layouts/admin-layout.php';
