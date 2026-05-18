import {
    Chart,
    DoughnutController,
    BarController,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

let chartRegistryReady = false;

export const ensureChartRegistry = () => {
    if (chartRegistryReady) return;

    Chart.register(
        DoughnutController,
        BarController,
        ArcElement,
        BarElement,
        CategoryScale,
        LinearScale,
        Tooltip,
        Legend,
    );

    chartRegistryReady = true;
};

export { Chart };
