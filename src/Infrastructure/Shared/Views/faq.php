<?php
declare(strict_types=1);
?>
<section id="faq" class="py-24 bg-white dark:bg-[#0c0f16] border-t border-slate-200 dark:border-slate-900 transition-colors duration-500">
    <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-16">
                <span class="text-brand-goldDark dark:text-brand-gold font-bold text-xs uppercase tracking-[0.25em] mb-2 block">Dudas Resueltas</span>
                <h2 class="text-4xl font-bold text-slate-950 dark:text-white font-serif italic">Preguntas & Certezas</h2>
                <div class="w-16 h-1 bg-gradient-to-r from-brand-goldDark to-brand-gold mx-auto mt-4"></div>
            </div>

            <div class="space-y-6">
                <!-- FAQ 1 -->
                <div class="border border-slate-300 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 p-6 rounded-sm hover:shadow-md transition cursor-pointer" onclick="toggleFaq(1)">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold uppercase tracking-widest text-xs sm:text-sm text-slate-950 dark:text-slate-100">¿El procedimiento genera dolor?</h4>
                        <span id="icon-1" class="text-brand-gold text-2xl font-light">+</span>
                    </div>
                    <div id="faq-1" class="hidden mt-4 text-slate-700 dark:text-slate-300 text-sm font-light leading-relaxed border-t border-slate-300 dark:border-slate-800/80 pt-4">
                        Garantizamos confort absoluto. Hacemos uso de anestésicos tópicos de última generación. La mayoría de nuestras clientas relatan que la experiencia se asimila a una vibración muy leve.
                    </div>
                </div>
                <!-- FAQ 2 -->
                <div class="border border-slate-300 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 p-6 rounded-sm hover:shadow-md transition cursor-pointer" onclick="toggleFaq(2)">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold uppercase tracking-widest text-xs sm:text-sm text-slate-950 dark:text-slate-100">¿Cuánto tiempo toma la cicatrización?</h4>
                        <span id="icon-2" class="text-brand-gold text-2xl font-light">+</span>
                    </div>
                    <div id="faq-2" class="hidden mt-4 text-slate-700 dark:text-slate-300 text-sm font-light leading-relaxed border-t border-slate-300 dark:border-slate-800/80 pt-4">
                        La primera fase toma aproximadamente de 7 a 10 días. El color definitivo se asienta de manera molecular en la epidermis a las 4 semanas.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
