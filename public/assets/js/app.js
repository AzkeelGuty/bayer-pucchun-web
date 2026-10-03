// Dashboard assets: mantener colores diferenciados y comportamiento visual vigente.
(() => {
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('appSidebar');

    const sidebarNav = document.getElementById('sidebarNav');
    if (sidebarNav) {
        const scrollKey = 'bp-sidebar-scroll:' + (sidebarNav.dataset.scrollKey || 'default');
        let scrollFrame = null;

        const saveSidebarScroll = () => {
            try { sessionStorage.setItem(scrollKey, String(sidebarNav.scrollTop)); } catch (_) {}
        };

        const restoreSidebarScroll = () => {
            let restored = false;
            try {
                const raw = sessionStorage.getItem(scrollKey);
                if (raw !== null) {
                    const saved = Number(raw);
                    if (Number.isFinite(saved) && saved >= 0) {
                        sidebarNav.scrollTop = saved;
                        restored = true;
                    }
                }
            } catch (_) {}

            if (!restored) {
                const active = sidebarNav.querySelector('.sidebar-link.active');
                if (active) active.scrollIntoView({block: 'nearest'});
            }
        };

        requestAnimationFrame(restoreSidebarScroll);
        sidebarNav.addEventListener('scroll', () => {
            if (scrollFrame) cancelAnimationFrame(scrollFrame);
            scrollFrame = requestAnimationFrame(saveSidebarScroll);
        }, {passive: true});
        sidebarNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', saveSidebarScroll));
        window.addEventListener('pagehide', saveSidebarScroll);
    }

    const setSidebar = (open) => {
        body.classList.toggle('sidebar-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        }
    };

    if (toggle) {
        toggle.addEventListener('click', () => setSidebar(!body.classList.contains('sidebar-open')));
    }

    if (backdrop) {
        backdrop.addEventListener('click', () => setSidebar(false));
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    setSidebar(false);
                }
            });
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebar(false);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 991.98) setSidebar(false);
    });

    document.querySelectorAll('.js-auto-dismiss').forEach((alert) => {
        window.setTimeout(() => {
            if (!alert.isConnected) return;
            if (window.bootstrap?.Alert) {
                window.bootstrap.Alert.getOrCreateInstance(alert).close();
            } else {
                alert.remove();
            }
        }, 4500);
    });

    const password = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    if (password && passwordToggle) {
        passwordToggle.addEventListener('click', () => {
            const show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            passwordToggle.textContent = show ? 'Ocultar' : 'Ver';
            passwordToggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            passwordToggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
            password.focus({preventScroll:true});
        });
    }

    const syncColor = (pickerId, inputId) => {
        const picker = document.getElementById(pickerId);
        const input = document.getElementById(inputId);
        if (!picker || !input) return;
        picker.addEventListener('input', () => { input.value = picker.value.toUpperCase(); });
        input.addEventListener('input', () => {
            if (/^#[0-9A-Fa-f]{6}$/.test(input.value)) picker.value = input.value;
        });
    };
    syncColor('primary_color_picker', 'primary_color');
    syncColor('accent_color_picker', 'accent_color');
    syncColor('sidebar_color_picker', 'sidebar_color');
    syncColor('background_color_picker', 'background_color');

    const brandingPreview = document.getElementById('brandingPreview');
    const brandingLoginPreview = document.getElementById('brandingLoginPreview');

    const readColor = (id, fallback) => {
        const input = document.getElementById(id);
        return input && /^#[0-9A-Fa-f]{6}$/.test(input.value) ? input.value.toUpperCase() : fallback;
    };

    const refreshBrandPreview = () => {
        if (!brandingPreview && !brandingLoginPreview) return;
        const primary = readColor('primary_color', '#075B9F');
        const accent = readColor('accent_color', '#168C5B');
        const sidebarColor = readColor('sidebar_color', '#0A2F55');
        const background = readColor('background_color', '#F4F7FB');

        [brandingPreview, brandingLoginPreview].filter(Boolean).forEach((preview) => {
            preview.style.setProperty('--preview-primary', primary);
            preview.style.setProperty('--preview-accent', accent);
            preview.style.setProperty('--preview-sidebar', sidebarColor);
            preview.style.setProperty('--preview-bg', background);
        });

        const theme = document.getElementById('sidebar_theme');
        if (brandingPreview && theme) {
            brandingPreview.classList.toggle('sidebar-light', theme.value === 'light');
            brandingPreview.classList.toggle('sidebar-dark', theme.value !== 'light');
        }
    };

    document.querySelectorAll('.brand-live-text').forEach((input) => {
        const target = document.getElementById(input.dataset.preview || '');
        if (!target) return;
        input.addEventListener('input', () => { target.textContent = input.value || '—'; });
    });

    ['primary_color','accent_color','sidebar_color','background_color'].forEach((id) => {
        const input = document.getElementById(id);
        const picker = document.getElementById(id + '_picker');
        if (input) input.addEventListener('input', refreshBrandPreview);
        if (picker) picker.addEventListener('input', () => requestAnimationFrame(refreshBrandPreview));
    });

    const sidebarTheme = document.getElementById('sidebar_theme');
    if (sidebarTheme) sidebarTheme.addEventListener('change', refreshBrandPreview);

    const applyBrandPalette = (palette) => {
        const map = {
            primary_color: palette.primary,
            accent_color: palette.accent,
            sidebar_color: palette.sidebar,
            background_color: palette.background
        };
        Object.entries(map).forEach(([id, value]) => {
            if (!value) return;
            const input = document.getElementById(id);
            const picker = document.getElementById(id + '_picker');
            if (input) input.value = value.toUpperCase();
            if (picker) picker.value = value;
        });
        refreshBrandPreview();
    };

    document.querySelectorAll('.brand-preset').forEach((button) => {
        button.addEventListener('click', () => applyBrandPalette(button.dataset));
    });

    const defaultsButton = document.getElementById('brandingDefaults');
    if (defaultsButton) {
        defaultsButton.addEventListener('click', () => {
            applyBrandPalette({
                primary: '#075B9F',
                accent: '#168C5B',
                sidebar: '#0A2F55',
                background: '#F4F7FB'
            });
            if (sidebarTheme) sidebarTheme.value = 'dark';
            refreshBrandPreview();
        });
    }

    refreshBrandPreview();

    /*
     * Actualización automática de tablas.
     * Pensado para hosting compartido: polling ligero, sin WebSockets ni recarga completa.
     * Solo se ejecuta mientras la pestaña del navegador está visible.
     */
    const liveElements = Array.from(document.querySelectorAll('[data-live-refresh][data-live-refresh-key]'));
    const liveRefreshMap = new Map();

    const refreshLiveElement = async (element) => {
        if (!element || !element.isConnected || document.hidden) return;
        const key = element.dataset.liveRefreshKey;
        if (!key || liveRefreshMap.get(key)) return;

        liveRefreshMap.set(key, true);
        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            if (!response.ok) return;

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector('[data-live-refresh-key="' + CSS.escape(key) + '"]');
            if (!fresh) return;

            const currentHtml = element.innerHTML;
            if (currentHtml !== fresh.innerHTML) {
                element.innerHTML = fresh.innerHTML;
                element.dataset.liveUpdatedAt = new Date().toISOString();
            }
        } catch (_) {
            // Una caída temporal de red no debe interrumpir la pantalla.
        } finally {
            liveRefreshMap.set(key, false);
        }
    };

    liveElements.forEach((element) => {
        const interval = Math.max(2000, Number(element.dataset.liveRefresh || 5000));
        const schedule = () => {
            window.setTimeout(async () => {
                if (element.isConnected) {
                    await refreshLiveElement(element);
                    schedule();
                }
            }, interval);
        };
        schedule();
    });

    // Después de generar una exportación, refrescar el historial sin F5.
    document.querySelectorAll('a.export-format, .format-actions a').forEach((link) => {
        link.addEventListener('click', () => {
            liveElements.forEach((element) => {
                window.setTimeout(() => refreshLiveElement(element), 450);
                window.setTimeout(() => refreshLiveElement(element), 1400);
            });
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) liveElements.forEach((element) => refreshLiveElement(element));
    });

    /*
     * Búsqueda incremental de Catálogos maestros.
     * Actualiza solo la tabla, conserva el foco y no obliga a pulsar Enter.
     */
    let masterSearchTimer = null;
    let masterSearchController = null;

    const runMasterSearch = async (input) => {
        const form = input.closest('[data-master-search-form]');
        if (!form || !input.isConnected) return;

        const table = document.querySelector('[data-live-refresh-key="backoffice-data-table"]');
        if (!table) return;

        const url = new URL(form.action || window.location.href, window.location.href);
        const tab = form.querySelector('input[name="tab"]')?.value || '';
        const query = input.value.trim();

        if (tab) url.searchParams.set('tab', tab);
        if (query) url.searchParams.set('q', query);
        else url.searchParams.delete('q');

        if (masterSearchController) masterSearchController.abort();
        masterSearchController = new AbortController();
        const controller = masterSearchController;

        form.classList.add('is-searching');
        table.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.href, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    'Accept': 'text/html,application/xhtml+xml',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            if (!response.ok) return;

            const html = await response.text();
            if (controller !== masterSearchController) return;

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector('[data-live-refresh-key="backoffice-data-table"]');
            if (!fresh) return;

            table.innerHTML = fresh.innerHTML;
            table.dataset.liveUpdatedAt = new Date().toISOString();

            const clear = form.querySelector('[data-master-search-clear]');
            if (clear) clear.classList.toggle('d-none', query === '');

            try {
                const state = {...(history.state || {}), bpSoftNavigation: true, scrollY: Math.round(window.scrollY || 0)};
                history.replaceState(state, '', url.href);
            } catch (_) {}
        } catch (error) {
            if (error?.name !== 'AbortError') console.error('Búsqueda incremental:', error);
        } finally {
            if (controller === masterSearchController) {
                table.removeAttribute('aria-busy');
                form.classList.remove('is-searching');
            }
        }
    };

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('#master-search');
        if (!input || !input.closest('[data-master-search-form]')) return;
        if (event.isComposing) return;

        if (masterSearchTimer) window.clearTimeout(masterSearchTimer);
        masterSearchTimer = window.setTimeout(() => runMasterSearch(input), 220);
    });

    document.addEventListener('click', (event) => {
        const clear = event.target.closest?.('[data-master-search-clear]');
        if (!clear) return;
        const form = clear.closest('[data-master-search-form]');
        const input = form?.querySelector('#master-search');
        if (!form || !input) return;

        event.preventDefault();
        input.value = '';
        input.focus({preventScroll:true});
        if (masterSearchTimer) window.clearTimeout(masterSearchTimer);
        runMasterSearch(input);
    });

    /*
     * Diálogo de observación.
     * Se usa en Documentos, Guías y Stock para evitar popovers dentro de tablas.
     */
    if (!window.BP_WORKFLOW_DIALOG_BOUND) {
        window.BP_WORKFLOW_DIALOG_BOUND = true;

        const resetWorkflowDialog = (dialog) => {
            const reason = dialog?.querySelector('[data-workflow-dialog-reason]');
            const counter = dialog?.querySelector('[data-workflow-reason-count]');
            if (reason) {
                reason.value = '';
                reason.setCustomValidity('');
            }
            if (counter) counter.textContent = '0';
        };

        const closeWorkflowDialog = (dialog) => {
            if (!dialog) return;
            resetWorkflowDialog(dialog);
            if (dialog.open) dialog.close();
        };

        document.addEventListener('click', (event) => {
            const opener = event.target.closest?.('[data-workflow-dialog-open]');
            if (opener) {
                event.preventDefault();
                const id = opener.getAttribute('data-workflow-dialog-open');
                const dialog = id ? document.getElementById(id) : null;
                if (!(dialog instanceof HTMLDialogElement)) return;

                document.querySelectorAll('dialog[data-workflow-dialog][open]').forEach((openDialog) => {
                    if (openDialog !== dialog) closeWorkflowDialog(openDialog);
                });

                resetWorkflowDialog(dialog);
                if (!dialog.open) dialog.showModal();
                window.setTimeout(() => dialog.querySelector('[data-workflow-dialog-reason]')?.focus(), 30);
                return;
            }

            const closer = event.target.closest?.('[data-workflow-dialog-close]');
            if (closer) {
                event.preventDefault();
                closeWorkflowDialog(closer.closest('dialog[data-workflow-dialog]'));
                return;
            }

            const dialog = event.target.closest?.('dialog[data-workflow-dialog]');
            if (dialog && event.target === dialog) {
                closeWorkflowDialog(dialog);
            }
        });

        document.addEventListener('input', (event) => {
            const reason = event.target.closest?.('[data-workflow-dialog-reason]');
            if (!reason) return;
            const counter = reason.closest('dialog[data-workflow-dialog]')?.querySelector('[data-workflow-reason-count]');
            if (counter) counter.textContent = String(reason.value.length);
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest?.('.workflow-dialog-form');
            if (!form) return;
            const reason = form.querySelector('[data-workflow-dialog-reason]');
            if (!reason) return;

            const value = reason.value.trim();
            if (value.length < 3) {
                event.preventDefault();
                reason.setCustomValidity('Escribe un motivo de observación claro.');
                reason.reportValidity();
                reason.focus();
            } else {
                reason.setCustomValidity('');
            }
        }, true);
    }

    if (!window.dashboardData || typeof Chart === 'undefined') return;

    const series = window.dashboardData.series || [];
    const top = window.dashboardData.top || [];
    const root = getComputedStyle(document.documentElement);
    const blue = root.getPropertyValue('--bp-blue').trim() || '#075b9f';
    const green = root.getPropertyValue('--bp-green').trim() || '#168c5b';
    const grid = 'rgba(23,50,77,.08)';

    Chart.defaults.font.family = 'Inter, Segoe UI, Roboto, Arial, sans-serif';
    Chart.defaults.color = '#6b7f92';

    const salesCanvas = document.getElementById('salesChart');
    if (salesCanvas) {
        new Chart(salesCanvas, {
            type: 'line',
            data: {
                labels: series.map(item => item.periodo),
                datasets: [{
                    label: 'Cantidad',
                    data: series.map(item => Number(item.cantidad)),
                    borderColor: blue,
                    backgroundColor: 'rgba(7,91,159,.10)',
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: .32
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: grid } }
                }
            }
        });
    }

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        const rows = window.dashboardData.statuses || [];
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: rows.map(item => item.estado_registro),
                datasets: [{
                    data: rows.map(item => Number(item.total)),
                    backgroundColor: ['#168c5b','#075b9f','#f59e0b','#d92d20','#6b7f92'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } }
            }
        });
    }

    const distributionCanvas = document.getElementById('distributionChart');
    if (distributionCanvas) {
        const rows = window.dashboardData.distribution || [];
        new Chart(distributionCanvas, {
            type: 'doughnut',
            data: {
                labels: rows.map(item => item.label),
                datasets: [{
                    data: rows.map(item => Number(item.value)),
                    backgroundColor: [blue, green, '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } }
            }
        });
    }

    const topCanvas = document.getElementById('topChart');
    if (topCanvas) {
        const topColors = top.map((_, index) => {
            const palette = ['#075b9f','#f59e0b','#7c3aed','#d92d20','#0891b2','#c2410c','#4f46e5','#be185d','#65a30d','#0f766e'];
            if (index < palette.length) return palette[index];

            // Si aparecen más productos, generamos tonos distintos para evitar barras repetidas.
            const hue = Math.round((index * 137.508) % 360);
            return `hsl(${hue} 68% 44%)`;
        });

        new Chart(topCanvas, {
            type: 'bar',
            data: {
                labels: top.map(item => item.nombre),
                datasets: [{
                    label: 'Cantidad',
                    data: top.map(item => Number(item.cantidad)),
                    backgroundColor: topColors,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: grid } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
})();


// Confirmación centralizada para acciones masivas de workflow.
document.addEventListener('submit', function (event) {
    const form = event.target.closest?.('[data-bulk-workflow]');
    if (!form) return;
    const label = form.dataset.bulkLabel || 'procesar todos los registros';
    if (!window.confirm('¿Confirmas que deseas ' + label + '? Esta acción se aplicará a todos los registros elegibles del módulo.')) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);


// Confirmación para borrado de catálogos maestros.
document.addEventListener('submit', function (event) {
    const form = event.target.closest?.('[data-master-delete]');
    if (!form) return;
    const label = form.dataset.masterLabel || 'este registro';
    if (!window.confirm('¿Eliminar "' + label + '"? Esta acción no se puede deshacer. Si el registro está en uso, el sistema impedirá eliminarlo.')) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);
