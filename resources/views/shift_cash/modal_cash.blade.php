<div class="px-6 py-2 space-y-5" x-data="{
    totalCount: 14,
    checkedCount: 14,
    updateCount() {
        this.checkedCount = document.querySelectorAll('input[type=checkbox][name^=\'options[\']:checked').length;
        const allChk = document.getElementById('all_options');
        if (allChk) {
            allChk.checked = (this.checkedCount === this.totalCount);
            allChk.indeterminate = (this.checkedCount > 0 && this.checkedCount < this.totalCount);
        }
    },
    toggleAll(checked) {
        document.querySelectorAll('input[type=checkbox][name^=\'options[\']').forEach(el => {
            el.checked = checked;
        });
        this.checkedCount = checked ? this.totalCount : 0;
    }
}" x-init="updateCount()">

    {{-- Master Select All Card --}}
    <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-900/10 via-emerald-800/5 to-transparent border border-emerald-500/20 dark:border-emerald-500/30 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#124731] text-white flex items-center justify-center shadow-sm">
                <i class="ri-checkbox-multiple-line text-xl"></i>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Seleccionar todas las secciones</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Incluir la totalidad de reportes en el documento PDF</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" x-text="`${checkedCount} / ${totalCount} seleccionados`">
                14 / 14 seleccionados
            </span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="all_options" checked @change="toggleAll($event.target.checked)" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-gray-600 peer-checked:bg-[#124731]"></div>
            </label>
        </div>
    </div>

    {{-- Categories & Cards Grid --}}
    <div class="grid gap-6 md:grid-cols-3">
        
        {{-- Section 1: Resúmenes General --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 pb-1 border-b border-gray-200 dark:border-gray-700/60">
                <i class="ri-bar-chart-2-fill text-emerald-600 dark:text-emerald-400 text-base"></i>
                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Resúmenes General</h5>
            </div>
            <div class="space-y-2">
                @php
                    $group1 = [
                        ['id' => 'sales_payments_summary', 'label' => 'Resúmenes - Ventas pagadas', 'icon' => 'ri-bar-chart-box-line'],
                        ['id' => 'products_sold_summary', 'label' => 'Consolidado de productos', 'icon' => 'ri-shopping-bag-3-line'],
                        ['id' => 'debts_sales', 'label' => 'Ventas adeudadas', 'icon' => 'ri-time-line'],
                        ['id' => 'debts_sales_summary', 'label' => 'Resúmenes - Ventas adeudadas', 'icon' => 'ri-file-chart-line'],
                    ];
                @endphp
                @foreach($group1 as $opt)
                    <label for="{{ $opt['id'] }}" class="relative flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-700/80 bg-white dark:bg-gray-800/80 transition-all duration-200 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-sm select-none cursor-pointer group has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 dark:has-[:checked]:bg-emerald-950/30 dark:has-[:checked]:border-emerald-500">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 group-has-[:checked]:bg-emerald-100 group-has-[:checked]:text-emerald-700 dark:group-has-[:checked]:bg-emerald-900/60 dark:group-has-[:checked]:text-emerald-300 transition-colors shrink-0">
                                <i class="{{ $opt['icon'] }} text-sm"></i>
                            </div>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-200 group-has-[:checked]:text-emerald-950 dark:group-has-[:checked]:text-emerald-200 leading-tight">
                                {{ $opt['label'] }}
                            </span>
                        </div>
                        <input type="checkbox" name="options[{{ $opt['id'] }}]" value="1" id="{{ $opt['id'] }}" checked @change="updateCount()" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 accent-[#124731] cursor-pointer shrink-0">
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Section 2: Métodos de Pago y Detalles --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 pb-1 border-b border-gray-200 dark:border-gray-700/60">
                <i class="ri-wallet-3-fill text-emerald-600 dark:text-emerald-400 text-base"></i>
                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Flujos y Métodos</h5>
            </div>
            <div class="space-y-2">
                @php
                    $group2 = [
                        ['id' => 'paid_sales_by_method', 'label' => 'Ventas por método de pago', 'icon' => 'ri-bank-card-line'],
                        ['id' => 'income_by_payment_method_paid', 'label' => 'Ingresos por método de pago', 'icon' => 'ri-hand-coin-line'],
                        ['id' => 'expenses_by_payment_method_paid', 'label' => 'Egresos por método de pago', 'icon' => 'ri-wallet-3-line'],
                        ['id' => 'sales_details_by_product', 'label' => 'Detalles de venta por producto', 'icon' => 'ri-file-list-3-line'],
                    ];
                @endphp
                @foreach($group2 as $opt)
                    <label for="{{ $opt['id'] }}" class="relative flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-700/80 bg-white dark:bg-gray-800/80 transition-all duration-200 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-sm select-none cursor-pointer group has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 dark:has-[:checked]:bg-emerald-950/30 dark:has-[:checked]:border-emerald-500">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 group-has-[:checked]:bg-emerald-100 group-has-[:checked]:text-emerald-700 dark:group-has-[:checked]:bg-emerald-900/60 dark:group-has-[:checked]:text-emerald-300 transition-colors shrink-0">
                                <i class="{{ $opt['icon'] }} text-sm"></i>
                            </div>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-200 group-has-[:checked]:text-emerald-950 dark:group-has-[:checked]:text-emerald-200 leading-tight">
                                {{ $opt['label'] }}
                            </span>
                        </div>
                        <input type="checkbox" name="options[{{ $opt['id'] }}]" value="1" id="{{ $opt['id'] }}" checked @change="updateCount()" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 accent-[#124731] cursor-pointer shrink-0">
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Section 3: Anulaciones, Descuentos y Cortesías --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 pb-1 border-b border-gray-200 dark:border-gray-700/60">
                <i class="ri-shield-flash-fill text-emerald-600 dark:text-emerald-400 text-base"></i>
                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Anulaciones y Ajustes</h5>
            </div>
            <div class="space-y-2">
                @php
                    $group3 = [
                        ['id' => 'cancellations_products', 'label' => 'Anulaciones de productos', 'icon' => 'ri-delete-bin-line'],
                        ['id' => 'sales_cancellations', 'label' => 'Anulaciones de ventas', 'icon' => 'ri-close-circle-line'],
                        ['id' => 'cancellations_history', 'label' => 'Histórico de anulaciones', 'icon' => 'ri-history-line'],
                        ['id' => 'discounts_by_product', 'label' => 'Descuentos por producto', 'icon' => 'ri-price-tag-3-line'],
                        ['id' => 'discounts_by_person', 'label' => 'Descuentos por persona', 'icon' => 'ri-user-unfollow-line'],
                        ['id' => 'courtesies', 'label' => 'Cortesías', 'icon' => 'ri-gift-line'],
                    ];
                @endphp
                @foreach($group3 as $opt)
                    <label for="{{ $opt['id'] }}" class="relative flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-700/80 bg-white dark:bg-gray-800/80 transition-all duration-200 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-sm select-none cursor-pointer group has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 dark:has-[:checked]:bg-emerald-950/30 dark:has-[:checked]:border-emerald-500">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 group-has-[:checked]:bg-emerald-100 group-has-[:checked]:text-emerald-700 dark:group-has-[:checked]:bg-emerald-900/60 dark:group-has-[:checked]:text-emerald-300 transition-colors shrink-0">
                                <i class="{{ $opt['icon'] }} text-sm"></i>
                            </div>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-200 group-has-[:checked]:text-emerald-950 dark:group-has-[:checked]:text-emerald-200 leading-tight">
                                {{ $opt['label'] }}
                            </span>
                        </div>
                        <input type="checkbox" name="options[{{ $opt['id'] }}]" value="1" id="{{ $opt['id'] }}" checked @change="updateCount()" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 accent-[#124731] cursor-pointer shrink-0">
                    </label>
                @endforeach
            </div>
        </div>

    </div>
</div>
