<?php declare(strict_types=1); ?>
<aside class="w-64 bg-white dark:bg-brand-pureBlack border-r border-slate-200 dark:border-white/5 flex flex-col h-screen sticky top-0 overflow-y-auto z-50 transition-all duration-300">
    <div class="p-8">
        <div class="flex items-center space-x-3 mb-10">
            <div class="w-9 h-9 rounded-full border border-brand-gold/60 flex items-center justify-center font-serif text-sm font-bold tracking-wider text-black">CM</div>
            <span class="text-[11px] font-bold tracking-[0.2em] uppercase text-black dark:text-slate-200">CAROLINA <span class="text-brand-gold">MORA</span></span>
        </div>

        <nav class="space-y-1">
            <?php
            $menuItems = [
                ['url' => 'admin-dashboard.php', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['url' => 'admin-recepcion.php', 'label' => 'Recepción', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                ['url' => 'admin-calendario.php', 'label' => 'Calendario', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['url' => 'admin-servicios.php', 'label' => 'Servicios', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.675.338a2 2 0 00-.894 2.605l.338.675a2 2 0 002.605.894l.675-.338a6 6 0 003.86-.517l2.387-.477a2 2 0 001.022-.547l.338-.675a2 2 0 00-.894-2.605l-.338-.675z'],
                ['url' => 'admin-horarios.php', 'label' => 'Horarios', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['url' => 'admin-promociones.php', 'label' => 'Promociones', 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
                ['url' => 'admin-usuarios.php', 'label' => 'Usuarios', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['url' => 'admin-reseñas.php', 'label' => 'Reseñas', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
                ['url' => 'admin-analiticas.php', 'label' => 'Analíticas', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ];

            foreach ($menuItems as $item): ?>
                <a href="<?php echo $item['url']; ?>" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-sm text-[11px] font-bold uppercase tracking-[0.15em] transition-all duration-200
                   <?php echo str_contains($_SERVER['REQUEST_URI'], $item['url']) 
                       ? 'bg-brand-gold !text-white shadow-md shadow-brand-gold/20' 
                       : 'text-black dark:text-slate-300 hover:text-brand-gold dark:hover:text-brand-gold hover:bg-slate-100 dark:hover:bg-white/5'; ?>">
                    
                    <svg class="w-4 h-4 transition-colors" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $item['icon']; ?>"></path>
                    </svg>
                    
                    <span class="font-bold"><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="mt-auto p-8 border-t border-slate-200 dark:border-white/5">
        <p class="text-[9px] text-black dark:text-slate-400 font-bold uppercase tracking-widest">Ecosistema Digital</p>
    </div>
</aside>