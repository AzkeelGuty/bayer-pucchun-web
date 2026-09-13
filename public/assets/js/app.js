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
        new Chart(topCanvas, {
            type: 'bar',
            data: {
                labels: top.map(item => item.nombre),
                datasets: [{
                    label: 'Cantidad',
                    data: top.map(item => Number(item.cantidad)),
                    backgroundColor: green,
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
