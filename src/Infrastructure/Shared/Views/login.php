<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Exclusivo - Carolina Mora Estética</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#D4AF37',
                            goldDark: '#B8860B',
                            pureBlack: '#050505',
                        }
                    },
                    fontFamily: { serif: ['Playfair Display', 'serif'], sans: ['Montserrat', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(212, 175, 55, 0.2); }
        .gold-gradient { background: linear-gradient(135deg, #B8860B 0%, #D4AF37 50%, #B8860B 100%); }
    </style>
    <script>
        /**
         * basePath — Detecta el subdirectorio del proyecto dinámicamente.
         * Ejemplo: para http://localhost/CarolinaMoraEstetica/login
         *   → basePath = '/CarolinaMoraEstetica'
         * Para http://localhost/login (producción en root)
         *   → basePath = ''
         */
        const basePath = (function() {
            const segments = window.location.pathname.split('/').filter(Boolean);
            // La URL visible NO contiene /public/; las rutas de vista son /basePath/login, /basePath/register, etc.
            // Buscamos el segmento conocido del proyecto (CarolinaMoraEstetica) o usamos he primer segmento.
            if (segments.length >= 1 && (segments[0] === 'CarolinaMoraEstetica' || segments.includes('CarolinaMoraEstetica'))) {
                return '/' + segments[0];
            }
            // Si no hay subdirectorio conocido (deploy en root), basePath es vacío
            return '';
        })();

        /**
         * Protección de Rutas (Auth Guard):
         * Si el usuario ya tiene un token, lo redirigimos a su dashboard
         * para evitar que vuelva a loguearse innecesariamente.
         */
        (function() {
            const token = localStorage.getItem('auth_token');
            const role = (localStorage.getItem('user_role') || '').toUpperCase().trim();

            if (token && role) {
                const target = ['SUPER_ADMIN', 'BRANCH_ADMIN', 'RECEPCIONIST', 'ADMIN', '1'].includes(role)
                    ? basePath + '/admin/dashboard'
                    : basePath + '/app/dashboard';

                window.location.href = target;
            }
        })();
    </script>
</head>
<body class="bg-slate-100 dark:bg-brand-pureBlack min-h-screen flex items-center justify-center p-6 font-sans transition-colors duration-500">
    
    <!-- Fondo Decorativo -->
    <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-gold/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-gold/5 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo / Regresar -->
        <div class="text-center mb-8">
            <a id="linkHome" href="#" class="inline-flex items-center space-x-2 group">
                <div class="w-10 h-10 rounded-full border border-brand-gold/60 flex items-center justify-center font-serif text-brand-gold group-hover:bg-brand-gold group-hover:text-white transition-all">CM</div>
                <span class="text-xs tracking-[0.3em] uppercase font-bold text-slate-500 dark:text-slate-400 group-hover:text-brand-gold transition-colors">Volver al inicio</span>
            </a>
        </div>

        <!-- Card de Login -->
        <div class="glass p-8 md:p-10 rounded-sm shadow-2xl">
            <h1 class="text-3xl font-serif italic text-slate-900 dark:text-white mb-2 text-center">Bienvenida</h1>
            <p class="text-[10px] tracking-[0.2em] uppercase text-brand-goldDark dark:text-brand-gold text-center mb-10 font-bold">Acceso Clientes & Staff</p>

            <form id="loginForm" class="space-y-6">
                <div>
                    <label for="email" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Correo Electrónico</label>
                    <input type="email" id="email" name="username" required autocomplete="username"
                        class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white"
                        placeholder="ejemplo@correo.com">
                </div>

                <div class="relative">
                    <label for="password" class="block text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2 font-bold">Contraseña</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full bg-white dark:bg-white/5 border border-slate-300 dark:border-white/10 p-4 text-sm rounded-sm focus:border-brand-gold outline-none transition-all dark:text-white pr-12"
                            placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-brand-gold transition-colors">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Honeypot Anti-Bot -->
                <div class="hidden" aria-hidden="true">
                    <input type="text" id="hp_user_check" tabindex="-1" autocomplete="off">
                </div>

                <div id="errorMessage" role="alert" aria-live="polite" class="hidden text-[11px] text-red-500 bg-red-500/10 p-3 border border-red-500/20 text-center"></div>

                <button type="submit" id="btnSubmit" class="w-full gold-gradient text-white py-4 text-xs font-bold uppercase tracking-[0.2em] rounded-sm shadow-lg hover:brightness-110 transition-all duration-300 transform active:scale-[0.98]">
                    Iniciar Sesión
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-200 dark:border-white/5 text-center">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest leading-loose">
                    ¿No tienes una cuenta? <br>
                    <a id="linkRegister" href="#" class="text-brand-gold hover:underline">Regístrate para agendar</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Detectar tema desde localStorage
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            document.getElementById('eye-icon').classList.toggle('text-brand-gold', type === 'text');
        }

        // Inicializar links dinámicos con basePath
        document.getElementById('linkHome').href = basePath + '/';
        document.getElementById('linkRegister').href = basePath + '/register';

        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        const btnSubmit = document.getElementById('btnSubmit');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Skill 10: Honeypot check (si el bot llenó el campo oculto, abortamos)
            if (document.getElementById('hp_user_check').value !== '') {
                console.warn('Bot detection triggered.');
                return;
            }

            // Limpieza preventiva de restos de sesiones anteriores
            localStorage.clear();

            // UI State: Loading
            const emailInput = document.getElementById('email');
            const passInput = document.getElementById('password');
            
            errorMessage.classList.add('hidden');
            emailInput.classList.remove('border-red-500');
            passInput.classList.remove('border-red-500');
            
            // Validación básica de email previa
            const emailValue = emailInput.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                emailInput.classList.add('border-red-500');
                errorMessage.innerText = 'Por favor ingresa un correo válido.';
                errorMessage.classList.remove('hidden');
                return;
            }

            btnSubmit.disabled = true;
            const originalBtnText = btnSubmit.innerText;
            btnSubmit.innerHTML = '<span class="inline-block animate-pulse">Verificando...</span>';

            const credentials = {
                email: emailValue,
                password: passInput.value
            };

            try {
                const apiUrl = basePath + '/api/v1/auth/login';

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(credentials)
                });
                const result = await response.json();

                if (response.ok) {
                    console.log('Login exitoso. Rol detectado:', result.data.role_code);
                    localStorage.setItem('auth_token', result.data.token);
                    localStorage.setItem('user_role', result.data.role_code);
                    localStorage.setItem('user_name', result.data.user_name || '');
                    localStorage.setItem('user_email', result.data.user_email || '');

                    btnSubmit.innerText = '¡Bienvenido/a!';

                    // Redirección Inteligente basada en basePath + redirect_to del backend
                    setTimeout(() => {
                        window.location.href = basePath + result.data.redirect_to;
                    }, 600);
                } else {
                    throw new Error(result.detail || 'Credenciales inválidas');
                }

            } catch (error) {
                errorMessage.innerText = error.message;
                errorMessage.classList.remove('hidden');
                emailInput.classList.add('border-red-500/50');
                passInput.classList.add('border-red-500/50');
                
                btnSubmit.disabled = false;
                btnSubmit.innerText = originalBtnText;
            }
        });
    </script>
</body>
</html>