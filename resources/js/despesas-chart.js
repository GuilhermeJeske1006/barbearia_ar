import Chart from 'chart.js/auto';

export default function despesasChart(categoriaLabels, categoriaValores, tendenciaLabels, tendenciaValores) {
    return {
        categoriaChart: null,
        tendenciaChart: null,

        init() {
            this.categoriaChart = new Chart(this.$refs.categoriaCanvas, {
                type: 'bar',
                data: {
                    labels: categoriaLabels,
                    datasets: [{ data: categoriaValores, backgroundColor: '#d91e18' }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
            });

            this.tendenciaChart = new Chart(this.$refs.tendenciaCanvas, {
                type: 'line',
                data: {
                    labels: tendenciaLabels,
                    datasets: [{ data: tendenciaValores, borderColor: '#d91e18', tension: 0.3 }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
            });

            this.$wire.on('despesas-relatorio-atualizado', (payload) => {
                this.categoriaChart.data.labels = payload.categoriaLabels;
                this.categoriaChart.data.datasets[0].data = payload.categoriaValores;
                this.categoriaChart.update();

                this.tendenciaChart.data.labels = payload.tendenciaLabels;
                this.tendenciaChart.data.datasets[0].data = payload.tendenciaValores;
                this.tendenciaChart.update();
            });
        },
    };
}
