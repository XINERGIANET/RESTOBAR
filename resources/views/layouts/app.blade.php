<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="turbo-cache-control" content="{{ $turboCacheControl ?? (session('status') || session('error') ? 'no-cache' : 'no-preview') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <script>
            window.__printBridgePullUrl = @json(route('print-bridge.pull'));
        </script>
    @endauth
    @php
        $qzDefaultPrinter = in_array(strtolower(request()->getHost()), ['localhost', '127.0.0.1', '::1']) ? 'BARRA' : 'BARRA2';
        $qzCertPairTryOrder = config('qz.cert_pair_try_order', ['primary', 'secondary', 'tertiary']);
    @endphp
    @stack('head')
    <script>
        window.__qzSecondaryFirstPrinterNames = @json(config('qz.secondary_first_printer_names', []));
        window.__qzTertiaryFirstPrinterNames = @json(config('qz.tertiary_first_printer_names', []));
        // Config global para QZ (usado por resources/js/qz-tray-init.js si no hay metas en la vista)
        window.__qzConfig = {
            certificateUrl: @json(route('qz.certificate')),
            signUrl: @json(route('qz.sign')),
            signatureAlgorithm: @json(config('qz.signature_algorithm', 'SHA512')),
            certPairTryOrder: @json($qzCertPairTryOrder),
            defaultPrinterName: @json($qzDefaultPrinter),
            printerName: @json($qzDefaultPrinter),
            printMode: @json(config('qz.print_mode', 'auto')),
            productionLock: @json((bool) config('qz.production_lock', false)),
            allowedOrigins: @json(config('qz.allowed_origins', [])),
        };
    </script>

    <title>{{ $title ?? 'Dashboard' }} | Xinergia FOOD</title>

    <!-- Scripts -->
    <script>
        window.crudModal = function (el) {
            const parseJson = (value, fallback) => {
                if (value === undefined || value === null || value === '') {
                    return fallback;
                }
                try {
                    return JSON.parse(value);
                } catch (error) {
                    return fallback;
                }
            };

            const initialForm = parseJson(el?.dataset?.form, {
                id: null,
                tax_id: '',
                legal_name: '',
                address: '',
            });

            return {
                open: parseJson(el?.dataset?.open, false),
                mode: parseJson(el?.dataset?.mode, 'create'),
                form: initialForm,
                createUrl: parseJson(el?.dataset?.createUrl, ''),
                updateBaseUrl: parseJson(el?.dataset?.updateBaseUrl, ''),
                get formAction() {
                    return this.mode === 'create' ? this.createUrl : `${this.updateBaseUrl}/${this.form.id}`;
                },
                openCreate() {
                    this.mode = 'create';
                    this.form = { id: null, tax_id: '', legal_name: '', address: '' };
                    this.open = true;
                },
                openEdit(company) {
                    this.mode = 'edit';
                    this.form = {
                        id: company.id,
                        tax_id: company.tax_id || '',
                        legal_name: company.legal_name || '',
                        address: company.address || '',
                    };
                    this.open = true;
                },
            };
        };

        document.addEventListener('alpine:init', () => {
            if (window.Alpine && typeof window.crudModal === 'function') {
                Alpine.data('crudModal', (el) => window.crudModal(el));
            }
        });
    </script>
    <!-- jQuery (Global para DataTables y otros plugins) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" data-turbo-track="reload"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store (forzar modo claro) -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    this.theme = 'light';
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    // Modo oscuro deshabilitado: siempre permanecer en light
                    this.theme = 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    html.classList.remove('dark');
                    body.classList.remove('dark', 'bg-gray-900');
                }
            });

            Alpine.store('sidebar', {
                // Mozo: siempre contraído. Varios módulos: siempre expandido. Otros: localStorage
                isMozoUser: @json($isMozo ?? false),
                isExpanded: false,
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = false;
                    this.isHovered = false;
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    if (this.isMozoUser) return; // Mozo no puede abrir/cerrar menú móvil
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Mozo: no permitir hover. Otros: solo en desktop cuando está contraído
                    if (this.isMozoUser) return;
                    if (window.innerWidth >= 1280) {
                        this.isHovered = val;
                    }
                }
            });
        });

        // Sincronizar sidebar con el usuario actual en cada carga (incl. Turbo) para evitar estado viejo
        function syncSidebarFromBody() {
            const body = document.body;
            if (!body || !window.Alpine?.store?.('sidebar')) return;
            const isMozo = body.getAttribute('data-is-mozo') === 'true';
            const store = Alpine.store('sidebar');
            store.isMozoUser = isMozo;
            store.isMobileOpen = false;
            store.isExpanded = false;
        }
        document.addEventListener('turbo:load', syncSidebarFromBody);
        document.addEventListener('DOMContentLoaded', () => { setTimeout(syncSidebarFromBody, 0); });
    </script>

    <!-- Forzar modo claro en carga inicial -->
    <script>
        (function() {
            const root = document.documentElement;
            const applyBody = () => {
                const body = document.body;
                if (!body) return;
                body.classList.remove('dark', 'bg-gray-900');
            };

            root.classList.remove('dark');

            if (document.body) {
                applyBody();
            } else {
                document.addEventListener('DOMContentLoaded', applyBody, { once: true });
            }
        })();
    </script>
    
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.swal-on-top { z-index: 2147483647 !important; }
.swal2-container { z-index: 2147483647 !important; position: fixed !important; inset: 0 !important; pointer-events: none; }
.swal2-popup { pointer-events: all; }
</style>
<style>
.swal2-container.swal2-bottom-start { padding-left: 18rem !important; }
@media (max-width: 1280px) {
  .swal2-container.swal2-bottom-start { padding-left: 1rem !important; }
}
</style>
<style>

