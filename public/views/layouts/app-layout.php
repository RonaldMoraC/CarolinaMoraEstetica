<?php declare(strict_types=1);
/**
 * Layout Base del Panel Cliente (PWA)
 * Estética Carolina Mora - Antigravity v2.0
 *
 * Uso: Incluir este archivo al inicio de cada vista /app/*.
 * Proporciona: header sticky, bottom nav, CSS global, JS modular.
 *
 * Variables esperadas:
 *   $pageTitle      - Título de la sección (string)
 *   $activeSection  - Sección activa en bottom nav (string: 'Catalog'|'Citas'|'Perfil')
 *   $pageContent    - Contenido HTML de la vista (string)
 *   $extraCSS       - CSS adicional específico de la vista (string, opcional)
 *   $extraJS        - JS adicional específico de la vista (string, opcional)
 */

$pageTitle = $pageTitle ?? 'Servicios';
$activeSection = $activeSection ?? 'Catalog';
$extraCSS = $extraCSS ?? '';
$extraJS = $extraJS ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#00d0ff">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Carolina Mora Estética</title>

    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#D4AF37',
                            goldDark: '#B8860B',
                            pureBlack: '#050505',
                            cyan: '#00d0ff',
                            cyanHover: '#00bce6',
                            cyanLight: 'rgba(0,208,255,0.1)',
                        }
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'serif'],
                        sans: ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Auth Guard & basePath -->
    <script>
        const basePath = (function() {
            const segments = window.location.pathname.split('/').filter(Boolean);
            if (segments.length >= 1 && segments.includes('CarolinaMoraEstetica')) {
                return '/' + segments[0];
            }
            return '';
        })();

        function isJwtExpired(token) {
            try {
                const parts = token.split('.');
                if (parts.length !== 3) return true;
                const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));
                if (payload && payload.exp) {
                    return payload.exp < Math.floor(Date.now() / 1000);
                }
                return false;
            } catch (e) {
                return true;
            }
        }

        (function() {
            const token = localStorage.getItem('auth_token');
            const role = localStorage.getItem('user_role');

            if (!token || isJwtExpired(token)) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_role');
                localStorage.removeItem('user_name');
                localStorage.removeItem('user_email');
                window.location.href = basePath + '/login';
                return;
            }

            if (role !== 'CLIENT') {
                window.location.href = basePath + '/admin/dashboard';
            }
        })();
    </script>

    <!-- CSS Global del Panel Cliente -->
    <link rel="stylesheet" href="/CarolinaMoraEstetica/assets/css/app-global.css">

    <!-- CSS adicional de la vista -->
    <?php if (!empty($extraCSS)): ?>
    <style>
        <?= $extraCSS ?>
    </style>
    <?php endif; ?>
</head>
<body class="bg-slate-50 dark:bg-brand-pureBlack min-h-screen font-sans">

    <!-- ====== TOP HEADER ====== -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-white/5 backdrop-blur-md border-b border-slate-200 dark:border-white/10">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-serif italic text-slate-900 dark:text-white" id="headerTitle"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold">Carolina Mora Estética</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" id="headerUserName">—</span>
                <button data-action="logout"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-red-500 hover:bg-red-600 text-white text-sm font-bold shadow-sm transition transform hover:-translate-y-0.5"
                    aria-label="Cerrar sesión" title="Cerrar sesión">⏻</button>
            </div>
        </div>
    </header>

    <!-- ====== MAIN CONTENT ====== -->
    <main class="max-w-2xl mx-auto px-4 pt-4 pb-24" id="appMain">
        <!-- Alertas dinámicas -->
        <div class="app-alert" id="globalAlert" role="alert" aria-live="polite"></div>

        <!-- Contenido de la vista -->
        <?= $pageContent ?? '' ?>
    </main>

    <!-- ====== BOTTOM NAVIGATION BAR ====== -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-brand-pureBlack border-t border-slate-200 dark:border-white/10 shadow-lg app-bottom-nav">
        <div class="max-w-2xl mx-auto flex justify-around py-2">
            <button class="nav-item <?= ($activeSection === 'Catalog') ? 'active' : '' ?> flex flex-col items-center gap-1 px-4 py-1" data-section="Catalog">
                <span class="nav-icon text-2xl transition-transform">✨</span>
                <span class="nav-label text-xs font-medium text-slate-500 dark:text-slate-400 transition-colors">Servicios</span>
                <span class="nav-dot w-1 h-1 rounded-full bg-brand-cyan opacity-0 transition-opacity"></span>
            </button>
            <button class="nav-item <?= ($activeSection === 'Citas') ? 'active' : '' ?> flex flex-col items-center gap-1 px-4 py-1" data-section="Citas">
                <span class="nav-icon text-2xl transition-transform">📅</span>
                <span class="nav-label text-xs font-medium text-slate-500 dark:text-slate-400 transition-colors">Mis Citas</span>
                <span class="nav-dot w-1 h-1 rounded-full bg-brand-cyan opacity-0 transition-opacity"></span>
            </button>
            <button class="nav-item <?= ($activeSection === 'Perfil') ? 'active' : '' ?> flex flex-col items-center gap-1 px-4 py-1" data-section="Perfil">
                <span class="nav-icon text-2xl transition-transform">👤</span>
                <span class="nav-label text-xs font-medium text-slate-500 dark:text-slate-400 transition-colors">Perfil</span>
                <span class="nav-dot w-1 h-1 rounded-full bg-brand-cyan opacity-0 transition-opacity"></span>
            </button>
        </div>
    </nav>

    <!-- ====== SCRIPTS ====== -->
    <!-- JS Modular del Panel Cliente -->
    <script src="/CarolinaMoraEstetica/assets/js/app-client.js"></script>

    <!-- Dark mode & user name init -->
    <script>
        if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        (function() {
            const storedName = localStorage.getItem('user_name');
            if (storedName) {
                const firstName = storedName.split(' ')[0] || storedName;
                document.getElementById('headerUserName').textContent = firstName;
            }
        })();
    </script>

    <!-- JS adicional de la vista -->
    <?php if (!empty($extraJS)): ?>
    <script>
        <?= $extraJS ?>
    </script>
    <?php endif; ?>

</body>
</html>
