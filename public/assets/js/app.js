(() => {
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('appSidebar');

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
