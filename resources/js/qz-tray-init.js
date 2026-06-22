import * as qz from 'qz-tray';

if (!window.qz) {
    window.qz = qz;
}
if (window.qz?.api?.showDebug) {
    window.qz.api.showDebug(false);
}

function getQz() {
    return window.qz || null;
}

function readMeta(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el?.getAttribute('content')?.trim() || '';
}

function qzCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function appendPairQuery(url, pair) {
    const u = new URL(url, window.location.href);
    if (pair === 'primary' || pair === 'secondary') {
        u.searchParams.set('pair', pair);
    } else {
        u.searchParams.delete('pair');
    }
    return u.toString();
}

const MULTI_KITCHEN_SECONDARY_FIRST = '__MULTI_KITCHEN_SECONDARY_FIRST__';

function matchesConfiguredSecondaryFirstList(printerName) {
    const list = Array.isArray(window.__qzSecondaryFirstPrinterNames) ? window.__qzSecondaryFirstPrinterNames : [];
    const raw = String(printerName || '').trim().toLowerCase();
    const compact = raw.replace(/\s+/g, '');
    if (!raw) {
        return false;
    }
    for (let i = 0; i < list.length; i++) {
        const e = String(list[i] || '').trim().toLowerCase();
        if (!e) {
            continue;
        }
        const ec = e.replace(/\s+/g, '');
        if (raw === e || compact === ec) {
            return true;
        }
        if (e.length >= 6 && raw.includes(e)) {
            return true;
        }
        if (ec.length >= 6 && compact.includes(ec)) {
            return true;
        }
    }

    return false;
}

/**
 * Misma regla que requiresStrictLocalQz en las vistas: ticketera de la 2.ª PC (LAN).
 * Para esas impresoras se usa primero el par secondary (app/qz2) y no se intenta primary antes.
 */
export function printerRequiresSecondaryCertFirst(printerName) {
    const raw = String(printerName || '').trim();
    if (raw === MULTI_KITCHEN_SECONDARY_FIRST) {
        return true;
    }
    const lower = raw.toLowerCase();
    if (!lower) {
        return false;
    }
    if (matchesConfiguredSecondaryFirstList(printerName)) {
        return true;
    }
    const compact = lower.replace(/\s+/g, '');
    if (compact === 'barra2' || compact.startsWith('barra2')) {
        return true;
    }
    if (/\bbarra\s*2\b/i.test(lower)) {
        return true;
    }
    if (lower.includes('barra2')) {
        return true;
    }

    return false;
}

export function applyQzCertPairOverrideForPrinter(printerName) {
    if (printerRequiresSecondaryCertFirst(printerName)) {
        window.__qzCertPairOrderOverride = ['secondary', 'primary'];
        if (String(printerName || '').trim() === MULTI_KITCHEN_SECONDARY_FIRST) {
            console.info('[QZ Xinergia] Comanda a varias ticketeras: orden secondary → primary (qz2 primero).');
        } else {
            console.info('[QZ Xinergia] Ticketera qz2 primero: orden secondary → primary (app/qz2 antes que primary).');
        }
    } else {
        window.__qzCertPairOrderOverride = null;
    }
}

export function resetQzTraySecurityState() {
    window.__qzTraySecurityConfigured = false;
    window.__qzActiveCertPair = null;
}

function resolveCertPairTryOrder() {
    const allowed = ['primary', 'secondary'];
    if (Array.isArray(window.__qzCertPairOrderOverride) && window.__qzCertPairOrderOverride.length > 0) {
        const order = [];
        const seen = new Set();
        for (const item of window.__qzCertPairOrderOverride) {
            const p = String(item || '').trim().toLowerCase();
            if (allowed.includes(p) && !seen.has(p)) {
                order.push(p);
                seen.add(p);
            }
        }
        for (const p of allowed) {
            if (!seen.has(p)) {
                order.push(p);
                seen.add(p);
            }
        }
        return order;
    }

    const cfg = window.__qzConfig || {};
    let raw = [];
    if (Array.isArray(cfg.certPairTryOrder)) {
        raw = cfg.certPairTryOrder;
    } else {
        raw = String(cfg.certPairTryOrder || 'primary,secondary')
            .split(',')
            .map((s) => s.trim().toLowerCase())
            .filter(Boolean);
    }
    const seen = new Set();
    const order = [];
    try {
        const pref = (window.localStorage?.getItem('qzCertPair') || '').trim().toLowerCase();
        if (pref === 'primary' || pref === 'secondary') {
            order.push(pref);
            seen.add(pref);
        }
    } catch (e) {
        // ignore
    }
    for (const item of raw) {
        const p = String(item || '').trim().toLowerCase();
        if (allowed.includes(p) && !seen.has(p)) {
            order.push(p);
            seen.add(p);
        }
    }
    if (order.length === 0) {
        return ['primary', 'secondary'];
    }
    return order;
}

/**
 * Configura certificado y firma para un par explícito (primary = app/qz, secondary = app/qz2).
 * @param {string} pair 'primary' | 'secondary'
 */
