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
