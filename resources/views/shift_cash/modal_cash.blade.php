<div class="grid gap-5 sm:grid-cols-2">
    {{-- Columna 1 --}}
    <div class="space-y-2 p-5">
        <div class="flex items-center gap-2">
            <input type="checkbox" name="all_options" id="all_options" onchange="toggleAllShiftPdfOptions()">
            <label for="all_options">Todos</label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[sales_payments_summary]" value="1" id="sales_payments_summary">
            <label for="sales_payments_summary">
                Resúmenes - Ventas pagadas
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[products_sold_summary]" value="1" id="products_sold_summary">
            <label for="products_sold_summary">
                Consolidado de productos vendidos
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[cancellations_products]" value="1" id="cancellations_products">
            <label for="cancellations_products">
                Anulaciones de productos
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[expenses_by_payment_method_paid]" value="1" id="expenses_by_payment_method_paid">
            <label for="expenses_by_payment_method_paid">
                Egresos (gastos) por método de pago - pagados
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[discounts_by_product]" value="1" id="discounts_by_product">
            <label for="discounts_by_product">
                Descuentos por producto
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[debts_sales]" value="1" id="debts_sales">
            <label for="debts_sales">
                Ventas adeudadas
            </label>
        </div>
    </div>

    {{-- Columna 2 --}}
    <div class="space-y-2 p-5">
        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[paid_sales_by_method]" value="1" id="paid_sales_by_method">
            <label for="paid_sales_by_method">
                Ventas por métodos de pago - pagadas
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[sales_details_by_product]" value="1" id="sales_details_by_product">
            <label for="sales_details_by_product">
                Detalles de venta - por producto
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[sales_cancellations]" value="1" id="sales_cancellations">
            <label for="sales_cancellations">
                Anulaciones de ventas
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[cancellations_history]" value="1" id="cancellations_history">
            <label for="cancellations_history">
                Histórico de anulaciones
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[income_by_payment_method_paid]" value="1" id="income_by_payment_method_paid">
            <label for="income_by_payment_method_paid">
                Ingresos por método de pago - pagados
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[discounts_by_person]" value="1" id="discounts_by_person">
            <label for="discounts_by_person">
                Descuentos - por persona
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[courtesies]" value="1" id="courtesies">
            <label for="courtesies">
                Cortesías
            </label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="options[debts_sales_summary]" value="1" id="debts_sales_summary">
            <label for="debts_sales_summary">
                Resúmenes - ventas adeudadas
            </label>
        </div>
    </div>
</div>
<script>
    function toggleAllShiftPdfOptions() {
        const allOptions = document.getElementById('all_options');
        const options = document.querySelectorAll('input[type="checkbox"][name^="options["]');
        options.forEach((option) => {
            option.checked = allOptions.checked;
        });
    }
</script>
