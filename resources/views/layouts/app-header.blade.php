<header style="min-height: 55px; background: linear-gradient(150deg, #124731 0%, #0A2E1F 100%) !important;"
    class="sticky top-0 flex w-full z-99999 dark:border-gray-800 dark:bg-gray-900" x-data="{
        isApplicationMenuOpen: false,
        toggleApplicationMenu() {
            this.isApplicationMenuOpen = !this.isApplicationMenuOpen;
        }
    }">
    <div class="flex flex-col items-center justify-between grow xl:flex-row xl:px-6">
        <div
            class="flex items-center justify-between w-full gap-2 px-3 py-3 dark:border-gray-800 sm:gap-4 xl:justify-normal xl:border-b-0 xl:px-0 lg:py-4">

            <!-- Desktop Sidebar Toggle Button (oculto para Mozo) -->
            @if(($showSidebar ?? true) && !($isMozo ?? false))
                <button
                    class="hidden xl:flex items-center justify-center w-10 h-10 text-white border border-white/10 rounded-xl hover:bg-white/10 hover:text-white transition-all duration-200 lg:h-11 lg:w-11"
                    :class="{ 'bg-white/10 text-white': !$store.sidebar.isExpanded }"
                    @click="$store.sidebar.toggleExpanded()" aria-label="Contraer sidebar">
                    <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                            fill="white"></path>
                    </svg>
                    <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                            fill="" />
                    </svg>
                </button>
            @endif

            @php
                $branchName = null;
                if (session()->has('branch_id')) {
                    $branchName = optional(\App\Models\Branch::find(session('branch_id')))->legal_name;
                }
            @endphp

            <!-- Mobile Menu Toggle Button (oculto para Mozo) -->
            @if(($showSidebar ?? true) && !($isMozo ?? false))
                <button
                    class="flex xl:hidden items-center justify-center w-10 h-10 text-white rounded-lg hover:bg-white/10 hover:text-white transition-all duration-200 lg:h-11 lg:w-11"
                    :class="{ 'bg-white/10 text-white': $store.sidebar.isMobileOpen }"
                    @click="$store.sidebar.toggleMobileOpen()" aria-label="Toggle Mobile Menu">
                    <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                            fill="white"></path>
                    </svg>
                    <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                            fill="" />
                    </svg>
                </button>
            @endif

            @if ($branchName)
                <h1 class="ml-2 inline-flex items-center text-lg font-bold text-white tracking-tight">
                    {{ $branchName }}
                </h1>
            @endif

            <!-- Logo (mobile only) -->
            <a href="/" class="xl:hidden">
                <img class="brightness-0 invert opacity-90" src="/images/logo/Xinergia.png" alt="Logo" width="130"
                    height="35" />
            </a>

            <!-- Application Menu Toggle -->
            <button @click="toggleApplicationMenu()"
                class="flex items-center justify-center w-10 h-10 text-white rounded-lg z-99999 hover:bg-white/10 hover:text-white transition-all xl:hidden">
                <!-- Dots Icon -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99902 10.4951C6.82745 10.4951 7.49902 11.1667 7.49902 11.9951V12.0051C7.49902 12.8335 6.82745 13.5051 5.99902 13.5051C5.1706 13.5051 4.49902 12.8335 4.49902 12.0051V11.9951C4.49902 11.1667 5.1706 10.4951 5.99902 10.4951ZM17.999 10.4951C18.8275 10.4951 19.499 11.1667 19.499 11.9951V12.0051C19.499 12.8335 18.8275 13.5051 17.999 13.5051C17.1706 13.5051 16.499 12.8335 16.499 12.0051V11.9951C16.499 11.1667 17.1706 10.4951 17.999 10.4951ZM13.499 11.9951C13.499 11.1667 12.8275 10.4951 11.999 10.4951C11.1706 10.4951 10.499 11.1667 10.499 11.9951V12.0051C10.499 12.8335 11.1706 13.5051 11.999 13.5051C12.8275 13.5051 13.499 12.8335 13.499 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </button>

        </div>

        <!-- Application Menu (mobile) and Right Side Actions (desktop) -->
        <div :class="isApplicationMenuOpen ? 'flex' : 'hidden'"
            class="items-center justify-between w-full gap-4 px-5 py-4 xl:flex shadow-theme-md xl:justify-end xl:px-0 xl:shadow-none border-t border-white/5 xl:border-0 max-xl:bg-[linear-gradient(150deg,#124731_0%,#0A2E1F_100%)]">
            <div class="flex items-center gap-2 2xsm:gap-3">
                @if (!($isMozo ?? false) && !empty($quickOptions) && $quickOptions->count())
                    <div class="hidden xl:flex items-center gap-3">
                        @php
                            $quickNewSaleUrl = \App\Helpers\MenuHelper::appendViewIdToPath(route('sales.create'), request('view_id'));
                        @endphp
                        <a href="{{ $quickNewSaleUrl }}"
                            class="relative flex items-center justify-center transition-all bg-white/5 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white group"
                            aria-label="Nueva venta">
                            <i class="ri-cash-line text-lg text-white"></i>
                            <span
                                class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-white px-2 py-1 text-[10px] font-medium text-gray-900 opacity-0 shadow-lg transition-opacity group-hover:opacity-100 z-50">
                                Nueva venta
                            </span>
                        </a>
                        @foreach ($quickOptions as $option)
                            @php
                                $quickUrl = \App\Helpers\MenuHelper::appendViewIdToPath(route($option->action), $option->view_id);
                            @endphp
                            <a href="{{ $quickUrl }}"
                                class="relative flex items-center justify-center transition-all bg-white/5 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white group"
                                aria-label="{{ $option->name }}">
                                <i class="{{ $option->icon }} text-lg text-white"></i>
                                <span
                                    class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-white px-2 py-1 text-[10px] font-medium text-gray-900 opacity-0 shadow-lg transition-opacity group-hover:opacity-100 z-50">
                                    {{ $option->name }}
                                </span>
                            </a>
                        @endforeach

                        <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer"
                            class="relative flex items-center justify-center transition-all bg-white/5 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white group"
                            aria-label="Videos tutoriales">
                            <i class="ri-youtube-line text-lg text-white"></i>
                            <span
                                class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-white px-2 py-1 text-[10px] font-medium text-gray-900 opacity-0 shadow-lg transition-opacity group-hover:opacity-100 z-50">
                                Videos tutoriales
                            </span>
                        </a>
                    </div>
                @endif

                @if(($cashRegisterSelectionEnabled ?? true))
                    <div x-data="{ openCashModal: @js((bool) ($forceCashRegisterModal ?? false)), cashSelectionRequired: @js((bool) ($cashSelectionRequired ?? false)) }"
                        class="flex items-center gap-2">

                        <button type="button" @click="openCashModal = true" title="Cambiar Caja"
                            class="relative flex items-center justify-center text-white transition-all bg-white/5 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">

                            <i class="ri-safe-3-fill text-lg text-white"></i>
                        </button>

                        <div x-show="openCashModal" style="display: none;"
                            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
                            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

                            <div class="w-full max-w-md bg-white border border-gray-200 shadow-2xl rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                                @click.away="if (!cashSelectionRequired) openCashModal = false"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95">

                                <div
                                    class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                                        Seleccionar Caja
                                    </h3>
                                    <template x-if="!cashSelectionRequired">
                                        <button @click="openCashModal = false"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                                            <i class="ri-close-line text-xl"></i>
                                        </button>
                                    </template>
                                </div>

                                <div class="p-6">
                                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                                        @if(($cashSelectionRequired ?? false) || ($forceCashRegisterModal ?? false))
                                            Selecciona la caja de trabajo para esta sesión. Todo el sistema operará con esa caja
                                            hasta que la cambies.
                                        @else
                                            Seleccione la caja en la que desea operar actualmente.
                                        @endif
                                    </p>

                                    @if(!empty($selectedCashRegister))
                                        <div
                                            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                            Caja actual: <span class="font-semibold">{{ $selectedCashRegister->number }}</span>
                                        </div>
                                    @endif

                                    <div class="grid gap-3">
                                        @if(isset($cashRegisters) && $cashRegisters->count() > 0)
                                            @foreach($cashRegisters as $caja)
                                                                    @php
                                                                        $sessionRegisterId = session('cash_register_id');

                                                                        $isActive = $sessionRegisterId
                                                                            ? ($sessionRegisterId == $caja->id)
                                                                            : $loop->first;
                                                                    @endphp

                                                                    <form action="{{ route('caja.fijar') }}" method="POST">
                                                                        @csrf
                                                                        <input type="hidden" name="cash_register_id" value="{{ $caja->id }}">

                                                                        <button type="submit"
                                                                            class="group relative w-full flex items-center p-3 text-left border rounded-xl transition-all duration-200
                                                                                                                                                                                    {{ $isActive
                                                ? 'bg-[#124731]/10 border-brand-200 ring-1 ring-brand-500/20 dark:bg-[#124731]/20 dark:border-[#124731]/30'
                                                : 'bg-gray-50 border-transparent hover:bg-white hover:border-gray-200 hover:shadow-sm dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:border-gray-600' 
                                                                                                                                                                                    }}">

                                                                            <div
                                                                                class="flex items-center justify-center w-10 h-10 rounded-lg mr-4 
                                                                                                                                                                                        {{ $isActive ? 'bg-brand-100 text-[#0A2E1F] dark:bg-[#124731]/20 dark:text-[#124731]/80' : 'bg-white text-gray-400 shadow-sm group-hover:text-[#124731] dark:bg-gray-700 dark:text-gray-400' }}">
                                                                                <i class="ri-store-2-line text-lg"></i>
                                                                            </div>

                                                                            <div class="flex-1 ml-4">
                                                                                <h4
                                                                                    class="font-medium {{ $isActive ? 'text-brand-700 dark:text-[#124731]/80' : 'text-gray-800 dark:text-gray-200' }}">
                                                                                    {{ $caja->number }}
                                                                                </h4>
                                                                                @if($isActive)
                                                                                    <span class="text-xs text-[#124731] font-medium">● Activa
                                                                                        actualmente</span>
                                                                                @else
                                                                                    <span class="text-xs text-gray-400 group-hover:text-gray-500"> Click
                                                                                        para cambiar</span>
                                                                                @endif
                                                                            </div>

                                                                            @if($isActive)
                                                                                <div class="text-[#124731]">
                                                                                    <i class="ri-checkbox-circle-fill text-xl"></i>
                                                                                </div>
                                                                            @endif
                                                                        </button>
                                                                    </form>
                                            @endforeach
                                        @else
                                            <div class="text-center py-4 text-gray-500">
                                                No hay cajas registradas para esta sucursal.
                                            </div>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif


            </div>

            <!-- User Dropdown -->
            <x-header.user-dropdown class="text-white hover:text-white" />
        </div>
    </div>
</header>
