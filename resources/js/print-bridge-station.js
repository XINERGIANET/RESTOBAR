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
    if (!pullBase) {
        return;
    }
    window.__xinergiaPrintBridgePollStarted = true;

    const getPrinter = () => {
        try {
            return localStorage.getItem('xinergia_print_bridge_printer') || 'BARRA2';
        } catch (e) {
            return 'BARRA2';
        }
    };

    const tick = async () => {
        try {
            const u = new URL(pullBase, window.location.origin);
            u.searchParams.set('printer_name', getPrinter());
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
            const qzApi = window.qz;
            if (!qzApi) {
                return;
            }
            const name = String(j.job.printer_name || getPrinter()).trim() || 'BARRA2';
            if (typeof window.__qzConnectWithCertPairFallback === 'function') {
                const ok = await window.__qzConnectWithCertPairFallback(qzApi, name);
                if (!ok) {
                    return;
                }
            }
            const config = qzApi.configs.create(name, {
                units: 'mm',
                size: { width: 80, height: 200 },
                margins: 0,
            });
            const data = atob(String(j.job.b64));
            await qzApi.print(config, [{
                type: 'raw',
                format: 'command',
                flavor: 'plain',
                data,
            }]);

            if (j.job.id) {
                const ackUrl = pullBase.replace('/pull', '/ack');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const fd = new FormData();
                fd.append('printer_name', name);
                fd.append('job_id', j.job.id);
                
                await fetch(ackUrl, {
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
        }
    };

    window.__xinergiaPrintBridgeInterval = setInterval(tick, 1600);
    tick();
}
