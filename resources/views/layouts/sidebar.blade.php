@php
    use App\Helpers\MenuHelper;
    use App\Models\Profile;
    $menuGroups = MenuHelper::getMenuGroups();

    // Get current path
    $currentPath = request()->path();
    $profileName = null;
    if (auth()->check() && auth()->user()->profile_id) {
        $profileName = Profile::where('id', auth()->user()->profile_id)->value('name');
    }
@endphp

<aside id="sidebar"
    style="background: #ffffff"
    class="fixed flex flex-col mt-0 top-0 px-5 left-0 dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-99999 border-r border-gray-200"
    x-data="{
        openSubmenus: {},
        init() {
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (!empty($item['subItems']))
                        @foreach ($item['subItems'] as $subItem)
                            if (this.isActive({{ Illuminate\Support\Js::from($subItem['path'] ?? '') }})) {
                                this.openSubmenus[{{ Illuminate\Support\Js::from($groupIndex . '-' . $itemIndex) }}] = true;
                            }
                        @endforeach
                    @endif
                @endforeach
            @endforeach
        },
        keepSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            this.openSubmenus[key] = true;
            localStorage.setItem('sidebarOpenSubmenus', JSON.stringify(this.openSubmenus));
        },
        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            if (newState) {
                // Opcional: Cerrar otros al abrir uno nuevo
                // this.openSubmenus = {}; 
            }

            this.openSubmenus[key] = newState;
            localStorage.setItem('sidebarOpenSubmenus', JSON.stringify(this.openSubmenus));
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        normalizePath(path) {
            if (!path) return '';
            try {
                if (path.startsWith('http')) {
                    path = new URL(path).pathname;
                }
            } catch (e) {}
            path = path.split('?')[0].split('#')[0];
            const normalized = path.replace(/\/+$/, '');
            return normalized === '' ? '/' : normalized;
        },
        isActiveExact(path) {
            const current = this.normalizePath(window.location.pathname);
            const target = this.normalizePath(path);
            return current === target;
        },
        isActive(path) {
            const current = this.normalizePath(window.location.pathname);
            const target = this.normalizePath(path);
            if (target === '/') return current === '/';
            return current === target || current.startsWith(target + '/');
        }
    }"
    :class="{
        'w-[290px]': !$store.sidebar.isMozoUser && ($store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered),
        'w-[90px]': $store.sidebar.isMozoUser || !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isMozoUser && !$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">

    <div class="pt-8 pb-8 flex px-2"
        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
        'xl:justify-center' :
        'justify-center'">
        <a href="/" class="transition-opacity duration-300 hover:opacity-80">
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                class="dark:hidden" src="/images/logo/Xinergia.png" alt="Logo" width="140" height="36" />
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                class="hidden dark:block" src="/images/logo/Xinergia.png" alt="Logo" width="140"
                height="36" />
            <img x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                src="/images/logo/Xinergia-icon.png" alt="Logo" width="32" height="32" />
        </a>
    </div>

    <div class="flex-1 flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar px-1">
        <nav class="mb-8 mt-4">
            <div class="flex flex-col gap-6">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <h2 class="mb-3 px-4 text-[11px] font-bold uppercase tracking-widest flex leading-[20px] text-gray-400/80 dark:text-gray-500"
                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                            'lg:justify-center px-0' : 'justify-start'">
                            <template
                                x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                                <span>{{ $profileName ?? $menuGroup['title'] }}</span>
                            </template>
                            <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                                <span class="h-px w-6 bg-gray-200 dark:bg-gray-800"></span>
                            </template>
                        </h2>

                        <ul class="flex flex-col gap-1.5">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                <li>
                                    @if (!empty($item['subItems']))
                                        <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            class="menu-item group w-full"
                                            :class="[
                                                isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                            ]">

                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! $item['icon'] !!}
                                            </span>

                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ $item['name'] }}
                                            </span>

                                            <svg x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="ml-auto w-4 h-4 transition-transform duration-300 opacity-60"
                                                :class="{
                                                    'rotate-180 text-[#FF4622] opacity-100': isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})
                                                }"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 -translate-y-2"
                                             x-transition:enter-end="opacity-100 translate-y-0">
                                            <ul class="mt-1.5 space-y-1 ml-10 border-l border-gray-100 dark:border-gray-800/50 pl-2">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <li>
                                                        <a href="{{ $subItem['path'] }}" 
                                                            @click="keepSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})"
                                                            class="menu-dropdown-item group/sub"
                                                            :class="isActiveExact('{{ $subItem['path'] }}') ?
                                                                'menu-dropdown-item-active' :
                                                                'menu-dropdown-item-inactive'">
                                                            <span class="w-5 h-5 flex items-center justify-center opacity-70 group-hover/sub:opacity-100 transition-opacity">
                                                                {!! $subItem['icon'] ?? '' !!}
                                                            </span>
                                                            {{ $subItem['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>

                                    @else
                                        
                                        <a href="{{ $item['path'] }}" 
                                           class="menu-item group w-full"
                                           :class="[
                                                isActive('{{ $item['path'] }}') ? 'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                           ]">

                                            <span :class="isActive('{{ $item['path'] }}') ?
                                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! $item['icon'] !!}
                                            </span>

                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ $item['name'] }}
                                                
                                                @if (!empty($item['new']))
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-[#FF4622] text-white">new</span>
                                                @endif
                                            </span>
                                        </a>

                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <!-- Quick Access Section -->
        @if (!empty($quickOptions) && $quickOptions->count())
            <div class="mb-8 pb-44 px-1 xl:hidden">
                <h2 class="mb-3 px-4 text-[11px] font-bold uppercase tracking-widest flex leading-[20px] text-gray-400/80 dark:text-gray-500"
                    :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                    'lg:justify-center px-0' : 'justify-start'">
                    <template x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                        <span>ACCESOS RÁPIDOS</span>
                    </template>
                    <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                        <span class="h-px w-6 bg-gray-200 dark:bg-gray-800"></span>
                    </template>
                </h2>

                <ul class="flex flex-col gap-1.5">
                    @foreach ($quickOptions as $option)
                        @php
                            $quickUrl = \App\Helpers\MenuHelper::appendViewIdToPath(route($option->action), $option->view_id);
                        @endphp
                        <li>
                            <a href="{{ $quickUrl }}" 
                               class="menu-item group w-full menu-item-inactive"
                               :class="!$store.sidebar.isExpanded && !$store.sidebar.isHovered ? 'xl:justify-center' : 'xl:justify-start'">
                                <span class="menu-item-icon-inactive">
                                    <i class="{{ $option->icon }} text-lg"></i>
                                </span>
                                <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                      class="menu-item-text flex items-center gap-2">
                                    {{ $option->name }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Spacer for mobile scroll -->
        <div class="h-32 xl:hidden"></div>
    </div>
</aside>

<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)"
    class="fixed z-50 h-screen w-full bg-gray-900/50"></div>
