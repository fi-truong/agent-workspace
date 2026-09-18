import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('usageOverTimeChart');
    const source = document.getElementById('usageOverTimeData');

    if (!canvas || !source) return;

    const usage = JSON.parse(source.textContent || '[]');

    new Chart(canvas, {
        data: {
            labels: usage.map((day) => day.label),
            datasets: [
                {
                    type: 'bar',
                    label: 'Tokens used',
                    data: usage.map((day) => day.tokens),
                    backgroundColor: 'rgba(31, 81, 71, 0.82)',
                    borderColor: '#1f5147',
                    borderWidth: 1,
                    borderRadius: 5,
                    yAxisID: 'tokens',
                },
                {
                    type: 'line',
                    label: 'Prompts sent',
                    data: usage.map((day) => day.prompts),
                    borderColor: '#d89b34',
                    backgroundColor: 'rgba(216, 155, 52, 0.16)',
                    pointBackgroundColor: '#d89b34',
                    pointRadius: 3,
                    tension: 0.35,
                    fill: true,
                    yAxisID: 'prompts',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 11, usePointStyle: true, pointStyle: 'circle' },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw || 0).toLocaleString();
                            return `${context.dataset.label}: ${value}`;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                tokens: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    ticks: { callback: (value) => Number(value).toLocaleString() },
                    title: { display: true, text: 'Tokens' },
                },
                prompts: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0 },
                    title: { display: true, text: 'Prompts' },
                },
            },
        },
    });
});
