import mask from '@alpinejs/mask';
import collapse from '@alpinejs/collapse';
import QRCode from 'qrcode';
import despesasChart from './despesas-chart';
import lucroChart from './lucro-chart';
import painelChart from './painel-chart';
import stripeCheckout from './stripe-checkout';

window.QRCode = QRCode;

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(mask);
    window.Alpine.plugin(collapse);
    window.Alpine.data('stripeCheckout', stripeCheckout);
    window.Alpine.data('despesasChart', despesasChart);
    window.Alpine.data('lucroChart', lucroChart);
    window.Alpine.data('painelChart', painelChart);
});

document.addEventListener('livewire:init', () => {
    Livewire.on('theme-changed', ({ theme }) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    });
});
