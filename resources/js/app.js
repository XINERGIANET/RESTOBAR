import '@hotwired/turbo';
import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

// flatpickr (registrar locale en la instancia importada; el import de l10n/es puede correr antes de window.flatpickr)
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
if (!flatpickr.l10ns.es) {
    flatpickr.l10ns.es = Spanish;
}
flatpickr.localize(Spanish);
// FullCalendar
import { Calendar } from '@fullcalendar/core';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

const remixIconCatalog = {
    list: null,
    promise: null,
    error: null,
    source: 'https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css',
};

const resolveRemixIconSource = () => {
    const link = document.querySelector('link[href*="remixicon"]');
    if (link && link.href) {
        remixIconCatalog.source = link.href;
    }
};

const loadRemixIconList = () => {
    if (Array.isArray(remixIconCatalog.list)) {
        return Promise.resolve(remixIconCatalog.list);
    }
    if (remixIconCatalog.promise) {
        return remixIconCatalog.promise;
    }
    resolveRemixIconSource();
    remixIconCatalog.promise = fetch(remixIconCatalog.source)
        .then((response) => response.text())
        .then((css) => {
            const regex = /\.ri-([a-z0-9-]+)::?before/g;
            const icons = new Set();
            let match;
            while ((match = regex.exec(css)) !== null) {
                icons.add(`ri-${match[1]}`);
            }
            remixIconCatalog.list = Array.from(icons).sort();
            remixIconCatalog.error = null;
            return remixIconCatalog.list;
        })
        .catch((error) => {
            remixIconCatalog.list = [];
            remixIconCatalog.error = error || true;
            return remixIconCatalog.list;
        });
    return remixIconCatalog.promise;
};

