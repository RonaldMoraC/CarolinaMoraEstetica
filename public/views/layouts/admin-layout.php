<?php declare(strict_types=1);
/**
 * Layout Base del Panel Administrativo
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Uso: Incluir este archivo al inicio de cada vista admin.
 * Proporciona: sidebar, header, CSS global, JS modular.
 *
 * Variables esperadas:
 *   $pageTitle      - Título de la página (string)
 *   $pageSubtitle   - Subtítulo descriptivo (string, opcional)
 *   $activeModule   - Módulo activo para highlight en sidebar (string)
 *   $pageContent    - Contenido HTML de la vista (string)
 *   $extraCSS       - CSS adicional específico de la vista (string, opcional)
 *   $extraJS        - JS adicional específico de la vista (string, opcional)
 */

// Protección: solo accesible desde views, no directamente
if (!defined('ADMIN_LAYOUT_LOADED')) {
    // Verificar autenticación básica
    $token = $_COOKIE['auth_token'] ?? '';
    $userRole = $_COOKIE['user_role'] ?? '';

    $staffRoles = ['SUPER_ADMIN', 'BRANCH_ADMIN', 'RECEPCIONIST', '1'];

    if (empty($token) || !in_array($userRole, $staffRoles, true)) {
        // Redirigir al login si no hay sesión válida
        header('Location: /login');
        exit;
    }
}

// Mapeo de módulos
$modules = [
    'dashboard'    => ['label' => 'Dashboard',    'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'url' => '/CarolinaMoraEstetica/admin/dashboard'],
    'recepcion'    => ['label' => 'Recepción',    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'url' => '/CarolinaMoraEstetica/admin/recepcion'],
    'calendario'   => ['label' => 'Calendario',   'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'url' => '/CarolinaMoraEstetica/admin/calendario'],
    'servicios'    => ['label' => 'Servicios',    'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.675.338a2 2 0 00-.894 2.605l.338.675a2 2 0 002.605.894l.675-.338a6 6 0 003.86-.517l2.387-.477a2 2 0 001.022-.547l.338-.675a2 2 0 00-.894-2.605l-.338-.675z', 'url' => '/CarolinaMoraEstetica/admin/servicios'],
    'horarios'     => ['label' => 'Horarios',     'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'url' => '/CarolinaMoraEstetica/admin/horarios'],
    'promociones'  => ['label' => 'Promociones',  'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', 'url' => '/CarolinaMoraEstetica/admin/promociones'],
    'usuarios'     => ['label' => 'Usuarios',     'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'url' => '/CarolinaMoraEstetica/admin/usuarios'],
    'resenas'      => ['label' => 'Reseñas',      'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'url' => '/CarolinaMoraEstetica/admin/resenas'],
    'analiticas'   => ['label' => 'Analíticas',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'url' => '/CarolinaMoraEstetica/admin/analiticas'],
];

$activeModule = $activeModule ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Panel Administrativo';
$pageSubtitle = $pageSubtitle ?? '';
$extraCSS = $extraCSS ?? '';
$extraJS = $extraJS ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Carolina Mora Estética</title>

    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Global del Panel Admin -->
    <link rel="stylesheet" href="/CarolinaMoraEstetica/assets/css/admin-global.css">


    <!-- CSS adicional de la vista -->
    <?php if (!empty($extraCSS)): ?>
    <style>
        <?= $extraCSS ?>
    </style>
    <?php endif; ?>
</head>
<body>

<div class="admin-layout">
    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar__brand">
            <h2>Backoffice</h2>
            <small>Carolina Mora Estética</small>
        </div>

        <nav class="admin-sidebar__nav">
            <ul class="admin-sidebar__menu">
                <?php foreach ($modules as $key => $mod): ?>
                    <li>
                        <a href="<?= $mod['url'] ?>"
                           class="admin-sidebar__link <?= ($activeModule === $key) ? 'admin-sidebar__link--active' : '' ?>">
                            <svg class="w-4 h-4" style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $mod['icon'] ?>"></path>
                            </svg>
                            <?= htmlspecialchars($mod['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a href="/CarolinaMoraEstetica/login" class="admin-sidebar__link admin-sidebar__link--logout" onclick="localStorage.removeItem('auth_token');localStorage.removeItem('user_role');">
                        <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Cerrar Sesión
                    </a>
                </li>
            </ul>
        </nav>

    </aside>

    <!-- ============================================================
         CONTENIDO PRINCIPAL
         ============================================================ -->
    <main class="admin-main" id="adminMain">
        <!-- Header de página -->
        <header class="page-header">
            <div>
                <h1 class="page-header__title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="page-header__subtitle"><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div class="page-header__actions" id="pageHeaderActions">
                <!-- Espacio para botones específicos de cada vista -->
            </div>
        </header>

        <!-- Alertas dinámicas -->
        <div class="alert" id="globalAlert" role="alert" aria-live="polite"></div>

        <!-- Contenido de la vista -->
        <div class="page-content" id="pageContent">
            <?= $pageContent ?? '' ?>
        </div>
    </main>
</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<!-- JS Modular del Panel Admin (ApiClient, Store, Alerts, Modal) -->
<script src="/CarolinaMoraEstetica/assets/js/admin-app.js"></script>

<!-- JS adicional de la vista -->
<?php if (!empty($extraJS)): ?>
<script>
    <?= $extraJS ?>
</script>
<?php endif; ?>

</body>
</html>
