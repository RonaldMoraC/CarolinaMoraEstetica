<?php declare(strict_types=1); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Montserrat:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">

<script>
    // Configuración de Tailwind antes de la carga del script para evitar parpadeos y errores de contraste
    window.tailwind = {
        config: {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#D4AF37',      /* Oro Clásico */
                            goldDark: '#B8860B',  /* Oro Profundo */
                            goldLight: '#F1D37E', /* Oro Brillante */
                            silver: '#F1F5F9',    /* Gris Plata */
                            charcoal: '#000',  /* Carbón */
                            pureBlack: '#000', /* Negro Absoluto */
                        }
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'serif'],
                        sans: ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>

<script>
    // Lógica de Rutas y Seguridad
    (function() {
        const pathSegments = window.location.pathname.split('/');
        const publicIndex = pathSegments.indexOf('public');
        const baseUrl = publicIndex !== -1 ? pathSegments.slice(0, publicIndex + 1).join('/') : '';
        window.fullBaseUrl = (baseUrl.startsWith('/') ? '' : '/') + baseUrl;

        const token = localStorage.getItem('auth_token');
        const role = (localStorage.getItem('user_role') || '').toUpperCase().trim();
        const staffRoles = ['SUPER_ADMIN', 'BRANCH_ADMIN', 'RECEPCIONIST'];

        if (!token || !staffRoles.includes(role)) {
            window.location.href = window.fullBaseUrl + '/login';
        }

        // Aplicar tema guardado inmediatamente para evitar parpadeo blanco
        if (localStorage.getItem('theme') === 'light' || (!('light' in localStorage) && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
        }
    })();

    window.logout = function() { 
        localStorage.clear(); 
        window.location.href = window.fullBaseUrl + '/login'; 
    };
    
    // Función toggleTheme Centralizada
    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        const event = new CustomEvent('themeChanged', { detail: { isDark } });
        window.dispatchEvent(event);
        syncThemeIcons();
    }

    function syncThemeIcons() {
        const isDark = document.documentElement.classList.contains('dark');
        const sunIcon = document.getElementById('sun-icon');
        const moonIcon = document.getElementById('moon-icon');
        if (sunIcon && moonIcon) {
            if (isDark) {
                sunIcon.classList.remove('hidden');
                moonIcon.classList.add('hidden');
            } else {
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            }
        }
    }

    // Sincronización inicial
    document.addEventListener("DOMContentLoaded", syncThemeIcons);
</script>

<style type="text/tailwindcss">
    @layer base {
        body {
            @apply text-slate-900 dark:text-slate-100 antialiased font-sans; 
            font-display: swap;
        }
    }

    @layer components {
        .gold-text-gradient {
            background: linear-gradient(135deg, #B8860B 0%, #D4AF37 50%, #B8860B 100%);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
        }
        .dark .gold-text-gradient {
            background: linear-gradient(135deg, #F1D37E 0%, #D4AF37 50%, #F1D37E 100%);
        }
        .luxury-border {
            @apply border border-slate-200 dark:border-brand-gold/10 shadow-sm transition-all duration-300 hover:border-brand-gold/30;
        }
        /* Ajustes de contraste para legibilidad AA/AAA */
        .dark .text-slate-500 { @apply text-slate-400; }
        .dark .text-slate-400 { @apply text-slate-300; }
        [v-cloak] { display: none; }
    }
</style>

<style>
    @keyframes sparkle { 0%, 100% { transform: scale(0.8); opacity: 0.5; } 50% { transform: scale(1.2); opacity: 1; } }
    .sparkle-icon { animation: sparkle 3s infinite ease-in-out; }
    
    /* Scrollbar Luxury con Hex Codes para evitar advertencias de VS Code */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #D4AF37; border-radius: 9999px; }
    .dark ::-webkit-scrollbar-track { background: #050505; }
</style>