window.iconPicker = function () {
    return {
        open: false,
        search: '',
        icons: [],
        loading: false,
        error: false,
        maxVisible: 180,
        selected: '',
        init() {
            if (this.$refs?.iconInput) {
                const current = this.$refs.iconInput.value || '';
                this.search = current;
                this.selected = current;
            }
        },
        loadIcons() {
            if (this.icons.length || this.loading) return;
            this.loading = true;
            this.error = false;
            loadRemixIconList()
                .then((icons) => {
                    this.icons = icons || [];
                    this.error = !!remixIconCatalog.error || this.icons.length === 0;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        get filteredIcons() {
            if (!this.search) return this.icons;
            const query = this.search.toLowerCase();
            return this.icons.filter((icon) => icon.includes(query));
        },
        get displayedIcons() {
            const icons = this.filteredIcons || [];
            if (!icons.length) return [];
            return icons.slice(0, this.maxVisible);
        },
        openDropdown() {
            this.open = true;
            this.loadIcons();
        },
        toggleDropdown() {
            if (this.open) {
                this.open = false;
                return;
            }
            this.openDropdown();
        },
        closeDropdown() {
            this.open = false;
        },
        select(icon) {
            this.selected = icon;
            this.search = icon;
            if (this.$refs?.iconInput) {
                this.$refs.iconInput.value = icon;
                this.$refs.iconInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
        },
        clear() {
            this.selected = '';
            this.search = '';
            if (this.$refs?.iconInput) {
                this.$refs.iconInput.value = '';
                this.$refs.iconInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
};

const loadingOverlay = (() => {
    let overlayEl = null;

    const getEl = () => {
        if (!overlayEl) {
            overlayEl = document.querySelector('[data-loading-overlay]');
        }
        return overlayEl;
    };

    const show = () => {
        const el = getEl();
        if (!el) return;
        el.classList.remove('hidden');
        el.setAttribute('aria-hidden', 'false');
    };

    const hide = () => {
        const el = getEl();
        if (!el) return;
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
    };

    return { show, hide };
})();

window.showLoadingModal = loadingOverlay.show;
window.hideLoadingModal = loadingOverlay.hide;

const bindGlobalLoadingOverlay = () => {
    if (!window.showLoadingModal || !window.hideLoadingModal) {
        return;
    }

    document.addEventListener('turbo:visit', () => {
        window.showLoadingModal();
    });

    const hideEvents = ['turbo:load', 'turbo:render', 'turbo:frame-load', 'turbo:frame-render'];
    hideEvents.forEach((eventName) => {
        document.addEventListener(eventName, () => {
            window.hideLoadingModal();
        });
    });
};

bindGlobalLoadingOverlay();

const shouldIgnoreLink = (link, event) => {
    if (!link) return true;
    if (link.closest('[data-no-loading]')) return true;
    if (link.hasAttribute('download')) return true;
    const href = link.getAttribute('href');
    if (!href) return true;
    if (href.startsWith('#')) return true;
    if (href.startsWith('javascript:')) return true;
    if (href.startsWith('mailto:') || href.startsWith('tel:')) return true;
    const target = link.getAttribute('target');
    if (target && target !== '_self') return true;
    if (event) {
        if (event.defaultPrevented) return true;
        if (event.button !== 0) return true;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return true;
    }
    return false;
};

const shouldIgnoreForm = (form, event) => {
    if (!form) return true;
    if (form.closest('[data-no-loading]')) return true;
    if (form.classList.contains('js-swal-delete')) return true;
    const target = form.getAttribute('target');
    if (target && target !== '_self') return true;
    if (event && event.defaultPrevented) return true;
    return false;
};

const bindLoadingOverlay = () => {
    if (window.__loadingOverlayBound) return;
    window.__loadingOverlayBound = true;

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (shouldIgnoreLink(link, event)) return;
        loadingOverlay.show();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (shouldIgnoreForm(form, event)) return;
        loadingOverlay.show();
    });

    document.addEventListener('pageshow', () => loadingOverlay.hide());

    if (window.Turbo) {
        document.addEventListener('turbo:visit', () => loadingOverlay.show());
        document.addEventListener('turbo:submit-start', () => loadingOverlay.show());
        document.addEventListener('turbo:submit-end', () => loadingOverlay.hide());
        document.addEventListener('turbo:render', () => loadingOverlay.hide());
        document.addEventListener('turbo:load', () => loadingOverlay.hide());
        document.addEventListener('turbo:before-cache', () => loadingOverlay.hide());
        document.addEventListener('turbo:frame-load', () => loadingOverlay.hide());
    } else {
        document.addEventListener('DOMContentLoaded', () => loadingOverlay.hide(), { once: true });
    }
};

bindLoadingOverlay();

const syncSelectAllState = (scope) => {
    if (!scope) return;
    const selectAll = scope.querySelector('input[data-select-all]');
    if (!selectAll) return;
    const items = Array.from(scope.querySelectorAll('input[data-select-item]'))
        .filter((input) => !input.disabled);
    if (items.length === 0) {
        selectAll.checked = false;
        return;
    }
    selectAll.checked = items.every((input) => input.checked);
};

const syncAllSelectAllStates = () => {
    document.querySelectorAll('[data-select-scope]').forEach(syncSelectAllState);
};

const bindSelectAllCheckboxes = () => {
    if (window.__selectAllBound) return;
    window.__selectAllBound = true;

    document.addEventListener('change', (event) => {
        const selectAll = event.target.closest('input[data-select-all]');
        if (selectAll) {
            const scope = selectAll.closest('[data-select-scope]') || selectAll.closest('form') || document;
            scope.querySelectorAll('input[data-select-item]').forEach((input) => {
                if (input.disabled) return;
                input.checked = selectAll.checked;
            });
            return;
        }

        const item = event.target.closest('input[data-select-item]');
        if (!item) return;
        const scope = item.closest('[data-select-scope]') || item.closest('form') || document;
        syncSelectAllState(scope);
    });

    document.addEventListener('turbo:load', syncAllSelectAllStates);
    document.addEventListener('turbo:render', syncAllSelectAllStates);
    document.addEventListener('DOMContentLoaded', syncAllSelectAllStates, { once: true });
};

bindSelectAllCheckboxes();

const productCreateModal = () => {
    const init = window.__productModalInit || {};
    const open = init.open === true || init.open === 'true';
    const type = typeof init.type === 'string' ? init.type : 'PRODUCT';
    return {
        open,
        selectedType: type,
        init() {
            this.$watch('open', (val) => {
                if (val && this.selectedType) {
                    this.$nextTick(() => {
                        const sel = document.querySelector('#create-product-form select[name="type"]');
                        if (sel) {
                            sel.value = this.selectedType;
                            sel.dispatchEvent(new Event('change'));
                        }
                    });
                }
            });
        },
    };
};
Alpine.data('productCreateModal', productCreateModal);
window.productCreateModal = productCreateModal;

Alpine.data('crudModal', (el) => {
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
});

if (window.Turbo) {
    window.Turbo.session.drive = true;
}

const bindSwalDelete = () => {
    document.querySelectorAll('.js-swal-delete').forEach((form) => {
        if (form.dataset.swalBound === 'true') return;
        form.dataset.swalBound = 'true';
        form.addEventListener('submit', (event) => {
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
            }).then((result) => {
                if (result.isConfirmed) {
                    if (window.showLoadingModal) {
                        window.showLoadingModal();
                    }
                    form.submit();
                }
            });
        });
    });
};

const initPage = () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }
    if (document.querySelector('#chartBalance')) {
        import('./components/chart/chart-balance').then(module => module.initChartBalance());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
};

let alpineBooted = false;

function tryStartPrintBridgeStation() {
    try {
        if (localStorage.getItem('xinergia_print_bridge_station') !== '1') {
            return;
        }
    } catch (e) {
        return;
    }
    import('./print-bridge-station.js').then((m) => {
        if (m && typeof m.startPrintBridgeStationPoll === 'function') {
            m.startPrintBridgeStationPoll();
        }
    }).catch(() => {});
}

const bootAlpine = () => {
    if (alpineBooted) {
        return;
    }
    Alpine.start();
    alpineBooted = true;
};

document.addEventListener('turbo:before-cache', () => {
    if (window.Alpine && alpineBooted) {
        Alpine.destroyTree(document.body);
    }
});

document.addEventListener('turbo:load', () => {
    if (window.Alpine) {
        if (!alpineBooted) {
            bootAlpine();
        } else {
            Alpine.initTree(document.body);
        }
    }
    initPage();
    bindSwalDelete();
    tryStartPrintBridgeStation();
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.Turbo) {
        return;
    }
    bootAlpine();
    initPage();
    bindSwalDelete();
    tryStartPrintBridgeStation();
});
