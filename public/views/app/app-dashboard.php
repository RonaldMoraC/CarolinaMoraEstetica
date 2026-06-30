<?php declare(strict_types=1);

$pageTitle = 'Servicios';
$activeSection = 'Catalog';

ob_start();
?>

<!-- ────── SECTION: CATALOGO DE SERVICIOS ────── -->
<div id="sectionCatalog" class="section-view visible">
    <!-- Search bar -->
    <div class="flex items-center bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl px-3 mb-4">
        <span class="text-slate-400 text-lg mr-2">🔍</span>
        <input type="text" id="searchService" placeholder="Buscar servicios..."
            class="app-input w-full py-3 bg-transparent text-slate-700 dark:text-slate-300 placeholder-slate-400">
    </div>

    <!-- Category filters (dynamic from API) -->
    <div class="grid grid-cols-2 gap-2 mb-4" id="categoryFilters"></div>

    <!-- Service grid -->
    <div class="flex flex-col gap-3" id="servicesGrid">
        <!-- Spinner will be injected by JS -->
    </div>
</div>

<!-- ────── SECTION: MIS CITAS ────── -->
<div id="sectionCitas" class="section-view hidden">
    <!-- Tabs: Próximas / Pasadas -->
    <div class="flex bg-slate-100 dark:bg-white/5 rounded-xl p-1 mb-4">
        <button class="tab-btn active flex-1 py-3 rounded-lg text-sm font-semibold text-slate-500 dark:text-slate-400"
            id="tabProximas">Próximas</button>
        <button class="tab-btn flex-1 py-3 rounded-lg text-sm font-semibold text-slate-500 dark:text-slate-400"
            id="tabPasadas">Pasadas</button>
    </div>

    <!-- Appointments container -->
    <div class="flex flex-col gap-3" id="appointmentsContainer">
        <!-- Spinner will be injected by JS -->
    </div>
</div>

<!-- ────── SECTION: MI PERFIL ────── -->
<div id="sectionPerfil" class="section-view hidden">
    <div class="bg-white dark:bg-white/5 rounded-2xl shadow-sm border border-slate-200 dark:border-white/10 p-6">
        <!-- Avatar -->
        <div class="text-center mb-6">
            <div class="w-20 h-20 rounded-full bg-brand-cyanLight flex items-center justify-center text-4xl mx-auto mb-3">👤</div>
            <h2 id="profileName" class="text-lg font-bold text-slate-900 dark:text-white">Cargando...</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold">Cliente Registrado</p>
        </div>

        <!-- Profile form -->
        <form id="profileForm" autocomplete="off">
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Nombres</label>
                <input type="text" id="profileFirstName" class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/15 transition"
                    placeholder="Sus nombres" required>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Apellidos</label>
                <input type="text" id="profileLastName" class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/15 transition"
                    placeholder="Sus apellidos" required>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Teléfono</label>
                <input type="tel" id="profileTelefono" class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/15 transition"
                    placeholder="Ingrese su teléfono">
            </div>
            <div class="mb-5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Correo Electrónico</label>
                <input type="email" id="profileCorreo" class="app-input w-full px-4 py-3 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-slate-700 dark:text-slate-300 focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/15 transition"
                    placeholder="Ingrese su correo electrónico" required>
            </div>
            <button type="submit" id="btnGuardarPerfil"
                class="w-full py-3 bg-brand-cyan text-white font-semibold rounded-xl hover:bg-brand-cyanHover transition transform hover:-translate-y-0.5">
                Guardar Cambios
            </button>
        </form>

        <!-- Logout -->
        <div class="mt-5 pt-4 border-t border-slate-200 dark:border-white/10">
            <button data-action="logout"
                class="w-full py-3 border-2 border-red-400 text-red-500 font-semibold rounded-xl hover:bg-red-500 hover:text-white transition">
                Cerrar Sesión
            </button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app-layout.php';
