<?php
declare(strict_types=1);
?>
<section id="equipo" class="py-24 bg-slate-100 dark:bg-brand-pureBlack transition-colors duration-500 border-t border-slate-200 dark:border-slate-900">
    <div class="container mx-auto px-6">
        <div class="flex flex-col items-center text-center mb-16">
            <div class="flex items-center space-x-1 mb-2">
                <div class="text-brand-gold">✦</div>
                <span class="text-brand-goldDark dark:text-brand-gold font-bold text-xs uppercase tracking-[0.25em]">Estudio Clínico</span>
                <div class="text-brand-gold">✦</div>
            </div>
            <h2 class="text-4xl font-bold text-slate-950 dark:text-white font-serif">Nuestro Equipo de Especialistas</h2>
            <div class="w-16 h-1 bg-gradient-to-r from-brand-goldDark to-brand-gold mx-auto mt-4 mb-4"></div>
            <p class="text-slate-700 dark:text-slate-300 max-w-2xl text-sm font-light">
                Liderado por Carolina Mora, nuestro personal especializado garantiza una experiencia de alta gama en Llorente, Nariño.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <!-- Carolina Mora -->
            <div class="bg-white dark:bg-slate-900/60 p-6 rounded-sm shadow-lg gold-border-glow transition duration-300 hover:scale-[1.02] flex flex-col justify-between">
                <div>
                    <div class="w-full h-64 overflow-hidden mb-6 rounded-sm relative">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80" alt="Carolina Mora" class="w-full h-full object-cover">
                        <span class="absolute bottom-2 left-2 bg-brand-gold text-white text-[9px] uppercase tracking-widest px-3 py-1 font-semibold rounded-sm">Directora</span>
                    </div>
                    <h4 class="text-lg font-bold tracking-wider text-slate-950 dark:text-slate-50 uppercase mb-1">Carolina Mora</h4>
                    <span class="text-[10px] text-brand-goldDark dark:text-brand-gold font-bold uppercase tracking-widest block mb-4">Master en Micropigmentación</span>
                    <p class="text-slate-700 dark:text-slate-300 text-xs font-light leading-relaxed mb-6">
                        Fundadora del estudio. Especializada en técnicas europeas avanzadas de diseño de cejas hiperrealistas.
                    </p>
                </div>
            </div>
            <!-- Se pueden añadir más miembros aquí -->
        </div>
    </div>
</section>
