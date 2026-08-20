import mask from '@alpinejs/mask';
import QRCode from 'qrcode';

window.QRCode = QRCode;

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(mask);
});

document.addEventListener('livewire:init', () => {
    Livewire.on('theme-changed', ({ theme }) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    });
});
