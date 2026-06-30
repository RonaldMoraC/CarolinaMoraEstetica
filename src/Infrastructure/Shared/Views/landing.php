<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carolina Mora - Micropigmentación & Estética | Alta Gama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Montserrat:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#D4AF37',
                            goldDark: '#B8860B',
                            goldLight: '#F1D37E',
                            silver: '#F1F5F9',
                            charcoal: '#0F172A',
                            pureBlack: '#050505',
                        }
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'serif'],
                        sans: ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        * { transition: background-color 0.4s ease, border-color 0.4s ease, color 0.4s ease; }
        .gold-text-gradient-light {
            background: linear-gradient(135deg, #B8860B 0%, #D4AF37 50%, #B8860B 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .gold-text-gradient-dark {
            background: linear-gradient(135deg, #F1D37E 0%, #D4AF37 50%, #F1D37E 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .gold-border-glow {
            border: 1px solid rgba(212, 175, 55, 0.4);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.1);
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0F172A; }
        ::-webkit-scrollbar-thumb { background: #D4AF37; border-radius: 4px; }
        @keyframes sparkle { 0%, 100% { transform: scale(0.8); opacity: 0.5; } 50% { transform: scale(1.2); opacity: 1; } }
        .sparkle-icon { animation: sparkle 3s infinite ease-in-out; }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-brand-pureBlack overflow-x-hidden">
    <nav class="fixed top-0 w-full z-50 transition-all duration-300 bg-white/90 dark:bg-brand-pureBlack/95 backdrop-blur-md border-b border-slate-200 dark:border-brand-gold/25">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-9 h-9 rounded-full border border-brand-gold/60 flex items-center justify-center font-serif text-sm font-semibold tracking-wider text-brand-gold">CM</div>
                <div class="text-xl font-bold tracking-[0.2em] font-sans">
                    <span class="text-brand-goldDark dark:text-brand-goldLight">CAROLINA</span> 
                    <span class="text-slate-950 dark:text-white font-light">MORA</span>
                </div>
            </div>
            <div class="hidden md:flex space-x-10 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-slate-200">
                <a href="#inicio" class="hover:text-brand-gold transition duration-300">Inicio</a>
                <a href="#servicios" class="hover:text-brand-gold transition duration-300">Servicios</a>
                <a href="#faq" class="hover:text-brand-gold transition duration-300">Consultas</a>
                <a href="#equipo" class="hover:text-brand-gold transition duration-300">Equipo</a>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Zona de Autenticación Dinámica -->
                <div id="auth-zone" class="flex items-center">
                    <a href="login" class="p-2 text-slate-700 dark:text-slate-300 hover:text-brand-gold transition-colors duration-300" title="Acceso Personal / Clientes">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    </a>
                </div>
                <!-- Advanced Toggle Mode Button -->
                <button id="theme-toggle" onclick="toggleTheme()" class="p-2.5 rounded-full border border-slate-300 dark:border-brand-gold/30 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition duration-300" aria-label="Cambiar Tema">
                    <!-- Sun Icon (Visible in Dark Mode) -->
                    <svg id="sun-icon" class="w-5 h-5 text-brand-goldLight hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9h-1m14.072-4.072l-.707.707M6.343 17.657l-.707.707M16.243 17.657l.707.707M6.343 6.343l.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                    </svg>
                    <!-- Moon Icon (Visible in Light Mode) -->
                    <svg id="moon-icon" class="w-5 h-5 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
                <a href="https://wa.me/573218915292" target="_blank" class="bg-gradient-to-r from-brand-goldDark to-brand-gold text-white px-5 py-2.5 rounded-sm text-[10px] font-bold uppercase tracking-widest shadow-lg hover:brightness-110 transition duration-300">Agendar Cita</a>
            </div>
        </div>
    </nav>

    <!-- Contenido Modular (Partials) -->
    <?php
    include __DIR__ . '/hero.php';
    include __DIR__ . '/services.php';
    include __DIR__ . '/faq.php';
    include __DIR__ . '/team.php';
    include __DIR__ . '/stats.php';
    include __DIR__ . '/cta.php';
    ?>

    <footer class="py-12 bg-slate-100 dark:bg-black border-t border-slate-300 dark:border-slate-900 text-slate-700 dark:text-slate-400">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center text-[10px] tracking-widest uppercase font-bold">
            <div>CAROLINA MORA &copy; <span id="current-year">2026</span> | Llorente, Nariño</div>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="https://www.instagram.com/carolinamora_estetica/" target="_blank" class="hover:text-brand-gold">Instagram</a>
                <a href="https://wa.me/573218915292" class="hover:text-brand-gold">WhatsApp</a>
            </div>
        </div>
    </footer>

    <script>
        /**
         * MOTOR DE DATOS DE SERVICIOS (Skill 1)
         * Centralizamos la información para que sea fácil de mantener.
         */
        const servicesData = {
            micropigmentacion: {
                title: 'Micropigmentación Profesional',
                desc: 'Diseños de alta gama para cejas, labios y ojos. Resultados hiper-realistas utilizando pigmentos orgánicos certificados.',
                freq: '12 - 18 Meses',
                time: '120 Minutos',
                list: ['Microblading & Cejas Híbridas', 'Labios Aquarelle / Neutralización', 'Eyeliner Clásico & Shaded'],
                img: 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                cta: 'https://wa.me/573218915292?text=Hola,%20deseo%20una%20valoración%20para%20Micropigmentación'
            },
            pestanas: {
                title: 'Pestañas & Mirada',
                desc: 'Técnicas de extensión pelo a pelo y volumen ruso para una mirada cautivadora sin perder la elegancia.',
                freq: '15 - 20 Días',
                time: '90 Minutos',
                list: ['Volumen Ruso & Megavolumen', 'Lifting de Pestañas con Queratina', 'Diseño de Cejas con Henna'],
                img: 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                cta: 'https://wa.me/573218915292?text=Hola,%20quiero%20agendar%20cita%20de%20Pestañas'
            },
            tratamientos: {
                title: 'Tratamientos Faciales Avanzados',
                desc: 'Protocolos personalizados para la salud de tu piel. Limpieza profunda y rejuvenecimiento con aparatología de punta.',
                freq: '28 Días',
                time: '60 Minutos',
                list: ['Limpieza Facial Profunda', 'Dermapen + Sueros de Vitamina', 'Peeling Químico Controlado'],
                img: 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                cta: 'https://wa.me/573218915292?text=Hola,%20necesito%20una%20limpieza%20facial'
            },
            depilacion: {
                title: 'Depilación Especializada',
                desc: 'Eliminación de vello con ceras elásticas de baja temperatura, ideales para zonas sensibles y pieles delicadas.',
                freq: '25 - 30 Días',
                time: '30 Minutos',
                list: ['Cuerpo Completo', 'Perfilado de Cejas', 'Zonas Sensibles (Bikini)'],
                img: 'https://images.unsplash.com/photo-1596755389378-c31d21fd1273?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                cta: 'https://wa.me/573218915292?text=Hola,%20solicito%20cita%20para%20depilación'
            },
            cabello: {
                title: 'Cuidado Capilar de Autor',
                desc: 'Tratamientos de hidratación profunda y restauración de la fibra capilar para un brillo y salud incomparables.',
                freq: '1 - 2 Meses',
                time: '120 Minutos',
                list: ['Keratinas Orgánicas', 'Bótox Capilar Profundo', 'Tratamientos de Sellado Térmico'],
                img: 'https://images.unsplash.com/photo-1562322140-8baeececf3df?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                cta: 'https://wa.me/573218915292?text=Hola,%20me%20interesa%20un%20tratamiento%20capilar'
            }
        };

        /**
         * INTERACTIVIDAD DE SERVICIOS
         * Actualiza el panel dinámico con animaciones de transición.
         */
        function updateService(type) {
            const data = servicesData[type];
            if (!data) return;

            const panel = document.getElementById('service-panel');
            if (!panel) return;

            // Efecto de salida
            panel.style.opacity = '0';
            panel.style.transform = 'translateY(10px)';

            setTimeout(() => {
                document.getElementById('s-title').innerText = data.title;
                document.getElementById('s-desc').innerText = data.desc;
                document.getElementById('s-freq').innerText = data.freq;
                document.getElementById('s-time').innerText = data.time;
                document.getElementById('s-img').src = data.img;
                document.getElementById('s-cta').href = data.cta;

                const list = document.getElementById('s-list');
                list.innerHTML = '';
                data.list.forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center space-x-3 text-sm text-slate-700 dark:text-slate-300';
                    li.innerHTML = `<span class="text-brand-gold">✔</span><span>${item}</span>`;
                    list.appendChild(li);
                });

                // Actualizar estado activo de las pestañas
                document.querySelectorAll('.service-tab').forEach(btn => {
                    btn.classList.remove('border-brand-gold');
                    btn.classList.add('border-transparent');
                });
                const activeBtn = document.getElementById(`btn-${type}`);
                if (activeBtn) activeBtn.classList.add('border-brand-gold');

                // Efecto de entrada
                panel.style.opacity = '1';
                panel.style.transform = 'translateY(0)';
            }, 300);
        }

        /**
         * MOTOR DE GRÁFICOS (Skill 1)
         */
        let luxuryChartInstance = null;
        function renderLuxuryChart(isDarkMode) {
            const canvas = document.getElementById('luxuryChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            if (luxuryChartInstance) luxuryChartInstance.destroy();

            const labelColor = isDarkMode ? '#CBD5E1' : '#0F172A';
            const gridColor = isDarkMode ? 'rgba(212, 175, 55, 0.15)' : 'rgba(15, 23, 42, 0.08)';

            luxuryChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Humedad', 'Exposición Solar', 'Post-Lavado'],
                    datasets: [
                        {
                            label: 'Convencional',
                            data: [35, 15, 10],
                            backgroundColor: isDarkMode ? '#334155' : '#CBD5E1',
                            borderColor: isDarkMode ? '#475569' : '#94A3B8',
                            borderWidth: 1
                        },
                        {
                            label: 'Autor',
                            data: [100, 95, 98],
                            backgroundColor: '#D4AF37',
                            borderColor: '#B8860B',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 110,
                            ticks: { color: labelColor, font: { family: 'Montserrat', size: 9 } },
                            grid: { color: gridColor }
                        },
                        x: {
                            ticks: { color: labelColor, font: { family: 'Montserrat', size: 9 } },
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { color: labelColor, font: { family: 'Montserrat', size: 10, weight: '600' } }
                        }
                    }
                }
            });
        }

        // Lógica de FAQ
        function toggleFaq(id) {
            const answer = document.getElementById(`faq-${id}`);
            const icon = document.getElementById(`icon-${id}`);
            if (answer.classList.contains('hidden')) {
                answer.classList.remove('hidden');
                icon.innerText = '−';
            } else {
                answer.classList.add('hidden');
                icon.innerText = '+';
            }
        }

        document.getElementById('current-year').innerText = new Date().getFullYear();
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            document.getElementById('sun-icon').classList.toggle('hidden', !isDark);
            document.getElementById('moon-icon').classList.toggle('hidden', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            if (typeof renderLuxuryChart === 'function') renderLuxuryChart(isDark);
        }
        function scrollToSection(id) { 
            const element = document.getElementById(id);
            if(element) element.scrollIntoView({ behavior: 'smooth' }); 
        }

        /**
         * Obtiene la base del proyecto desde la URL actual.
         * Detecta el subdirectorio de instalación (e.g. '/CarolinaMoraEstetica')
         * examinando el pathname. Funciona tanto con acceso directo a la raíz
         * (http://localhost/CarolinaMoraEstetica/) como vía /public/.
         *
         * @returns {string} Base path sin trailing slash (e.g. '/CarolinaMoraEstetica')
         */
        function getProjectBase() {
            const segments = window.location.pathname.split('/').filter(Boolean);
            // Si la URL contiene 'public', todo antes de 'public' es el project root.
            const pubIdx = segments.indexOf('public');
            if (pubIdx > 0) {
                return '/' + segments.slice(0, pubIdx).join('/');
            }
            // Sin 'public': el primer segmento es el nombre del proyecto.
            // Ejemplo: /CarolinaMoraEstetica/  → '/CarolinaMoraEstetica'
            // Ejemplo: /CarolinaMoraEstetica/login → '/CarolinaMoraEstetica'
            return segments.length > 0 ? '/' + segments[0] : '';
        }

        /**
         * Limpieza de Sesión:
         * Borra localStorage y redirige a la raíz pública.
         */
        function logout() {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_role');
            window.location.href = getProjectBase() + '/login';
        }
        
        window.onload = function() {
            // Gestión de UI según sesión
            const token = localStorage.getItem('auth_token');
            const role = localStorage.getItem('user_role');
            const authZone = document.getElementById('auth-zone');

            if (token && role && authZone) {
                const base = getProjectBase();
                
                const target = (role === 'SUPER_ADMIN' || role === 'BRANCH_ADMIN' || role === 'RECEPCIONIST')
                    ? '/admin/dashboard' : '/app/dashboard';

                const fullPath = base + target;

                authZone.innerHTML = `
                    <div class="flex items-center space-x-4">
                        <a href="${fullPath}" class="text-[10px] font-bold uppercase tracking-widest text-brand-gold hover:underline">
                            Mi Panel
                        </a>
                        <button onclick="logout()" class="text-slate-500 hover:text-red-500 transition-colors" title="Cerrar Sesión">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        </button>
                    </div>
                `;
            }

            // Gestión de Tema (Modo Claro/Oscuro) - Sincronizado con Plantilla
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            const isDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
            
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.getElementById('sun-icon').classList.remove('hidden');
                document.getElementById('moon-icon').classList.add('hidden');
            } else {
                document.documentElement.classList.remove('dark');
                document.getElementById('sun-icon').classList.add('hidden');
                document.getElementById('moon-icon').classList.remove('hidden');
            }

            // Inicializar servicio por defecto si existe la función
            if (typeof updateService === 'function') updateService('micropigmentacion');

            if (typeof renderLuxuryChart === 'function') renderLuxuryChart(isDark);
        }
    </script>
</body>
</html>
