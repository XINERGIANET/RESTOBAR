<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
    @foreach($accounts as $key => $account)
    @php
        $theme = match($key) {
            'Ventas'   => ['color' => '#2979ff'],
            'Compras'  => ['color' => '#FE0000'],
            'Entradas' => ['color' => '#03B430'],
            'Salidas'  => ['color' => '#FFA500'],
            default    => ['color' => '#4b5563'],
        };
    @endphp
    <div class="rounded-2xl p-5 md:p-6 shadow-lg hover:shadow-xl transition-all duration-300 relative overflow-hidden group" style="background-color: {{ $theme['color'] }}">
                <!-- Decoration element -->
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition-all"></div>
        
        <div class="flex items-start justify-between relative z-10">
            <div class="flex items-center justify-center w-10 h-10 bg-white/20 backdrop-blur-md rounded-xl shadow-inner">
                @if($key == 'Ventas')
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a2 2 0 0 0 0 4h15a1 1 0 0 0 1-1v-4"></path><path d="M19 11v12"></path></svg>
                @elseif($key == 'Compras')
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="10" width="18" height="11" rx="2"></rect><path d="M3 10L12 3L21 10"></path><path d="M6 10V21"></path><path d="M10 10V21"></path><path d="M14 10V21"></path><path d="M18 10V21"></path></svg>
                @elseif($key == 'Entradas')
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                @elseif($key == 'Salidas')
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                @else
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                @endif
            </div>
            
            <span class="flex items-center gap-1 rounded-lg bg-white/20 text-white py-1 px-2.5 text-[10px] font-black uppercase tracking-widest backdrop-blur-sm">
                <svg width="8" height="8" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $account['diff'] < 0 ? 'rotate-180' : '' }}">
                    <path d="M5 2L8 5L2 5L5 2Z" fill="currentColor"/>
                </svg>
                {{ abs($account['diff']) }}%
            </span>
        </div>

        <div class="mt-6 relative z-10">
            <span class="text-[10px] font-black text-white/70 uppercase tracking-[0.2em]">
                {{ match($key) { 'caja' => 'CAJA PRINCIPAL', 'bcp' => 'BANCO BCP', 'interbank' => 'INTERBANK', 'wallet' => 'WALLET DIGITAL', default => strtoupper($key) } }}
            </span>
            <h4 class="mt-1 text-3xl font-black text-white tracking-tight">
                S/{{ number_format($account['total'], 2) }}
            </h4>
        </div>

        <div class="mt-6 flex items-center justify-between border-t border-white/10 pt-4 relative z-10">
            <span class="text-[10px] font-bold text-white/50 uppercase tracking-widest">Transacciones</span>
            <span class="text-xs font-black text-white bg-white/10 px-2 py-0.5 rounded-md">{{ number_format($account['transactions']) }}</span>
        </div>
    </div>
    @endforeach
</div>
