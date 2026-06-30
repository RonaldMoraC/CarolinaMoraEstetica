<?php declare(strict_types=1); ?>
<header class="h-16 bg-white/95 dark:bg-brand-pureBlack/98 backdrop-blur-md border-b border-slate-200 dark:border-brand-gold/20 flex items-center justify-between px-8 sticky top-0 z-40 transition-all duration-500">
    <div class="flex items-center gap-4">
        <h2 class="text-[10px] font-bold tracking-[0.3em] uppercase text-black dark:text-slate-400">Panel de Control</h2>
    </div>
    
    <div class="flex items-center space-x-6">
        <!-- Botón de cambio de tema premium -->
        <button id="theme-toggle-btn" onclick="toggleTheme()" class="p-2.5 rounded-full border border-slate-200 dark:border-brand-gold/30 hover:bg-slate-100 dark:hover:bg-brand-gold/10 transition-all duration-300 focus:outline-none flex items-center justify-center">
            <!-- Icono Sol (Se ve en modo oscuro) -->
            <svg id="sun-icon" class="w-5 h-5 text-brand-goldLight hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 9h-1m14.072-4.072l-.707.707M6.343 17.657l-.707.707M16.243 17.657l.707.707M6.343 6.343l.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
            </svg>
            <!-- Icono Luna (Se ve en modo claro) -->
            <svg id="moon-icon" class="w-5 h-5 text-slate-700 dark:text-brand-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>

        <!-- Perfil e inicio de sesión -->
        <div class="flex items-center space-x-4 border-l border-slate-200 dark:border-brand-gold/20 pl-6">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-black dark:text-white uppercase tracking-tight">Carolina Mora</p>
                <p class="text-[9px] text-black dark:text-brand-gold font-bold uppercase tracking-[0.1em] opacity-100">Administradora</p>
            </div>
            <button onclick="logout()" class="group flex items-center justify-center w-9 h-9 rounded-full bg-slate-100 dark:bg-brand-charcoal border border-slate-200 dark:border-brand-gold/20 text-slate-500 hover:text-red-600 dark:hover:text-red-500 transition-all shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </button>
        </div>
    </div>
</header>
