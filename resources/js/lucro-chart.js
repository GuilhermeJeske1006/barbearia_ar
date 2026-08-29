import Chart from 'chart.js/auto';

export default function lucroChart(labels, receita, despesas, lucro) {
    return {
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.lucroCanvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Receita', data: receita, backgroundColor: '#a8763e' },
                        { label: 'Despesas', data: despesas, backgroundColor: '#b45309' },
                        { label: 'Lucro', data: lucro, type: 'line', borderColor: '#166534', tension: 0.3 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });

            this.$wire.on('lucro-relatorio-atualizado', (payload) => {
                this.chart.data.labels = payload.labels;
                this.chart.data.datasets[0].data = payload.receita;
                this.chart.data.datasets[1].data = payload.despesas;
                this.chart.data.datasets[2].data = payload.lucro;
                this.chart.update();
            });
        },
    };
}