export function configureQzSecurityForPair(pair) {
    const qzLib = getQz();
    if (!qzLib) {
        return false;
    }

    const cfg = window.__qzConfig || {};
    const signBase = readMeta('qz-sign-url') || cfg.signUrl || '';
    const certBase = readMeta('qz-certificate-url') || cfg.certificateUrl || '';
    const algo = (readMeta('qz-signature-algorithm') || cfg.signatureAlgorithm || 'SHA512').toUpperCase();
    if (!signBase || !certBase) {
        return false;
    }

    const signUrl = appendPairQuery(signBase, pair);
    const certUrl = appendPairQuery(certBase, pair);

    resetQzTraySecurityState();

    qzLib.security.setCertificatePromise((resolve, reject) => {
        fetch(certUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'text/plain' },
        })
            .then((r) => (r.ok ? r.text() : Promise.reject(new Error(r.statusText))))
            .then(resolve)
            .catch(reject);
    });

    qzLib.security.setSignatureAlgorithm(algo);
    qzLib.security.setSignaturePromise((toSign) => {
        return (resolve, reject) => {
            const body = { request: toSign };
            fetch(signUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': qzCsrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            })
                .then(async (r) => {
                    const raw = await r.text();
                    if (!r.ok) {
                        throw new Error(raw || r.statusText);
                    }
                    let sig = '';
                    try {
                        const payload = raw ? JSON.parse(raw) : {};
                        sig = payload?.signature ? String(payload.signature) : '';
                    } catch (e) {
                        sig = String(raw || '').trim();
                    }
                    if (!sig) throw new Error('Firma QZ inválida');
                    return sig;
                })
                .then((sig) => resolve(sig))
                .catch(reject);
        };
    });

    window.__qzTraySecurityConfigured = true;
    window.__qzActiveCertPair = pair;
    return true;
}

/**
 * Intenta conectar a QZ Tray probando cada par de certificado en orden.
 * @param {object} qzApi instancia qz-tray
 * @param {string} [printerName] si es BARRA2, se fuerza orden secondary → primary sin conectar antes con primary.
 */
export async function connectQzWithCertPairFallback(qzApi, printerName) {
    if (!qzApi) {
        return false;
    }
    applyQzCertPairOverrideForPrinter(printerName);

    const needSecondaryFirst = printerRequiresSecondaryCertFirst(printerName);
    if (qzApi.websocket.isActive()) {
        if (needSecondaryFirst) {
            if (window.__qzSelectedCertPair === 'secondary') {
                return true;
            }
            console.warn('[QZ Xinergia] Sesión QZ activa pero no usa certificado secondary; desconectando para BARRA2.');
            try {
                await qzApi.websocket.disconnect();
            } catch (e) {
                console.warn('[QZ Xinergia] disconnect:', e);
            }
        } else {
            return true;
        }
    }

    const order = resolveCertPairTryOrder();
    console.info('[QZ Xinergia] Orden de pares:', order.join(' → '), printerName ? '(impresora: ' + printerName + ')' : '');
    let lastErr = null;
    for (let i = 0; i < order.length; i++) {
        const pair = order[i];
        try {
            configureQzSecurityForPair(pair);
            if (qzApi.websocket.isActive()) {
                try {
                    await qzApi.websocket.disconnect();
                } catch (e) {
                    console.warn('[QZ Xinergia] disconnect antes de reintentar:', e);
                }
            }
            await qzApi.websocket.connect();
            console.info('[QZ Xinergia] Conectado con par de certificado:', pair, '| orden probado:', order.join(' → '));
            window.__qzSelectedCertPair = pair;
            return true;
        } catch (e) {
            lastErr = e;
            console.warn('[QZ Xinergia] Falló conexión con par', pair, e);
            try {
                if (qzApi.websocket.isActive()) {
                    await qzApi.websocket.disconnect();
                }
            } catch (e2) {
                // ignore
            }
        }
    }
    console.error('[QZ Xinergia] No se pudo conectar con ningún par.', order.join(' → '), lastErr);
    return false;
}

/**
 * Compatibilidad: configura el primer par del orden (sin conectar).
 */
export function configureQzSecurity() {
    const first = resolveCertPairTryOrder()[0] || 'primary';
    return configureQzSecurityForPair(first);
}

function initQzIfMetaPresent() {
    const cfg = window.__qzConfig || {};
    const signUrl = readMeta('qz-sign-url') || cfg.signUrl || '';
    const certUrl = readMeta('qz-certificate-url') || cfg.certificateUrl || '';
    if (!signUrl || !certUrl) {
        return;
    }
    const qzLib = getQz();
    if (qzLib?.websocket?.isActive?.()) {
        return;
    }
    const defaultPrinterHint = String(cfg.defaultPrinterName || cfg.printerName || '').trim();
    applyQzCertPairOverrideForPrinter(defaultPrinterHint);
    configureQzSecurity();
}

window.__qzConnectWithCertPairFallback = connectQzWithCertPairFallback;
window.__qzConfigureQzSecurityForPair = configureQzSecurityForPair;
window.__qzResolveCertPairTryOrder = resolveCertPairTryOrder;
window.__qzApplyCertPairOverrideForPrinter = applyQzCertPairOverrideForPrinter;
window.__qzPrinterRequiresSecondaryCertFirst = printerRequiresSecondaryCertFirst;
window.__qzMultiKitchenSecondaryFirstToken = MULTI_KITCHEN_SECONDARY_FIRST;

initQzIfMetaPresent();

document.addEventListener('turbo:load', initQzIfMetaPresent);
