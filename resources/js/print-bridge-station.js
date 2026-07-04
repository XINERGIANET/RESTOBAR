import './qz-tray-init.js';

/**
 * PC estación (BARRA2 USB + QZ/qz2): escucha la cola del servidor (móvil → Laravel → caché).
 * Activar una vez: localStorage xinergia_print_bridge_station = '1' (p. ej. desde /print-bridge/worker).
 */
export function startPrintBridgeStationPoll() {
    if (window.__xinergiaPrintBridgePollStarted) {
        return;
    }
    const pullBase = typeof window.__printBridgePullUrl === 'string' ? window.__printBridgePullUrl.trim() : '';
    const ackBase = typeof window.__printBridgeAckUrl === 'string' ? window.__printBridgeAckUrl.trim() : '';
    const failBase = typeof window.__printBridgeFailUrl === 'string' ? window.__printBridgeFailUrl.trim() : '';
    if (!pullBase) {
        return;
    }
    window.__xinergiaPrintBridgePollStarted = true;

    const getPrinter = () => {
        try {
            const stored = localStorage.getItem('xinergia_print_bridge_printer') || '*';
            return stored === '*' ? '' : stored;
        } catch (e) {
            return '';
        }
    };

    let busy = false;
    const tick = async () => {
        if (busy) return;
        busy = true;
        try {
            const u = new URL(pullBase, window.location.origin);
            const selectedPrinter = getPrinter();
            if (selectedPrinter) u.searchParams.set('printer_name', selectedPrinter);
            const r = await fetch(u.toString(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (r.status === 401 || r.status === 419) {
                return;
            }
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                return;
            }
            const j = await r.json();
            if (!j || !j.job || !j.job.b64) {
                return;
            }
            window.__xinergiaActivePrintBridgeJob = j.job;
            const qzApi = window.qz;
            if (!qzApi) {
                throw new Error('QZ Tray no está disponible en esta PC');
            }
            const name = String(j.job.printer_name || getPrinter()).trim();
            if (!name) throw new Error('El trabajo no indica una impresora de destino.');
            if (typeof window.__qzConnectWithCertPairFallback === 'function') {
                const ok = await window.__qzConnectWithCertPairFallback(qzApi, name);
                if (!ok) {
                    throw new Error('No se pudo conectar con QZ Tray');
                }
            }
            const config = qzApi.configs.create(name, {
                units: 'mm',
                size: { width: 80, height: 200 },
                margins: 0,
            });
            await qzApi.print(config, [{
                type: 'raw',
                format: 'command',
                flavor: 'base64',
                data: String(j.job.b64),
            }]);

            if (j.job.id) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const fd = new FormData();
                fd.append('printer_name', name);
                fd.append('job_id', j.job.id);
                
                await fetch(ackBase || pullBase.replace('/pull', '/ack'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: fd
                });
            }
        } catch (e) {
            console.warn('[print-bridge-station]', e);
            const job = window.__xinergiaActivePrintBridgeJob;
            if (job?.id && failBase) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                await fetch(failBase, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        printer_name: job.printer_name || getPrinter(),
                        job_id: job.id,
                        error: String(e?.message || e || 'Error de impresión en QZ'),
                    }),
                }).catch(() => null);
            }
        } finally {
            window.__xinergiaActivePrintBridgeJob = null;
            busy = false;
        }
    };

    window.__xinergiaPrintBridgeInterval = setInterval(tick, 1600);
    tick();
}
