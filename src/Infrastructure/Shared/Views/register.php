<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Carolina Mora Estética</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: { gold: '#D4AF37', goldDark: '#B8860B', pureBlack: '#050505' } }, fontFamily: { serif: ['Playfair Display', 'serif'], sans: ['Montserrat', 'sans-serif'] } } } }
    </script>
    <style>.glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(212, 175, 55, 0.2); } .gold-gradient { background: linear-gradient(135deg, #B8860B 0%, #D4AF37 50%, #B8860B 100%); }</style>
</head>
<body class="bg-slate-100 dark:bg-brand-pureBlack min-h-screen flex items-center justify-center p-6 font-sans transition-colors duration-500">
    <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-gold/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-gold/5 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-xl relative z-10">
        <div class="text-center mb-8">
            <a href="/" id="linkHome" class="inline-flex items-center space-x-2 group">
                <div class="w-10 h-10 rounded-full border border-brand-gold/60 flex items-center justify-center font-serif text-brand-gold group-hover:bg-brand-gold group-hover:text-white transition-all">CM</div>
                <span class="text-xs tracking-[0.3em] uppercase font-bold text-slate-500 dark:text-slate-400 group-hover:text-brand-gold transition-colors">Volver al inicio</span>
            </a>
        </div>

        <div class="glass p-8 md:p-10 rounded-sm shadow-2xl">
            <h1 class="text-3xl font-serif italic text-slate-900 dark:text-white mb-2 text-center">Únete a la Exclusividad</h1>
            <p class="text-[10px] tracking-[0.2em] uppercase text-brand-goldDark dark:text-brand-gold text-center mb-10 font-bold">Crea tu cuenta de cliente</p>

            <form id="registerForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Nombre</label>
                    <input type="text" id="first_name" required autocomplete="given-name" 
                        class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white" placeholder="Ana">
                </div>
                <div>
                    <label for="last_name" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Apellido</label>
                    <input type="text" id="last_name" required autocomplete="family-name" 
                        class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white" placeholder="García">
                </div>
                <div class="md:col-span-2">
                    <label for="email" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Email</label>
                    <input type="email" id="email" required autocomplete="email" 
                        class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white" placeholder="ana@ejemplo.com">
                </div>
                <div>
                    <label for="phone" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Teléfono</label>
                    <input type="tel" id="phone" required autocomplete="tel" 
                        class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white" placeholder="+57 321 000 0000">
                </div>
                <div class="relative">
                    <label for="password" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Contraseña</label>
                    <div class="relative">
                        <input type="password" id="password" required autocomplete="new-password" 
                            class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white pr-12" placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-brand-gold">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Honeypot Anti-Bot -->
                <div class="hidden" aria-hidden="true">
                    <input type="text" id="hp_user_check" tabindex="-1" autocomplete="off">
                </div>

                <div id="statusMessage" role="alert" aria-live="polite" class="hidden md:col-span-2 text-[11px] p-3 border text-center"></div>

                <button type="submit" id="btnSubmit" class="md:col-span-2 w-full gold-gradient text-white py-4 text-xs font-bold uppercase tracking-[0.2em] rounded-sm shadow-lg hover:brightness-110 transition-all duration-300 transform active:scale-[0.98]">
                    Finalizar Registro
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-200 dark:border-white/5 text-center">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest leading-loose">
                    ¿Ya tienes una cuenta? <br>
                    <a href="#" id="linkLogin" class="text-brand-gold hover:underline">Inicia Sesión aquí</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        /**
         * basePath — Detecta el subdirectorio del proyecto dinámicamente.
         */
        const basePath = (function() {
            const segments = window.location.pathname.split('/').filter(Boolean);
            if (segments.length >= 1 && (segments[0] === 'CarolinaMoraEstetica' || segments.includes('CarolinaMoraEstetica'))) {
                return '/' + segments[0];
            }
            return '';
        })();

        if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');

        function togglePassword() {
            const input = document.getElementById('password');
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            document.getElementById('eye-icon').classList.toggle('text-brand-gold', type === 'text');
        }

        // Inicializar links dinámicos con basePath
        document.getElementById('linkHome').href = basePath + '/';
        document.getElementById('linkLogin').href = basePath + '/login';

        const registerForm = document.getElementById('registerForm');
        const statusMessage = document.getElementById('statusMessage');
        const btnSubmit = document.getElementById('btnSubmit');

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Verificación de Honeypot
            if (document.getElementById('hp_user_check').value !== '') return;

            const fields = {
                firstName: document.getElementById('first_name'),
                lastName: document.getElementById('last_name'),
                email: document.getElementById('email'),
                phone: document.getElementById('phone'),
                password: document.getElementById('password')
            };

            // Limpiar estados previos
            Object.values(fields).forEach(f => f.classList.remove('border-red-500'));
            statusMessage.classList.add('hidden');

            // Validaciones de Integridad (Skill 10: Sanitización Perimetral)
            const nameRegex = /^[a-zA-ZÀ-ÿ\s]{2,40}$/;
            if (!nameRegex.test(fields.firstName.value.trim())) {
                fields.firstName.classList.add('border-red-500');
                showError('El nombre debe contener solo letras y al menos 2 caracteres.');
                return;
            }

            if (!nameRegex.test(fields.lastName.value.trim())) {
                fields.lastName.classList.add('border-red-500');
                showError('El apellido debe contener solo letras y al menos 2 caracteres.');
                return;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email.value.trim())) {
                fields.email.classList.add('border-red-500');
                showError('El correo electrónico no tiene un formato válido.');
                return;
            }

            // Validación de Teléfono (Celular Colombia/Internacional básico)
            const phoneValue = fields.phone.value.replace(/\D/g, '');
            if (phoneValue.length < 10) {
                fields.phone.classList.add('border-red-500');
                showError('Ingresa un número de teléfono válido (10 dígitos).');
                return;
            }

            if (fields.password.value.length < 8) {
                fields.password.classList.add('border-red-500');
                showError('La contraseña debe tener al menos 8 caracteres.');
                return;
            }

            btnSubmit.disabled = true;
            const originalText = btnSubmit.innerText;
            btnSubmit.innerHTML = '<span class="inline-block animate-pulse">Creando Perfil...</span>';

            const data = {
                firstName: fields.firstName.value.trim(),
                lastName: fields.lastName.value.trim(),
                email: fields.email.value.trim(),
                phone: fields.phone.value.trim(),
                password: fields.password.value
            };

            function showError(msg) {
                statusMessage.innerText = msg;
                statusMessage.className = 'md:col-span-2 text-[11px] text-red-500 bg-red-500/10 p-3 border border-red-500/20 text-center';
                statusMessage.classList.remove('hidden');
                btnSubmit.disabled = false;
                btnSubmit.innerText = originalText;
            }

            try {
                const response = await fetch(basePath + '/api/v1/auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok) {
                    statusMessage.innerText = result.message;
                    statusMessage.className = 'md:col-span-2 text-[11px] text-green-500 bg-green-500/10 p-3 border border-green-500/20 text-center';
                    statusMessage.classList.remove('hidden');
                    btnSubmit.innerText = '¡Perfil Creado!';
                    setTimeout(() => window.location.href = basePath + '/login', 2500);
                } else {
                    throw new Error(result.detail || 'Error en el servidor');
                }
            } catch (error) {
                showError(error.message);
            }
        });
    </script>
</body>
</html>
