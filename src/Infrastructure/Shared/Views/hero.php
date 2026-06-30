<?php
declare(strict_types=1);
?>
<section id="inicio" class="relative pt-32 pb-24 md:py-40 px-6 min-h-screen flex items-center overflow-hidden bg-gradient-to-tr from-slate-100 via-slate-200 to-slate-100 dark:from-slate-900 dark:via-[#0c0f16] dark:to-brand-pureBlack">
    <div class="absolute inset-0 bg-grid-slate-200 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] dark:bg-grid-slate-800 dark:[mask-image:linear-gradient(0deg,transparent,rgba(0,0,0,0.8))] z-0"></div>
    <div class="absolute top-0 right-0 w-1/2 h-full bg-slate-200/40 dark:bg-[#0f121a]/60 skew-x-[-12deg] translate-x-24 z-0 hidden md:block"></div>

    <!-- Sparkles -->
    <div class="absolute top-28 right-[10%] opacity-60 dark:opacity-80 sparkle-icon hidden md:block">
        <svg class="w-8 h-8 text-brand-gold" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0l3 9 9 3-9 3-3 9-3-9-9-3 9-3z"/></svg>
    </div>

    <div class="container mx-auto grid md:grid-cols-2 gap-12 items-center relative z-10">
        <div class="text-left">
            <div class="w-16 h-1 bg-gradient-to-r from-brand-goldDark to-brand-gold mb-8"></div>
            <span class="text-brand-goldDark dark:text-brand-gold font-bold tracking-[0.3em] uppercase text-xs mb-4 block">Estudio de Estética Facial & Corporal</span>
            
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-bold leading-tight mb-6 font-serif text-slate-950 dark:text-white">
                El arte de la <br>
                <span class="italic font-light gold-text-gradient-light dark:gold-text-gradient-dark">perfección</span> <br>
                facial.
            </h1>
            
            <p class="text-base sm:text-lg mb-10 max-w-lg leading-relaxed font-light text-slate-700 dark:text-slate-300">
                Transforma tu mirada con técnicas profesionales de alta fidelidad. Diseños exclusivos diseñados meticulosamente en Llorente, Nariño.
            </p>
            
            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-6">
                <button onclick="scrollToSection('servicios')" class="bg-gradient-to-r from-brand-goldDark to-brand-gold text-white px-10 py-5 rounded-sm shadow-xl font-bold text-xs tracking-[0.2em] uppercase hover:scale-[1.03] transition-all duration-300">Explorar Servicios</button>
                <button onclick="window.open('https://wa.me/573218915292?text=Hola%20Carolina,%20quiero%20solicitar%20mi%20valoraci%C3%B3n%20facial%20gratuita')" class="border-2 border-slate-900 dark:border-brand-gold/40 text-slate-950 dark:text-white px-10 py-5 rounded-sm hover:bg-slate-900/5 dark:hover:bg-white/5 transition-all duration-300 font-bold text-xs tracking-[0.2em] uppercase">Valoración Gratis</button>
            </div>
        </div>

        <div class="relative flex justify-center">
            <div class="absolute -inset-4 border-2 border-brand-gold/30 translate-x-4 translate-y-4 z-0 rounded-sm"></div>
            <div class="relative z-10 bg-slate-300 dark:bg-slate-800 w-full max-w-[450px] h-[500px] overflow-hidden shadow-2xl rounded-sm">
                <div class="w-full h-full bg-cover bg-center grayscale hover:grayscale-0 transition-all duration-1000 transform hover:scale-105" 
                     style="background-image: url('assets/images/landing/estetica_carolina.jpeg');">
                </div>
            </div>
        </div>
    </div>
</section>