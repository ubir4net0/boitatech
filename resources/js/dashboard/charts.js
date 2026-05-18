import { Chart, ensureChartRegistry } from '../lib/chart.js';

const CHART_PALETTE = ['#3DFF9A', '#22d3ee', '#f97316', '#ef4444', '#a78bfa', '#facc15', '#38bdf8', '#06b6d4', '#ec4899'];

const BIOME_COLORS = {
    'Amazônia': '#059669',
    'Cerrado': '#eab308',
    'Mata Atlântica': '#16a34a',
    'Caatinga': '#ea580c',
    'Pantanal': '#0891b2',
    'Pampa': '#6366f1',
};

const sanitizeLabel = (value, maxLength = 42) => {
    const clean = String(value ?? '')
        .replace(/[\u0000-\u001F\u007F]/g, '')
        .replace(/[<>]/g, '')
        .trim();

    if (clean.length === 0) return 'N/D';

    return clean.slice(0, maxLength);
};

const normalizeSeries = (items, limit = 12) => {
    if (!Array.isArray(items)) {
        return [{ label: 'Sem dados', value: 0 }];
    }

    const normalized = items
        .map((item) => ({
            label: sanitizeLabel(item?.label),
            value: Number(item?.value ?? 0),
        }))
        .filter((item) => Number.isFinite(item.value) && item.value >= 0)
        .sort((a, b) => b.value - a.value)
        .slice(0, limit);

    if (normalized.length === 0) {
        return [{ label: 'Sem dados', value: 0 }];
    }

    return normalized;
};

export const createDashboardCharts = ({ categoryCanvas, stateCanvas, biomeCanvas, numberFormatter }) => {
    ensureChartRegistry();

    let categoryChart = null;
    let stateChart = null;
    let biomeChart = null;

    const renderCategory = (series) => {
        const data = normalizeSeries(series);

        if (!categoryCanvas) return;

        if (!categoryChart) {
            categoryChart = new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: data.map((item) => item.label),
                    datasets: [
                        {
                            data: data.map((item) => item.value),
                            borderWidth: 1,
                            borderColor: 'rgba(15,23,42,0.95)',
                            backgroundColor: CHART_PALETTE,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400, easing: 'easeOutCubic' },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#cbd5e1',
                                boxWidth: 12,
                                padding: 12,
                                font: { size: 12, weight: '500' },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${sanitizeLabel(ctx.label)}: ${numberFormatter.format(Number(ctx.parsed ?? 0))}`,
                            },
                            backgroundColor: 'rgba(2,6,23,.9)',
                            borderColor: 'rgba(61,255,154,.3)',
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                        },
                    },
                },
            });

            return;
        }

        categoryChart.data.labels = data.map((item) => item.label);
        categoryChart.data.datasets[0].data = data.map((item) => item.value);
        categoryChart.update('none');
    };

    const renderState = (series) => {
        const data = normalizeSeries(series);

        if (!stateCanvas) return;

        if (!stateChart) {
            stateChart = new Chart(stateCanvas, {
                type: 'bar',
                data: {
                    labels: data.map((item) => item.label),
                    datasets: [
                        {
                            label: 'Denúncias',
                            data: data.map((item) => item.value),
                            borderRadius: 6,
                            backgroundColor: 'rgba(61,255,154,.7)',
                            hoverBackgroundColor: 'rgba(61,255,154,.9)',
                            borderColor: 'rgba(61,255,154,.4)',
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400, easing: 'easeOutCubic' },
                    indexAxis: 'y',
                    scales: {
                        x: {
                            ticks: { color: '#cbd5e1', precision: 0 },
                            grid: { color: 'rgba(148,163,184,.08)', drawBorder: false },
                        },
                        y: {
                            ticks: { color: '#cbd5e1' },
                            grid: { display: false, drawBorder: false },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${numberFormatter.format(Number(ctx.parsed?.x ?? 0))} denúncias`,
                            },
                            backgroundColor: 'rgba(2,6,23,.9)',
                            borderColor: 'rgba(61,255,154,.3)',
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                        },
                    },
                },
            });

            return;
        }

        stateChart.data.labels = data.map((item) => item.label);
        stateChart.data.datasets[0].data = data.map((item) => item.value);
        stateChart.update('none');
    };

    const renderBiome = (series) => {
        const data = normalizeSeries(series, 6);

        if (!biomeCanvas) return;

        const colors = data.map((item) => BIOME_COLORS[item.label] || CHART_PALETTE[Math.floor(Math.random() * CHART_PALETTE.length)]);

        if (!biomeChart) {
            biomeChart = new Chart(biomeCanvas, {
                type: 'bar',
                data: {
                    labels: data.map((item) => item.label),
                    datasets: [
                        {
                            label: 'Alertas',
                            data: data.map((item) => item.value),
                            borderRadius: 8,
                            backgroundColor: colors,
                            hoverBackgroundColor: colors.map((c) => c.replace(/\)/, ', 0.9)')),
                            borderColor: 'rgba(148,163,184,.2)',
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400, easing: 'easeOutCubic' },
                    scales: {
                        x: {
                            ticks: { color: '#cbd5e1', maxRotation: 45, minRotation: 0 },
                            grid: { color: 'rgba(148,163,184,.08)', drawBorder: false },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#cbd5e1', precision: 0 },
                            grid: { color: 'rgba(148,163,184,.08)', drawBorder: false },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${numberFormatter.format(Number(ctx.parsed?.y ?? 0))} alertas`,
                            },
                            backgroundColor: 'rgba(2,6,23,.9)',
                            borderColor: 'rgba(61,255,154,.3)',
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                        },
                    },
                },
            });

            return;
        }

        biomeChart.data.labels = data.map((item) => item.label);
        biomeChart.data.datasets[0].data = data.map((item) => item.value);
        biomeChart.data.datasets[0].backgroundColor = colors;
        biomeChart.update('none');
    };

    return {
        render: ({ category, state, biome }) => {
            renderCategory(category);
            renderState(state);
            renderBiome(biome);
        },
        resize: () => {
            categoryChart?.resize();
            stateChart?.resize();
            biomeChart?.resize();
        },
        destroy: () => {
            categoryChart?.destroy();
            stateChart?.destroy();
            biomeChart?.destroy();
            categoryChart = null;
            stateChart = null;
            biomeChart = null;
        },
    };
};
