import ApexCharts from 'apexcharts';

function parseDataset(el, key) {
    try {
        const raw = el.getAttribute(`data-${key}`);

        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function renderBarCharts() {
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
                width: '100%',
                toolbar: { show: false },
                fontFamily: 'inherit',
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
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
            responsive: [
                {
                    breakpoint: 768,
                    options: {
                        chart: { height: 260 },
                        plotOptions: { bar: { columnWidth: '58%', borderRadius: 2 } },
                    },
                },
                {
                    breakpoint: 480,
                    options: {
                        chart: { height: 300 },
                        xaxis: {
                            labels: {
                                rotate: -40,
                                maxHeight: 88,
                                style: { fontSize: '10px' },
                            },
                        },
                        legend: { fontSize: '11px', offsetY: 2 },
                    },
                },
            ],
        });

        chart.render();
        el._bbApexChart = chart;
    });
}

function renderCategoryDonuts() {
    document.querySelectorAll('.bb-category-donut').forEach((el) => {
        if (el._bbApexChart) {
            el._bbApexChart.destroy();
            el._bbApexChart = null;
        }

        const labels = parseDataset(el, 'labels');
        const values = parseDataset(el, 'values');
        const currency = el.dataset.currency ?? '';
        const emptyMessage = el.dataset.emptyMessage ?? '';

        if (labels.length === 0 || values.length === 0) {
            el.innerHTML = `<p class="text-base-content/60 text-sm py-8 text-center">${emptyMessage}</p>`;

            return;
        }

        const sum = values.reduce((a, b) => a + Number(b), 0);
        if (!Number.isFinite(sum) || sum <= 0) {
            el.innerHTML = `<p class="text-base-content/60 text-sm py-8 text-center">${emptyMessage}</p>`;

            return;
        }

        el.innerHTML = '';

        const chart = new ApexCharts(el, {
            chart: {
                type: 'donut',
                height: 320,
                width: '100%',
                fontFamily: 'inherit',
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
            },
            labels,
            series: values.map((v) => Number(v)),
            legend: { position: 'bottom' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                    },
                },
            },
            dataLabels: { enabled: false },
            tooltip: {
                y: {
                    formatter(val) {
                        return `${Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
                    },
                },
            },
            responsive: [
                {
                    breakpoint: 480,
                    options: {
                        chart: { height: 300 },
                        legend: {
                            position: 'bottom',
                            fontSize: '11px',
                            itemMargin: { horizontal: 4, vertical: 2 },
                        },
                        plotOptions: {
                            pie: {
                                donut: { size: '58%' },
                            },
                        },
                    },
                },
            ],
        });

        chart.render();
        el._bbApexChart = chart;
    });
}

function renderSnapshotTrendLines() {
    document.querySelectorAll('.bb-snapshot-trend').forEach((el) => {
        if (el._bbApexChart) {
            el._bbApexChart.destroy();
            el._bbApexChart = null;
        }

        const labels = parseDataset(el, 'labels');
        const income = parseDataset(el, 'income');
        const expense = parseDataset(el, 'expense');
        const net = parseDataset(el, 'net');
        const currency = el.dataset.currency ?? '';
        const incomeLabel = el.dataset.incomeLabel ?? 'Income';
        const expenseLabel = el.dataset.expenseLabel ?? 'Expenses';
        const netLabel = el.dataset.netLabel ?? 'Net';

        if (labels.length < 2) {
            el.innerHTML = '';

            return;
        }

        el.innerHTML = '';

        const chart = new ApexCharts(el, {
            chart: {
                type: 'line',
                height: 300,
                toolbar: { show: false },
                fontFamily: 'inherit',
                zoom: { enabled: false },
            },
            stroke: { width: [2, 2, 3], curve: 'smooth' },
            series: [
                { name: incomeLabel, data: income.map(Number) },
                { name: expenseLabel, data: expense.map(Number) },
                { name: netLabel, data: net.map(Number) },
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
            colors: ['#22c55e', '#f97316', '#3b82f6'],
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

function renderDashboardCharts() {
    renderBarCharts();
    renderCategoryDonuts();
    renderSnapshotTrendLines();
}

document.addEventListener('DOMContentLoaded', renderDashboardCharts);
document.addEventListener('livewire:navigated', renderDashboardCharts);
