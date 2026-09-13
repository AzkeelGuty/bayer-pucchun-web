(() => {
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.addEventListener('click', () => body.classList.toggle('sidebar-open'));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') body.classList.remove('sidebar-open');
        });
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
