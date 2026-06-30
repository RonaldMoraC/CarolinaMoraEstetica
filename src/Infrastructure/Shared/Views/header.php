<?php declare(strict_types=1); ?>
<header class="h-16 bg-white/80 dark:bg-brand-pureBlack/90 backdrop-blur-md border-b border-slate-200 dark:border-brand-gold/20 flex items-center justify-between px-8 sticky top-0 z-40">
    <div class="flex items-center gap-4">
        <div class="md:hidden">
            <button @click="isSidebarOpen = !isSidebarOpen" class="text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>
        <h2 class="text-sm font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">Panel de Control</h2>
    </div>
    
    <div class="flex items-center space-x-6">
        <button id="theme-toggle" onclick="toggleTheme()" class="p-2 rounded-full border border-slate-200 dark:border-brand-gold/20 text-slate-500 dark:text-brand-gold hover:bg-slate-50 dark:hover:bg-brand-gold/5 transition-all">
            <svg id="sun-icon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9h-1m14.072-4.072l-.707.707M6.343 17.657l-.707.707M16.243 17.657l.707.707M6.343 6.343l.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"></path></svg>
            <svg id="moon-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
        </button>
        
        <div class="flex items-center space-x-3 pl-6 border-l border-slate-200 dark:border-white/10">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-tighter" id="user-display-name">Cargando...</p>
                <p class="text-[10px] text-brand-goldDark dark:text-brand-gold font-medium uppercase tracking-widest" id="user-display-role">Staff</p>
            </div>
            <button onclick="logout()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 hover:text-red-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </button>
        </div>
    </div>
</header>