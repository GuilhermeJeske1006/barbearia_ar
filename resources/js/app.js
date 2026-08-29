import mask from '@alpinejs/mask';
import QRCode from 'qrcode';
import despesasChart from './despesas-chart';
import stripeCheckout from './stripe-checkout';

window.QRCode = QRCode;

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(mask);
    window.Alpine.data('stripeCheckout', stripeCheckout);
    window.Alpine.data('despesasChart', despesasChart);
});

document.addEventListener('livewire:init', () => {
    Livewire.on('theme-changed', ({ theme }) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    });
});
