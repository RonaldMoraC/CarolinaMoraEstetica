<?php declare(strict_types=1);
/**
 * Gestión de Inventario - Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 */

define('ADMIN_LAYOUT_LOADED', true);

$userRole = $_COOKIE['user_role'] ?? 'CLIENT';
$canEdit = in_array($userRole, ['SUPER_ADMIN', 'MANAGER'], true);

$pageTitle = 'Gestión de Inventario';
$pageSubtitle = 'Control de productos, stock y movimientos del sistema de estética.';
$activeModule = 'inventario';

$extraCSS = <<<'CSS'
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.metric-card { background: var(--color-surface, #fff); padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 16px; }
.metric-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
.search-filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px; }
.search-filter-bar input, .search-filter-bar select { flex: 1; min-width: 180px; }
.badge-stock { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.badge-ok { background: #DCFCE7; color: #166534; }
.badge-bajo { background: #FEF3C7; color: #92400E; }
.badge-cero { background: #FEE2E2; color: #991B1B; }
CSS;

ob_start();
?>

<!-- Métricas -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon" style="background: #E0F2FE; color: #0369A1;">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
        </div>
        <div>
            <span class="text-muted" style="font-size: 0.85rem;">Total Productos</span>
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
            <span class="text-muted" style="font-size: 0.85rem;">Valor Inventario</span>
            <h3 id="metric-valor" style="margin: 0; font-size: 1.5rem; font-weight: 700;">$0.00</h3>
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

<!-- Acciones Principales y Filtros -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <h3 style="margin: 0; font-size: 1.15rem;">Filtros y Búsqueda</h3>
        <?php if ($canEdit): ?>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn--outline" onclick="abrirModalMovimiento()">Registrar Movimiento</button>
            <button type="button" class="btn btn--primary" onclick="abrirModalProducto()">+ Nuevo Producto</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="search-filter-bar">
        <input type="text" id="filtro-busqueda" class="form-control" placeholder="Buscar por nombre, SKU..." oninput="filtrarConDebounce()">
        <select id="filtro-categoria" class="form-control" onchange="cargarProductos()">
            <option value="">Todas las categorías</option>
        </select>
        <select id="filtro-stock" class="form-control" onchange="cargarProductos()">
            <option value="">Todos los estados de stock</option>
            <option value="ok">Stock OK</option>
            <option value="bajo">Stock Bajo</option>
            <option value="cero">Agotado (Cero)</option>
        </select>
        <button type="button" class="btn btn--outline" onclick="limpiarFiltros()">Limpiar</button>
    </div>
</div>

<!-- Pestañas -->
<div style="display: flex; gap: 12px; margin-bottom: 16px; border-bottom: 2px solid var(--color-border, #e5e7eb);">
    <button type="button" id="tab-btn-productos" class="btn" style="background: none; border: none; border-bottom: 3px solid var(--color-primary, #0284c7); padding: 8px 16px; font-weight: 600; cursor: pointer;" onclick="cambiarTab('productos')">Lista de Productos</button>
    <button type="button" id="tab-btn-movimientos" class="btn" style="background: none; border: none; border-bottom: 3px solid transparent; padding: 8px 16px; font-weight: 600; cursor: pointer; color: #6b7280;" onclick="cambiarTab('movimientos')">Historial de Movimientos</button>
</div>

<!-- CONTENEDOR TAB 1: PRODUCTOS -->
<div id="tab-productos" class="card">
    <div class="table-container">
        <table class="table" id="tablaProductos">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Costo</th>
                    <th>Precio Venta</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <?php if ($canEdit): ?><th style="text-align: center;">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-productos">
                <tr><td colspan="<?= $canEdit ? 8 : 7 ?>" class="text-center text-muted p-4">Cargando productos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- CONTENEDOR TAB 2: MOVIMIENTOS -->
<div id="tab-movimientos" class="card" style="display: none;">
    <div class="table-container">
        <table class="table" id="tablaMovimientos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha / Hora</th>
                    <th>Producto</th>
                    <th>Tipo de Movimiento</th>
                    <th>Cantidad</th>
                    <th>Razón / Notas</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody id="tbody-movimientos">
                <tr><td colspan="7" class="text-center text-muted p-4">Cargando historial...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: PRODUCTO -->
<div id="modalProducto" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 24px; background: #fff;">
        <h3 id="modalProductoTitulo" style="margin-top: 0; margin-bottom: 16px;">Nuevo Producto</h3>
        <form id="formProducto" onsubmit="guardarProducto(event)">
            <input type="hidden" id="product_id">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="form-label">SKU</label>
                    <input type="text" id="sku" class="form-control" placeholder="Automático">
                </div>
                <div>
                    <label class="form-label">Nombre del Producto *</label>
                    <input type="text" id="inv_name" class="form-control" required placeholder="Ej. Crema Hidratante">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label">Descripción</label>
                <textarea id="inv_description" class="form-control" rows="2" placeholder="Detalles..."></textarea>
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
                    <label class="form-label">Precio Costo ($)</label>
                    <input type="number" step="0.01" id="cost_price" class="form-control" value="0.00" required>
                </div>
                <div>
                    <label class="form-label">Precio Venta ($)</label>
                    <input type="number" step="0.01" id="unit_price" class="form-control" value="0.00" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div>
                    <label class="form-label">Stock Inicial</label>
                    <input type="number" id="current_stock" class="form-control" value="0" required>
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

<!-- MODAL: MOVIMIENTO -->
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
                    <option value="CONSUMO_INTERNO">CONSUMO INTERNO (-)</option>
                    <option value="MERMA">MERMA / DAÑO (-)</option>
                    <option value="AJUSTE">AJUSTE DE INVENTARIO (+)</option>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="form-label">Cantidad *</label>
                <input type="number" min="1" id="quantity" class="form-control" value="1" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label class="form-label">Razón / Notas</label>
                <textarea id="reason" class="form-control" rows="2" placeholder="Motivo del movimiento..."></textarea>
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
document.addEventListener('DOMContentLoaded', () => {
    cargarCatalogosIniciales();
    cargarProductos();
});

const API_URL = '/CarolinaMoraEstetica/api/v1/admin/inventory';

let listaGlobalProductos = [];

async function cargarCatalogosIniciales() {
    try {
        const resp = await fetch(`${API_URL}?action=obtener_datos_iniciales`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.status === 'success') {
            poblarSelect('category_id', data.categories);
            poblarSelect('brand_id', data.brands);
            poblarSelectSelectMovimiento(data.categories); // fallback
        }
    } catch (err) {
        console.error('Error cargando catálogos:', err);
    }
}

function poblarSelect(id, items) {
    const sel = document.getElementById(id);
    if (!sel) return;
    let html = '<option value="">-- Seleccionar --</option>';
    if (Array.isArray(items)) {
        items.forEach(i => {
            html += `<option value="${i.id}">${i.name}</option>`;
        });
    }
    sel.innerHTML = html;
}

function poblarSelectSelectMovimiento(items) {
    // Se llena con la lista de productos al cargar productos
}

async function cargarProductos() {
    try {
        const busqueda = document.getElementById('filtro-busqueda').value;
        const categoriaId = document.getElementById('filtro-categoria').value;
        const estadoStock = document.getElementById('filtro-stock').value;

        let url = `${API_URL}?action=obtener_productos`;
        if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
        if (categoriaId) url += `&categoria_id=${categoriaId}`;
        if (estadoStock) url += `&estado_stock=${estadoStock}`;

        const resp = await fetch(url);
        const res = await resp.json();

        if (res.status === 'success') {
            listaGlobalProductos = res.data || [];
            renderTablaProductos(listaGlobalProductos);
            actualizarMetricas(res.metrics || {});
            actualizarSelectProductos(listaGlobalProductos);
            poblarFiltroCategorias(res.data);
        }
    } catch (err) {
        console.error('Error al cargar productos:', err);
    }
}

function poblarFiltroCategorias(productos) {
    const sel = document.getElementById('filtro-categoria');
    const valorActual = sel.value;
    const cats = {};
    productos.forEach(p => {
        if (p.category_id && p.categoria_nombre) {
            cats[p.category_id] = p.categoria_nombre;
        }
    });
    let html = '<option value="">Todas las categorías</option>';
    for (const [id, name] of Object.entries(cats)) {
        html += `<option value="${id}" ${valorActual == id ? 'selected' : ''}>${name}</option>`;
    }
    sel.innerHTML = html;
}

function actualizarMetricas(m) {
    document.getElementById('metric-total').innerText = m.total_productos || 0;
    document.getElementById('metric-bajo').innerText = m.stock_bajo || 0;
    document.getElementById('metric-valor').innerText = '$' + (parseFloat(m.valor_inventario || 0)).toFixed(2);
    document.getElementById('metric-movs').innerText = m.movimientos_hoy || 0;
}

function renderTablaProductos(productos) {
    const tbody = document.getElementById('tbody-productos');
    if (productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-4">No se encontraron productos.</td></tr>';
        return;
    }

    let html = '';
    productos.forEach(p => {
        const stock = parseInt(p.current_stock);
        const min = parseInt(p.minimum_stock);
        let badgeClass = 'badge-ok';
        let badgeText = 'Stock OK';
        if (stock === 0) {
            badgeClass = 'badge-cero';
            badgeText = 'Agotado';
        } else if (stock <= min) {
            badgeClass = 'badge-bajo';
            badgeText = 'Stock Bajo';
        }

        html += `
            <tr>
                <td>${p.sku || '<span class="text-muted">S/N</span>'}</td>
                <td><strong>${p.name}</strong><br><small class="text-muted">${p.description || ''}</small></td>
                <td>${p.categoria_nombre || 'General'}</td>
                <td>$${parseFloat(p.cost_price).toFixed(2)}</td>
                <td>$${parseFloat(p.unit_price).toFixed(2)}</td>
                <td><strong>${stock}</strong></td>
                <td><span class="badge-stock ${badgeClass}">${badgeText}</span></td>
                <?php if ($canEdit): ?>
                <td style="text-align: center;">
                    <button class="btn btn--outline" style="padding: 4px 8px; font-size: 0.8rem;" onclick='editarProducto(${JSON.stringify(p)})'>Editar</button>
                    <button class="btn" style="padding: 4px 8px; font-size: 0.8rem; background: #fee2e2; color: #991b1b; border: none;" onclick='eliminarProducto(${p.id})'>Eliminar</button>
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
    productos.forEach(p => {
        html += `<option value="${p.id}">${p.name} (Stock: ${p.current_stock})</option>`;
    });
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
        const resp = await fetch(`${API_URL}?action=obtener_movimientos`);
        const res = await resp.json();
        if (res.status === 'success') {
            const tbody = document.getElementById('tbody-movimientos');
            const movs = res.data || [];
            if (movs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted p-4">No hay movimientos registrados.</td></tr>';
                return;
            }
            let html = '';
            movs.forEach(m => {
                html += `
                    <tr>
                        <td>#${m.id}</td>
                        <td>${m.created_at}</td>
                        <td><strong>${m.producto_nombre || 'Producto eliminado'}</strong></td>
                        <td><span style="font-weight: 600; color: #0369a1;">${m.movement_type}</span></td>
                        <td><strong>${m.quantity}</strong></td>
                        <td>${m.reason || '-'}</td>
                        <td>${m.usuario_nombre || 'Sistema'}</td>
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
    document.getElementById('cost_price').value = p.cost_price || '0.00';
    document.getElementById('unit_price').value = p.unit_price || '0.00';
    document.getElementById('current_stock').value = p.current_stock || '0';
    document.getElementById('minimum_stock').value = p.minimum_stock || '5';

    // Ocultar stock inicial al editar ya que se maneja por movimientos
    document.getElementById('current_stock').disabled = true;

    document.getElementById('modalProductoTitulo').innerText = 'Editar Producto';
    document.getElementById('modalProducto').style.display = 'flex';
}

async function guardarProducto(e) {
    e.preventDefault();
    const data = {
        product_id: document.getElementById('product_id').value,
        sku: document.getElementById('sku').value,
        name: document.getElementById('inv_name').value,
        description: document.getElementById('inv_description').value,
        category_id: document.getElementById('category_id').value,
        brand_id: document.getElementById('brand_id').value,
        cost_price: document.getElementById('cost_price').value,
        unit_price: document.getElementById('unit_price').value,
        current_stock: document.getElementById('current_stock').value,
        minimum_stock: document.getElementById('minimum_stock').value
    };

    try {
        const resp = await fetch(`${API_URL}?action=guardar_producto`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const res = await resp.json();
        if (res.status === 'success') {
            cerrarModalProducto();
            document.getElementById('current_stock').disabled = false;
            cargarProductos();
            alert(res.message);
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        console.error('Error guardando producto:', err);
    }
}

async function eliminarProducto(id) {
    if (!confirm('¿Estás seguro de eliminar este producto?')) return;
    try {
        const resp = await fetch(`${API_URL}?action=eliminar_producto&id=${id}`, {
            method: 'DELETE'
        });
        const res = await resp.json();
        if (res.status === 'success') {
            cargarProductos();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        console.error('Error eliminando producto:', err);
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
        reason: document.getElementById('reason').value
    };

    try {
        const resp = await fetch(`${API_URL}?action=registrar_movimiento`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const res = await resp.json();
        if (res.status === 'success') {
            cerrarModalMovimiento();
            cargarProductos();
            alert(res.message);
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        console.error('Error registrando movimiento:', err);
    }
}
JS;

require __DIR__ . '/../layouts/admin-layout.php';