.swal2-container { position: fixed !important; inset: 0 !important; }
</style>
<style>
body.swal2-shown > .swal2-container { z-index: 999999 !important; }
body.swal2-shown .swal2-container { z-index: 999999 !important; }
</style>
<style>
body.swal2-shown .sidebar,
body.swal2-shown [class*='sidebar'] { z-index: 10 !important; }
</style><style>
body.swal2-shown .swal2-container { z-index: 1000000 !important; position: fixed !important; inset: 0 !important; }
body.swal2-shown 
body.swal2-shown #sidebar { z-index: 1 !important; }
</style></head>

<body class="min-h-screen flex flex-col"
    x-data="{ 'loaded': true }"
    data-is-mozo="@json($isMozo ?? false)"
    data-has-multiple-modules="@json($hasMultipleModules ?? false)"
    x-init="
    const isMozo = @json($isMozo ?? false);
    if ($store.sidebar) {
        $store.sidebar.isMozoUser = isMozo;
        $store.sidebar.isExpanded = false;
    }
    const checkMobile = () => {
        if (!$store.sidebar) return;
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isMozoUser = isMozo;
            $store.sidebar.isExpanded = false;
        }
    };
    if (window.__sidebarResizeHandler) {
        window.removeEventListener('resize', window.__sidebarResizeHandler);
    }
    window.__sidebarResizeHandler = checkMobile;
    window.addEventListener('resize', window.__sidebarResizeHandler);
    checkMobile();">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}
    <x-common.loading-overlay/>

    <div class="flex-1 flex flex-col xl:flex-row min-h-screen">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="flex-1 flex flex-col min-w-0 min-h-full transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': !$store.sidebar.isMozoUser && ($store.sidebar.isExpanded || $store.sidebar.isHovered),
                'xl:ml-[90px]': $store.sidebar.isMozoUser || (!$store.sidebar.isExpanded && !$store.sidebar.isHovered),
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            
            @php
                $routeName = Route::currentRouteName();
                $isPosStyle = in_array($routeName, ['orders.create', 'sales.create', 'sales.charge', 'orders.charge']);
            @endphp

            <main class="flex-1 w-full flex flex-col {{ $isPosStyle ? 'p-0 md:p-0' : 'p-4 md:p-6' }}">
                <div class="flex-1 min-w-0 w-full">
                    @yield('content')
                </div>
            </main>

            <footer class="shrink-0 border-t border-gray-100 bg-white px-6 py-2 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition-colors duration-300">
                <div class="flex items-center justify-center gap-2">
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium">Derechos de autor &copy; {{ date('Y') }}</span>
                        <span class="font-bold tracking-tight text-[#FF4622]">Xinergia</span>
                        <span class="hidden sm:inline opacity-40">&bull;</span>
                        <span class="hidden sm:inline">Todos los derechos reservados.</span>
                    </div>
                </div>
            </footer>
        </div>

    </div>

    @if (session('status'))
    <script>
    (function() {
        const showStatusToast = () => {
            const message = @json(session('status'));
            const key = 'toast:status';
            if (window.sessionStorage && sessionStorage.getItem(key) === message) {
                return;
            }
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: message,
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            }
            if (window.sessionStorage) {
                sessionStorage.setItem(key, message);
            }
        };
        document.addEventListener('DOMContentLoaded', showStatusToast, { once: true });
        document.addEventListener('turbo:load', showStatusToast);
    })();
    </script>
    @endif

    @php
        $errorMessage = session('error');
        if (empty($errorMessage) && isset($errors) && $errors->any()) {
            $errorMessage = $errors->first();
        }
    @endphp
    @if (!empty($errorMessage))
    <script>
    (function() {
        const showErrorToast = () => {
            const message = @json($errorMessage);
            const key = 'toast:error';
            if (window.sessionStorage && sessionStorage.getItem(key) === message) {
                return;
            }
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'error',
                    title: message,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
            if (window.sessionStorage) {
                sessionStorage.setItem(key, message);
            }
        };
        document.addEventListener('DOMContentLoaded', showErrorToast, { once: true });
        document.addEventListener('turbo:load', showErrorToast);
    })();
    </script>
    @endif
    <script>
        if (!window.__globalSwalDeleteHandler) {
            document.addEventListener('submit', (event) => {
                const form = event.target.closest('.js-swal-delete');
                if (!form) return;
                if (form.dataset.swalBound === 'true') return;
                event.preventDefault();
                if (!window.Swal) {
                    form.submit();
                    return;
                }
                const title = form.dataset.swalTitle || '¿Eliminar registro?';
                const text = form.dataset.swalText || 'Esta acción no se puede deshacer.';
                const icon = form.dataset.swalIcon || 'warning';
                const confirmText = form.dataset.swalConfirm || 'Sí, eliminar';
                const cancelText = form.dataset.swalCancel || 'Cancelar';
                const confirmColor = form.dataset.swalConfirmColor || '#ef4444';
                const cancelColor = form.dataset.swalCancelColor || '#6b7280';

                const isDark = document.documentElement.classList.contains('dark');
                Swal.fire({
                    title,
                    text,
                    icon,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: cancelText,
                    confirmButtonColor: confirmColor,
                    cancelButtonColor: cancelColor,
                    reverseButtons: true,
                    allowOutsideClick: false,
                    background: isDark ? '#111827' : '#ffffff',
                    color: isDark ? '#e5e7eb' : '#111827',
                    customClass: {
                        backdrop: 'swal-backdrop-blur',
                    },
                    didOpen: (popup) => {
                        popup.classList.toggle('swal-dark', isDark);
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (window.showLoadingModal) {
                            window.showLoadingModal();
                        }
                        form.dataset.swalBound = 'true';
                        form.submit();
                    }
                });
            });
            window.__globalSwalDeleteHandler = true;
        }
    </script>
    <style>
        .swal2-container.swal2-backdrop-show .swal-backdrop-blur {
            background-color: rgba(156, 163, 175, 0.5) !important;
            opacity: 1 !important;
            backdrop-filter: blur(32px) !important;
            -webkit-backdrop-filter: blur(32px) !important;
        }
    </style>
    @stack('scripts')
</body>

</html>
