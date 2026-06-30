<?php declare(strict_types=1); ?>
<aside class="w-64 bg-slate-50 dark:bg-brand-pureBlack border-r border-slate-200 dark:border-brand-gold/10 flex flex-col h-screen sticky top-0 overflow-y-auto z-50 transition-all duration-300">
    <div class="p-8">
        <div class="flex items-center space-x-3 mb-10">
            <div class="w-8 h-8 rounded-full border border-brand-gold flex items-center justify-center font-serif text-brand-gold text-xs">CM</div>
            <span class="text-xs font-bold tracking-[0.3em] uppercase text-slate-900 dark:text-white">Admin<span class="text-brand-gold">Panel</span></span>
        </div>

        <nav class="space-y-1">
            <?php
            $menuItems = [
                ['url' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['url' => 'recepcion', 'label' => 'Recepción', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                ['url' => 'calendario', 'label' => 'Calendario', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['url' => 'servicios', 'label' => 'Servicios', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.675.338a2 2 0 00-.894 2.605l.338.675a2 2 0 002.605.894l.675-.338a6 6 0 003.86-.517l2.387-.477a2 2 0 001.022-.547l.338-.675a2 2 0 00-.894-2.605l-.338-.675z'],
                ['url' => 'horarios', 'label' => 'Horarios', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['url' => 'usuarios', 'label' => 'Usuarios', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['url' => 'analiticas', 'label' => 'Analíticas', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ];

            foreach ($menuItems as $item): ?>
                <a href="/admin/<?php echo $item['url']; ?>" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-sm text-[11px] font-bold uppercase tracking-widest transition-all
                   <?php echo str_contains($_SERVER['REQUEST_URI'], $item['url']) 
                       ? 'bg-brand-gold text-white shadow-lg shadow-brand-gold/20' 
                       : 'text-slate-500 hover:text-brand-gold hover:bg-slate-100 dark:hover:bg-white/5'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $item['icon']; ?>"></path>
                    </svg>
                    <span><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="mt-auto p-8 border-t border-slate-200 dark:border-white/5">
        <p class="text-[9px] text-slate-400 uppercase tracking-widest">Ecosistema Digital</p>
        <p class="text-[9px] text-brand-gold font-bold uppercase tracking-widest mt-1">v2.0 Antigravity</p>
    </div>
</aside>