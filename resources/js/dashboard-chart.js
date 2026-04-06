import ApexCharts from 'apexcharts';

function parseDataset(el, key) {
    try {
        const raw = el.getAttribute(`data-${key}`);

        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function renderDashboardCharts() {
    document.querySelectorAll('.bb-dashboard-chart').forEach((el) => {
        if (el._bbApexChart) {
            el._bbApexChart.destroy();
            el._bbApexChart = null;
        }

        const labels = parseDataset(el, 'labels');
        const income = parseDataset(el, 'income');
        const expense = parseDataset(el, 'expense');
        const currency = el.dataset.currency ?? '';
        const incomeLabel = el.dataset.incomeLabel ?? 'Income';
        const expenseLabel = el.dataset.expenseLabel ?? 'Expenses';

        if (labels.length === 0) {
            el.innerHTML = '';

            return;
        }

        el.innerHTML = '';

        const chart = new ApexCharts(el, {
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            series: [
                { name: incomeLabel, data: income },
                { name: expenseLabel, data: expense },
            ],
            xaxis: { categories: labels },
            yaxis: {
                labels: {
                    formatter(val) {
                        return Number(val).toLocaleString(undefined, { maximumFractionDigits: 0 });
                    },
                },
            },
            dataLabels: { enabled: false },
            plotOptions: {
                bar: { columnWidth: '55%', borderRadius: 2 },
            },
            colors: ['#22c55e', '#f97316'],
            legend: { position: 'top' },
            tooltip: {
                y: {
                    formatter(val) {
                        return `${Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
                    },
                },
            },
        });

        chart.render();
        el._bbApexChart = chart;
    });
}

document.addEventListener('DOMContentLoaded', renderDashboardCharts);
document.addEventListener('livewire:navigated', renderDashboardCharts);
