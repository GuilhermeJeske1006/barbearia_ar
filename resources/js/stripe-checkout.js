/**
 * Checkout transparente: monta o Stripe PaymentElement no client_secret da
 * Subscription 'incomplete' criada em Register::avancarParaPagamento(), e
 * confirma o pagamento sem redirecionar pro Stripe. Só depois de confirmado
 * é que finalizarCadastro() (Livewire) cria a conta de verdade.
 */
export default function stripeCheckout({ publicKey, clientSecret, labelPagar, labelProcessando, erroGenerico }) {
    return {
        publicKey,
        clientSecret,
        labelPagar,
        labelProcessando,
        erroGenerico,
        processando: false,
        erro: null,
        stripe: null,
        elements: null,

        montar() {
            if (!window.Stripe || !this.publicKey || !this.clientSecret) {
                this.erro = this.erroGenerico;

                return;
            }

            this.stripe = window.Stripe(this.publicKey);
            this.elements = this.stripe.elements({ clientSecret: this.clientSecret });
            this.elements.create('payment').mount('#stripe-payment-element');
        },

        async confirmarPagamento() {
            if (!this.stripe || !this.elements || this.processando) {
                return;
            }

            this.processando = true;
            this.erro = null;

            const { error } = await this.stripe.confirmPayment({
                elements: this.elements,
                redirect: 'if_required',
            });

            if (error) {
                this.erro = error.message || this.erroGenerico;
                this.processando = false;

                return;
            }

            await this.$wire.finalizarCadastro();
            this.processando = false;
        },
    };
}
