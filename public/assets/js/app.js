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

    // La actualización de tablas y la búsqueda incremental se coordinan
    // exclusivamente desde soft-page-init.js y navigation.js.
    // Eliminar los temporizadores duplicados evita peticiones HTML innecesarias.
    document.addEventListener('click',(event)=>{
        const clear=event.target.closest?.('[data-master-search-clear]');
        if(!clear)return;
        const form=clear.closest('[data-master-search-form]');
        const input=form?.querySelector('input[name="q"]');
        if(!(input instanceof HTMLInputElement))return;
        event.preventDefault();
        input.value='';
        input.focus({preventScroll:true});
        input.dispatchEvent(new Event('input',{bubbles:true}));
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
    const logicalDelete = form.dataset.masterDeleteMode === 'deactivate';
    const message = logicalDelete
        ? '¿Eliminar "' + label + '" de las homologaciones activas? El registro quedará inactivo y se conservará su historial.'
        : '¿Eliminar "' + label + '"? Esta acción no se puede deshacer. Si el registro está en uso, el sistema impedirá eliminarlo.';
    if (!window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);


// Control de acciones supervisadas: el motivo debe ser obligatorio al observar.
// Se usa delegación para que siga funcionando tras la navegación interna.
document.addEventListener('change', (event) => {
    const select=event.target.closest?.('[data-workflow-status]');
    if (!select) return;
    const form=select.closest('[data-workflow-supervised]');
    if (!form) return;
    const needsReason=['OBSERVADO','ANULADO'].includes(select.value);
    const reason=form.querySelector('[data-workflow-reason]');
    if (reason) {
        reason.required=needsReason;
        reason.placeholder=needsReason ? 'Motivo obligatorio para observar' : 'Motivo si aplica';
    }
    const action=form.querySelector('button[type="submit"]');
    if (action) {
        action.classList.remove('btn-app-blue','btn-app-green','btn-app-amber');
        action.classList.add(select.value==='PUBLICADO' ? 'btn-app-green' :
            needsReason ? 'btn-app-amber' : 'btn-app-blue');
    }
});


// Filtros GET: al elegir una opción del desplegable se aplica de inmediato.
// Solo afecta formularios de consulta; nunca formularios de edición/POST.
document.addEventListener('change', (event) => {
    const select = event.target?.closest?.('select');
    if (!select || select.hasAttribute('data-no-filter-autosubmit')) return;
    const form = select.form;
    if (!form || form.method.toLowerCase() !== 'get') return;
    if (form.hasAttribute('data-no-filter-autosubmit')) return;
    const queryForm = form.matches('[data-master-search-form], [data-stock-unified-filter]') ||
        form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    if (!queryForm && !select.hasAttribute('data-filter-autosubmit')) return;
    // Un cambio de filtro empieza en la primera página; conserva las otras opciones.
    form.querySelectorAll('input[name="page"], input[name="stock_pagina"]').forEach((input) => {
        input.value = '1';
    });
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
});


// Edición manual de precios Bayer: cálculos de apoyo, sin determinar la tasa
// tributaria por defecto. El servidor siempre valida los importes guardados.
document.addEventListener('input', (event) => {
    const field=event.target;
    const form=field?.closest?.('[data-bayer-price-editor]');
    if (!form) return;
    if (!['bayer-precio-neto','bayer-precio-igv','bayer-precio-final'].includes(field.id)) return;
    const net=form.querySelector('#bayer-precio-neto');
    const vat=form.querySelector('#bayer-precio-igv');
    const gross=form.querySelector('#bayer-precio-final');
    if (!net || !vat || !gross) return;
    const rate=vat.value.trim()==='' ? NaN : Number(vat.value);
    if (!Number.isFinite(rate) || rate < 0 || rate > 100) return;
    if (field===gross) {
        if (gross.value.trim()==='') return;
        const amount=Number(gross.value);
        if (Number.isFinite(amount) && amount>=0)
            net.value=(amount/(1+rate/100)).toFixed(2);
        return;
    }
    if (net.value.trim()==='') return;
    const amount=Number(net.value);
    if (Number.isFinite(amount) && amount>=0)
        gross.value=(amount*(1+rate/100)).toFixed(2);
});


// Cantidad Bayer: solo es obligatoria cuando se elige registrar una cifra.
document.addEventListener('change',(event)=>{
    const select=event.target?.closest?.('[data-bayer-stock-action]');
    if(!select)return;
    const form=select.closest('form');
    const wrap=form?.querySelector('[data-bayer-stock-qty-wrap]');
    const amount=wrap?.querySelector('[name="inventario_cantidad"]');
    if(!wrap||!amount)return;
    const numeric=select.value==='CANTIDAD';
    wrap.hidden=!numeric;
    amount.required=numeric;
});
