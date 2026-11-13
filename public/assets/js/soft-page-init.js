(() => {
    'use strict';

    const rootStyle = () => getComputedStyle(document.documentElement);

    function initAlerts(root) {
        root.querySelectorAll('.js-auto-dismiss').forEach((alert) => {
            if (alert.dataset.bpSoftAlertReady === '1') return;
            alert.dataset.bpSoftAlertReady = '1';
            window.setTimeout(() => {
                if (!alert.isConnected) return;
                if (window.bootstrap?.Alert) window.bootstrap.Alert.getOrCreateInstance(alert).close();
                else alert.remove();
            }, 4500);
        });
    }

    function initBranding(root) {
        if (window.BP_BRANDING_BOUND) return;
        const preview = root.querySelector('#brandingPreview');
        const loginPreview = root.querySelector('#brandingLoginPreview');
        if (!preview && !loginPreview) return;

        const readColor = (id, fallback) => {
            const input = root.querySelector('#' + id);
            return input && /^#[0-9A-Fa-f]{6}$/.test(input.value) ? input.value.toUpperCase() : fallback;
        };

        const refresh = () => {
            const primary = readColor('primary_color', '#075B9F');
            const accent = readColor('accent_color', '#168C5B');
            const sidebar = readColor('sidebar_color', '#0A2F55');
            const background = readColor('background_color', '#F4F7FB');

            [preview, loginPreview].filter(Boolean).forEach((node) => {
                node.style.setProperty('--preview-primary', primary);
                node.style.setProperty('--preview-accent', accent);
                node.style.setProperty('--preview-sidebar', sidebar);
                node.style.setProperty('--preview-bg', background);
            });

            const theme = root.querySelector('#sidebar_theme');
            if (preview && theme) {
                preview.classList.toggle('sidebar-light', theme.value === 'light');
                preview.classList.toggle('sidebar-dark', theme.value !== 'light');
            }
        };

        const syncColor = (name) => {
            const picker = root.querySelector('#' + name + '_picker');
            const input = root.querySelector('#' + name);
            if (!picker || !input) return;
            picker.addEventListener('input', () => {
                input.value = picker.value.toUpperCase();
                refresh();
            });
            input.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(input.value)) picker.value = input.value;
                refresh();
            });
        };

        ['primary_color','accent_color','sidebar_color','background_color'].forEach(syncColor);

        root.querySelectorAll('.brand-live-text').forEach((input) => {
            const target = root.querySelector('#' + (input.dataset.preview || ''));
            if (!target) return;
            input.addEventListener('input', () => { target.textContent = input.value || '—'; });
        });

        const theme = root.querySelector('#sidebar_theme');
        if (theme) theme.addEventListener('change', refresh);

        const applyPalette = (palette) => {
            const map = {
                primary_color: palette.primary,
                accent_color: palette.accent,
                sidebar_color: palette.sidebar,
                background_color: palette.background
            };
            Object.entries(map).forEach(([id, value]) => {
                if (!value) return;
                const input = root.querySelector('#' + id);
                const picker = root.querySelector('#' + id + '_picker');
                if (input) input.value = value.toUpperCase();
                if (picker) picker.value = value;
            });
            refresh();
        };

        root.querySelectorAll('.brand-preset').forEach((button) => {
            button.addEventListener('click', () => applyPalette(button.dataset));
        });

        const defaults = root.querySelector('#brandingDefaults');
        if (defaults) {
            defaults.addEventListener('click', () => {
                applyPalette({
                    primary:'#075B9F',
                    accent:'#168C5B',
                    sidebar:'#0A2F55',
                    background:'#F4F7FB'
                });
                if (theme) theme.value = 'dark';
                refresh();
            });
        }

        refresh();
    }

    function initLiveRefresh(root) {
        root.querySelectorAll('[data-live-refresh][data-live-refresh-key]').forEach((element) => {
            if (element.dataset.bpSoftLiveReady === '1') return;
            element.dataset.bpSoftLiveReady = '1';
            const interval = Math.max(2000, Number(element.dataset.liveRefresh || 5000));
            let busy = false;

            const refresh = async () => {
                if (!element.isConnected || document.hidden || busy) return;
                busy = true;
                try {
                    const response = await fetch(location.href, {
                        credentials:'same-origin',
                        cache:'no-store',
                        headers:{'X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'}
                    });
                    if (!response.ok) return;
                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const key = element.dataset.liveRefreshKey;
                    const fresh = doc.querySelector('[data-live-refresh-key="' + CSS.escape(key) + '"]');
                    if (fresh && fresh.innerHTML !== element.innerHTML) element.innerHTML = fresh.innerHTML;
                } catch (_) {
                } finally {
                    busy = false;
                }
            };

            const schedule = () => {
                window.setTimeout(async () => {
                    if (!element.isConnected) return;
                    await refresh();
                    schedule();
                }, interval);
            };
            schedule();

            root.querySelectorAll('a.export-format, .format-actions a').forEach((link) => {
                if (link.dataset.bpSoftExportReady === '1') return;
                link.dataset.bpSoftExportReady = '1';
                link.addEventListener('click', () => {
                    window.setTimeout(refresh, 450);
                    window.setTimeout(refresh, 1400);
                });
            });
        });
    }

    function initCharts(root) {
        if (!window.dashboardData || typeof Chart === 'undefined') return;

        const series = window.dashboardData.series || [];
        const top = window.dashboardData.top || [];
        const styles = rootStyle();
        const blue = styles.getPropertyValue('--bp-blue').trim() || '#075b9f';
        const green = styles.getPropertyValue('--bp-green').trim() || '#168c5b';
        const grid = 'rgba(23,50,77,.08)';

        Chart.defaults.font.family = 'Inter, Segoe UI, Roboto, Arial, sans-serif';
        Chart.defaults.color = '#6b7f92';

        const recreate = (canvas, config) => {
            if (!canvas) return;
            const existing = Chart.getChart(canvas);
            if (existing) existing.destroy();
            new Chart(canvas, config);
        };

        recreate(root.querySelector('#salesChart'), {
            type:'line',
            data:{
                labels:series.map(item => item.periodo),
                datasets:[{
                    label:'Cantidad',
                    data:series.map(item => Number(item.cantidad)),
                    borderColor:blue,
                    backgroundColor:'rgba(7,91,159,.10)',
                    fill:true,
                    borderWidth:3,
                    pointRadius:3,
                    pointHoverRadius:5,
                    tension:.32
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                plugins:{legend:{display:false}},
                scales:{
                    x:{grid:{display:false}},
                    y:{beginAtZero:true,grid:{color:grid}}
                }
            }
        });

        const statuses = window.dashboardData.statuses || [];
        recreate(root.querySelector('#statusChart'), {
            type:'doughnut',
            data:{
                labels:statuses.map(item => item.estado_registro),
                datasets:[{
                    data:statuses.map(item => Number(item.total)),
                    backgroundColor:['#168c5b','#075b9f','#f59e0b','#d92d20','#6b7f92'],
                    borderWidth:0,
                    hoverOffset:4
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                cutout:'68%',
                plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,padding:16}}}
            }
        });

        const distribution = window.dashboardData.distribution || [];
        recreate(root.querySelector('#distributionChart'), {
            type:'doughnut',
            data:{
                labels:distribution.map(item => item.label),
                datasets:[{
                    data:distribution.map(item => Number(item.value)),
                    backgroundColor:[blue,green,'#f59e0b'],
                    borderWidth:0,
                    hoverOffset:4
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                cutout:'66%',
                plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,padding:16}}}
            }
        });

        const topColors = top.map((_, index) => {
            const palette=['#075b9f','#f59e0b','#7c3aed','#d92d20','#0891b2','#c2410c','#4f46e5','#be185d','#65a30d','#0f766e'];
            if (index < palette.length) return palette[index];
            const hue = Math.round((index * 137.508) % 360);
            return `hsl(${hue} 68% 44%)`;
        });
        recreate(root.querySelector('#topChart'), {
            type:'bar',
            data:{
                labels:top.map(item => item.nombre),
                datasets:[{
                    label:'Cantidad',
                    data:top.map(item => Number(item.cantidad)),
                    backgroundColor:topColors,
                    borderRadius:8,
                    borderSkipped:false
                }]
            },
            options:{
                indexAxis:'y',
                responsive:true,
                maintainAspectRatio:false,
                plugins:{legend:{display:false}},
                scales:{
                    x:{beginAtZero:true,grid:{color:grid}},
                    y:{grid:{display:false}}
                }
            }
        });
    }

    function init(root = document) {
        initAlerts(root);
        initBranding(root);
        initLiveRefresh(root);
        initCharts(root);
    }

    document.addEventListener('bp:navigation:complete', () => {
        init(document.querySelector('.app-main') || document);
    });

    window.BP_SoftPageInit = {init};
})();
