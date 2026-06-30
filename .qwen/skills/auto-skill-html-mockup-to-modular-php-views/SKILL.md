---
name: html-mockup-to-modular-php-views
description: Migrar mockups HTML estáticos a vistas PHP modulares con comunicación API REST, layout compartido y separación estricta de CSS/JS/PHP
source: auto-skill
extracted_at: '2026-06-12T17:03:46.932Z'
---

# Migración de Mockups HTML a Vistas PHP Modulares con API REST

## Cuándo aplicar

- Se tienen mockups/prototipos HTML estáticos que necesitan convertirse en interfaces funcionales
- El backend expone una API REST y el frontend debe consumirla
- Se necesita separación estricta de responsabilidades: CSS, JS y PHP/HTML en archivos distintos
- Múltiples vistas comparten estructura (sidebar, header, navegación)

## Procedimiento paso a paso

### 1. Extraer un sistema de diseño CSS unificado

Leer TODOS los mockups HTML existentes y extraer:
- Variables CSS repetidas (colores, espaciados, tipografía, radios) → definir como `:root` tokens
- Componentes UI comunes (botones, badges, tablas, formularios, cards) → clases genéricas `.btn--primary`, `.badge--success`, etc.
- Layouts repetidos (grid de sidebar + contenido) → `.admin-layout`, `.layout-split`
- Responsive breakpoints comunes

Crear un único archivo `admin-global.css` que reemplace los `<style>` inline duplicados en cada mockup.

### 2. Crear un cliente API modular en JavaScript

Construir un módulo `admin-app.js` con estas clases singleton:

```javascript
// ApiClient: fetch + JWT automático + manejo de errores HTTP
class ApiClient {
    async get(endpoint) { /* ... */ }
    async post(endpoint, body) { /* ... */ }
    async put(endpoint, body) { /* ... */ }
    async patch(endpoint, body) { /* ... */ }
    async delete(endpoint) { /* ... */ }
}

// Store reactivo con Proxy
class AdminStore { /* estado global observable */ }

// Gestores de UI reutilizables
class AlertManager { show(msg, type, duration) }
class ModalManager { open({title, body, buttons}) }
class DynamicTable { render(data, renderRowFn) }
```

Exponer en `window.adminApi`, `window.adminAlerts`, etc. para que cada vista los use.

### 3. Crear un layout PHP compartido

Un archivo `layouts/admin-layout.php` que:
- Verifique autenticación (JWT/cookies)
- Renderice sidebar con navegación dinámica (highlight del módulo activo)
- Renderice header con título y subtítulo variables
- Incluya el CSS global y el JS modular
- Acepte variables: `$pageTitle`, `$pageSubtitle`, `$activeModule`, `$pageContent`, `$extraCSS`, `$extraJS`

### 4. Patrón de vista modular con ob_start/ob_get_clean

Cada vista PHP sigue este esqueleto:

```php
<?php declare(strict_types=1);
define('ADMIN_LAYOUT_LOADED', true);

$pageTitle = 'Mi Vista';
$pageSubtitle = 'Descripción';
$activeModule = 'mi_modulo';

$extraCSS = <<<CSS
/* CSS específico de esta vista */
CSS;

ob_start();
?>
<!-- HTML de la vista -->
<section class="card">...</section>
<?php
$pageContent = ob_get_clean();

$extraJS = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const api = window.adminApi;
    const alerts = window.adminAlerts;
    // Lógica de la vista: cargar datos, event listeners, renderizar
});
JS;

require_once __DIR__ . '/../layouts/admin-layout.php';
```

### 5. Patrón de datos con fallback simulado

Cada vista intenta cargar desde la API, y si falla usa datos de demo:

```javascript
async function cargarDatos() {
    try {
        if (api) {
            const res = await api.get('/endpoint');
            if (res.success && res.data) {
                renderDatos(res.data);
                return;
            }
        }
    } catch (error) {
        console.warn('API no disponible:', error.message);
    }
    // Fallback con datos simulados para desarrollo
    datosDemo = [ /* ... */ ];
    renderDatos(datosDemo);
}
```

### 6. Prevención XSS obligatoria

- **PHP → HTML**: Siempre usar `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` al renderizar datos del servidor
- **JS → DOM**: Siempre usar una función `escapeHtml()` que crea un `div.textContent` y retorna `div.innerHTML`
- **Nunca** usar `innerHTML` con datos sin sanitizar

### 7. Manejo de estados en tablas dinámicas

Para tablas CRUD o de estados transicionales:
- Definir un `STATUS_MAP` que mapee estados del backend a clases CSS y labels visuales
- Usar `id` en cada fila (`id="row-{entity_id}"`) para poder actualizar/eliminar individualmente
- Animar eliminación con opacity + transition antes de `.remove()`

## Estructura de archivos resultante

```
public/
├── assets/
│   ├── css/admin-global.css          ← Sistema de diseño unificado
│   └── js/admin-app.js              ← ApiClient + Store + UI components
├── views/
│   ├── layouts/admin-layout.php      ← Layout compartido (sidebar + header + auth)
│   └── admin/
│       ├── admin-dashboard.php       ← Cada vista usa ob_start + layout
│       ├── admin-modulo1.php
│       └── admin-modulo2.php
```

## Lecciones aprendidas

- **No duplicar el sidebar**: Extraerlo al layout PHP evita inconsistencias cuando se agregan nuevos módulos
- **CSS inline en mockups → siempre extraer**: Los mockups suelen tener estilos duplicados; consolidarlos en un CSS global reduce ~70% de CSS
- **Fallback simulado es crítico para desarrollo**: Permite trabajar en frontend sin que el backend esté completo
- **Usar `declare(strict_types=1)` en cada archivo PHP**: Obliga a tipar correctamente y previene bugs silenciosos
- **Separar $extraCSS y $extraJS del layout**: Permite que cada vista tenga estilos y lógica propios sin contaminar el layout compartido
