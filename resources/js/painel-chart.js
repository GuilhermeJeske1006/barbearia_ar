import Chart from 'chart.js/auto';

export default function painelChart({ faturamentoLabels, faturamentoValores, statusLabels, statusValores, statusCores, atendimentosLabels, atendimentosValores, comissoesLabels, comissoesValores }) {
    return {
        init() {
            if (this.$refs.faturamentoCanvas) {
                new Chart(this.$refs.faturamentoCanvas, {
                    type: 'bar',
                    data: {
                        labels: faturamentoLabels,
                        datasets: [{ data: faturamentoValores, backgroundColor: '#1a334f' }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
                });
            }

            if (this.$refs.statusCanvas) {
                new Chart(this.$refs.statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{ data: statusValores, backgroundColor: statusCores }],
                    },
                    options: { responsive: true, maintainAspectRatio: false },
                });
            }

            if (this.$refs.atendimentosCanvas) {
                new Chart(this.$refs.atendimentosCanvas, {
                    type: 'bar',
                    data: {
                        labels: atendimentosLabels,
                        datasets: [{ data: atendimentosValores, backgroundColor: '#1a334f' }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
                });
            }

            if (this.$refs.comissoesCanvas) {
                new Chart(this.$refs.comissoesCanvas, {
                    type: 'line',
                    data: {
                        labels: comissoesLabels,
                        datasets: [{ data: comissoesValores, borderColor: '#1a334f', tension: 0.3 }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
                });
            }
        },
    };
}
