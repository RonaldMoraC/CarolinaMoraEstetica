<?php
declare(strict_types=1);
?>
<section id="servicios" class="py-24 transition-colors duration-500 bg-slate-100 dark:bg-brand-pureBlack border-t border-slate-200 dark:border-slate-900">
    <div class="container mx-auto px-6">
        <div class="flex flex-col items-center text-center mb-16">
            <div class="flex items-center space-x-1 mb-2">
                <div class="text-brand-gold">✦</div>
                <span class="text-brand-goldDark dark:text-brand-gold font-bold text-xs uppercase tracking-[0.25em]">Estudio de Autor</span>
                <div class="text-brand-gold">✦</div>
            </div>
            <h2 class="text-4xl font-bold text-slate-950 dark:text-white font-serif">Nuestros Servicios Profesionales</h2>
            <p class="text-slate-700 dark:text-slate-300 max-w-xl text-sm font-light mt-3">
                Estructura oficial de tratamientos diseñados meticulosamente por Carolina Mora para realzar tu belleza.
            </p>
        </div>

        <!-- Tabs de Navegación -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-12 border-b border-slate-300 dark:border-slate-800">
            <button onclick="updateService('micropigmentacion')" id="btn-micropigmentacion" class="service-tab pb-4 text-center border-b-2 border-transparent transition duration-300 group">
                <span class="block text-[9px] text-slate-500 dark:text-slate-400 group-hover:text-brand-gold tracking-widest uppercase mb-1">Especialidad 01</span>
                <span class="text-xs sm:text-sm font-bold tracking-wider uppercase text-slate-800 dark:text-white">Micropigmentación</span>
            </button>
            <button onclick="updateService('pestanas')" id="btn-pestanas" class="service-tab pb-4 text-center border-b-2 border-transparent transition duration-300 group">
                <span class="block text-[9px] text-slate-500 dark:text-slate-400 group-hover:text-brand-gold tracking-widest uppercase mb-1">Especialidad 02</span>
                <span class="text-xs sm:text-sm font-bold tracking-wider uppercase text-slate-800 dark:text-white">Pestañas</span>
            </button>
            <button onclick="updateService('tratamientos')" id="btn-tratamientos" class="service-tab pb-4 text-center border-b-2 border-transparent transition duration-300 group">
                <span class="block text-[9px] text-slate-500 dark:text-slate-400 group-hover:text-brand-gold tracking-widest uppercase mb-1">Especialidad 03</span>
                <span class="text-xs sm:text-sm font-bold tracking-wider uppercase text-slate-800 dark:text-white">Tratamientos</span>
            </button>
            <button onclick="updateService('depilacion')" id="btn-depilacion" class="service-tab pb-4 text-center border-b-2 border-transparent transition duration-300 group">
                <span class="block text-[9px] text-slate-500 dark:text-slate-400 group-hover:text-brand-gold tracking-widest uppercase mb-1">Especialidad 04</span>
                <span class="text-xs sm:text-sm font-bold tracking-wider uppercase text-slate-800 dark:text-white">Depilación Cera</span>
            </button>
            <button onclick="updateService('cabello')" id="btn-cabello" class="service-tab pb-4 text-center border-b-2 border-transparent transition duration-300 group col-span-2 md:col-span-1">
                <span class="block text-[9px] text-slate-500 dark:text-slate-400 group-hover:text-brand-gold tracking-widest uppercase mb-1">Especialidad 05</span>
                <span class="text-xs sm:text-sm font-bold tracking-wider uppercase text-slate-800 dark:text-white">Cabello</span>
            </button>
        </div>

        <!-- Panel de Contenido Dinámico -->
        <div id="service-panel" class="grid md:grid-cols-2 gap-12 items-center opacity-0 translate-y-4 transition-all duration-500">
            <div>
                <h3 id="s-title" class="text-3xl md:text-4xl font-bold mb-6 text-slate-900 dark:text-white font-serif italic">Cargando...</h3>
                <p id="s-desc" class="text-slate-700 dark:text-slate-300 font-light leading-relaxed mb-8 text-base">...</p>
                
                <div class="grid grid-cols-2 gap-6 mb-8 border-y border-slate-300 dark:border-slate-800 py-6">
                    <div>
                        <span class="block text-brand-goldDark dark:text-brand-gold font-bold text-[9px] uppercase tracking-widest mb-1">Retoque</span>
                        <span id="s-freq" class="text-xs font-semibold text-slate-900 dark:text-slate-200 uppercase tracking-wider">...</span>
                    </div>
                    <div>
                        <span class="block text-brand-goldDark dark:text-brand-gold font-bold text-[9px] uppercase tracking-widest mb-1">Duración</span>
                        <span id="s-time" class="text-xs font-semibold text-slate-900 dark:text-slate-200 uppercase tracking-wider">...</span>
                    </div>
                </div>

                <ul id="s-list" class="space-y-3.5 mb-8"></ul>

                <a id="s-cta" href="#" target="_blank" class="bg-gradient-to-r from-brand-goldDark to-brand-gold text-white inline-block px-10 py-4 font-bold text-xs tracking-widest uppercase rounded-sm shadow-xl hover:brightness-111 transition duration-300">
                    Consultar Vía WhatsApp
                </a>
            </div>

            <div class="relative">
                <div class="absolute inset-0 bg-brand-gold/10 dark:bg-brand-gold/5 translate-x-4 translate-y-4 rounded-sm"></div>
                <div class="relative h-[440px] overflow-hidden grayscale hover:grayscale-0 transition-all duration-700 rounded-sm shadow-xl border border-slate-300 dark:border-slate-800">
                    <img id="s-img" src="" alt="Servicio" class="w-full h-full object-cover">
                </div>
            </div>
        </div>
    </div>
</section>