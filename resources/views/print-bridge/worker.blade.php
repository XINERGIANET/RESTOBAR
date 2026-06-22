@extends('layouts.app')

@push('head')
    <meta name="qz-sign-url" content="{{ route('qz.sign') }}">
    <meta name="qz-certificate-url" content="{{ route('qz.certificate') }}">
    <meta name="qz-signature-algorithm" content="{{ config('qz.signature_algorithm', 'SHA512') }}">
    <script>
        window.__qzSecondaryFirstPrinterNames = @json(config('qz.secondary_first_printer_names', []));
        window.__qzTertiaryFirstPrinterNames = @json(config('qz.tertiary_first_printer_names', []));
    </script>
    @vite(['resources/js/qz-tray-init.js'])
    <meta name="turbo-visit-control" content="no-cache">
@endpush

@section('content')
    <div class="p-4 max-w-xl mx-auto" data-turbo="false">
        <h1 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Puente de impresión (BARRA2 / QZ)</h1>
        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
            En la PC que tiene BARRA2 por USB y QZ Tray: puede dejar solo esta pestaña, o activar el puente para
            <strong>todas las pantallas</strong> del navegador (dashboard, pedidos, etc.) sin depender de esta URL.
        </p>
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" id="bridge-activate-all"
                class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-2">
                Activar escucha en todas las pantallas (esta PC)
            </button>
            <button type="button" id="bridge-deactivate-all"
                class="rounded-md border border-slate-300 dark:border-slate-600 text-sm px-3 py-2 text-slate-700 dark:text-slate-200">
                Desactivar escucha global
            </button>
        </div>
        <p class="text-xs text-slate-500 mb-2">Sucursal en sesión: <strong id="bridge-branch">—</strong> ·
            Impresora: <strong>{{ $targetPrinter }}</strong></p>
        <div id="bridge-status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/80 p-3 text-sm font-mono min-h-[3rem]">
            Iniciando…
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const targetPrinter = @json($targetPrinter);
            const pullBase = @json(route('print-bridge.pull'));
            const ackBase = @json(route('print-bridge.ack'));
            const st = document.getElementById('bridge-status');
            const bEl = document.getElementById('bridge-branch');
            if (bEl) bEl.textContent = @json((string) (session('branch_id') ?? ''));
            const btnAll = document.getElementById('bridge-activate-all');
            const btnOff = document.getElementById('bridge-deactivate-all');
            if (btnAll) {
                btnAll.addEventListener('click', function () {
                    try {
                        localStorage.setItem('xinergia_print_bridge_station', '1');
                        localStorage.setItem('xinergia_print_bridge_printer', targetPrinter);
                    } catch (e) {}
                    window.location.href = @json(url('/'));
                });
            }
            if (btnOff) {
                btnOff.addEventListener('click', function () {
                    try {
                        localStorage.removeItem('xinergia_print_bridge_station');
                        localStorage.removeItem('xinergia_print_bridge_printer');
                    } catch (e) {}
                    if (st) st.textContent = 'Escucha global desactivada. Recargue otras pestañas si hace falta.';
                });
            }

            function log(msg) {
                if (st) st.textContent = msg;
            }

            async function printJob(job) {
                const qzApi = window.qz;
                if (!qzApi) {
                    throw new Error('QZ Tray no está cargado en la página');
                }
                const name = String(job.printer_name || targetPrinter).trim() || 'BARRA2';
                if (typeof window.__qzApplyCertPairOverrideForPrinter === 'function') {
                    window.__qzApplyCertPairOverrideForPrinter(name);
                }
                if (typeof window.__qzConnectWithCertPairFallback === 'function') {
                    const ok = await window.__qzConnectWithCertPairFallback(qzApi, name);
                    if (!ok) {
                        throw new Error('No se pudo conectar QZ. Revise el certificado (qz2) en esta PC.');
                    }
                }
                const paperMm = 80;
                const config = qzApi.configs.create(name, {
                    units: 'mm',
                    size: { width: paperMm, height: 200 },
                    margins: 0,
                });
                const data = atob(String(job.b64 || ''));
                await qzApi.print(config, [{
                    type: 'raw',
                    format: 'command',
                    flavor: 'plain',
                    data: data
                }]);
            }

            async function ackJob(job) {
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const r = await fetch(ackBase, {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            printer_name: targetPrinter,
                            job_id: String(job.id || ''),
                        }),
                    });
                    if (!r.ok) {
                        const resp = await r.json().catch(() => null);
                        console.warn('[print-bridge] Ack failed:', r.status, resp?.message);
                        return false;
                    }
                    return true;
                } catch (e) {
                    console.warn('[print-bridge] Ack exception:', e.message);
                    return false;
                }
            }

            let busy = false;
            let currentJobId = null;
            let failureCount = 0;
            let lastAckFailTime = 0;
            
            async function tick() {
                if (busy) return;
                busy = true;
                try {
                    // Si hay fallos previos de ack, esperar 3s antes de reintentar
                    const now = Date.now();
                    if (failureCount > 0 && lastAckFailTime > 0) {
                        const elapsed = now - lastAckFailTime;
                        if (elapsed < 3000) {
                            const waitSecs = Math.ceil((3000 - elapsed) / 1000);
                            if (waitSecs > 0) {
                                log('Esperando ' + waitSecs + 's antes de reintentar ack...');
                                return;
                            }
                        }
                    }
                    
                    const u = new URL(pullBase, window.location.origin);
                    u.searchParams.set('printer_name', targetPrinter);
                    const r = await fetch(u.toString(), { 
                        credentials: 'same-origin', 
                        cache: 'no-store', 
                        headers: { 'Accept': 'application/json' } 
                    });
                    if (r.status === 401 || r.status === 419) {
                        log('Sesión caducada. Vuelva a iniciar sesión y abra esta página de nuevo.');
                        return;
                    }
                    const j = r.headers.get('content-type') && r.headers.get('content-type').includes('application/json')
                        ? await r.json() : null;
                    if (j && j.job && j.job.b64) {
                        const jobId = String(j.job.id || '').trim();
                        if (!jobId) {
                            log('Trabajo inválido recibido.');
                            return;
                        }
                        if (currentJobId && currentJobId !== jobId) {
                            log('Nuevo trabajo de impresión disponible.');
                            failureCount = 0;
                            lastAckFailTime = 0;
                        }
                        currentJobId = jobId;
                        log('Imprimiendo comanda / precuenta…');
                        await printJob(j.job);
                        failureCount = 0;
                        const ackOk = await ackJob(j.job);
                        if (!ackOk) {
                            failureCount++;
                            lastAckFailTime = Date.now();
                            if (failureCount >= 5) {
                                log('Fallo confirmación 5+ veces. Asumiendo impreso, reseteando...');
                                currentJobId = null;
                                failureCount = 0;
                                lastAckFailTime = 0;
                            } else {
                                log('Confirmación falló (intento ' + failureCount + '/5).');
                            }
                        } else {
                            currentJobId = null;
                            failureCount = 0;
                            lastAckFailTime = 0;
                            log('Listo. Esperando cola…');
                        }
                    } else {
                        log('En espera (cola vacía)… ' + new Date().toLocaleTimeString());
                    }
                } catch (e) {
                    console.error(e);
                    log('Error: ' + (e && e.message ? e.message : e));
                } finally {
                    busy = false;
                }
            }

            setInterval(tick, 1500);
            tick();
        })();
    </script>
@endpush
