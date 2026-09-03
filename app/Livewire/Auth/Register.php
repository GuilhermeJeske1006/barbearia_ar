<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Models\Barbearia;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::guest', ['maxWidth' => 'max-w-xl'])]
class Register extends Component
{
    /**
     * Wizard de 2 passos: 'dados' coleta dono+barbearia, 'pagamento' embute
     * o Stripe PaymentElement (checkout transparente, sem redirect). A conta
     * só é criada em finalizarCadastro(), depois do pagamento confirmado —
     * ver RegistrarDonoEBarbeariaAction.
     */
    public string $step = 'dados';

    public string $nome = '';

    public string $email = '';

    public string $telefoneDono = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public string $nomeBarbearia = '';

    public string $slugBarbearia = '';

    public string $telefoneBarbearia = '';

    public string $enderecoBarbearia = '';

    public string $cidadeBarbearia = '';

    public string $provinciaBarbearia = '';

    public string $paisBarbearia = '';

    public string $cuitBarbearia = '';

    public string $idiomaPadrao = 'pt';

    public ?string $stripeCustomerId = null;

    public ?string $stripeSubscriptionId = null;

    public ?string $stripeClientSecret = null;

    public ?string $stripePublicKey = null;

    private bool $slugTocado = false;

    public function updatedNomeBarbearia(): void
    {
        if (! $this->slugTocado) {
            $this->slugBarbearia = Str::slug($this->nomeBarbearia);
        }
    }

    public function updatedSlugBarbearia(): void
    {
        $this->slugTocado = true;
        $this->slugBarbearia = Str::slug($this->slugBarbearia);
    }

    /**
     * Passo 1 → 2: valida dono+barbearia e já cria a Subscription no Stripe
     * como 'incomplete' (sem cobrar ainda) só pra obter o client_secret que o
     * passo 2 usa pra montar o PaymentElement. Nenhum registro é gravado no
     * nosso banco aqui — só depois do pagamento confirmado.
     *
     * REGRA DE PLANOS DESATIVADA TEMPORARIAMENTE: por enquanto o cadastro
     * cria a conta direto, sem exigir assinatura Stripe (ver bloco comentado
     * abaixo e finalizarCadastroSemAssinatura()). Pra reativar: descomentar
     * o trecho do Stripe, voltar o parâmetro `CriarAssinaturaStripeAction
     * $action` na assinatura do método (import em App\Actions\Pagamento) e
     * o wire:submit do blade pra este fluxo.
     */
    public function avancarParaPagamento(): void
    {
        $this->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telefoneDono' => 'nullable|string|max:30',
            'senha' => ['required', 'string', Password::default(), 'confirmed'],
            'nomeBarbearia' => 'required|string|max:255',
            'slugBarbearia' => 'required|string|max:255|alpha_dash|unique:barbearias,slug',
            'telefoneBarbearia' => 'nullable|string|max:30',
            'enderecoBarbearia' => 'nullable|string|max:255',
            'cidadeBarbearia' => 'nullable|string|max:100',
            'provinciaBarbearia' => 'nullable|string|max:100',
            'paisBarbearia' => 'nullable|string|size:2',
            'cuitBarbearia' => 'nullable|string|max:30',
            'idiomaPadrao' => 'required|in:es,pt',
        ]);

        // $assinatura = $action->handle(nome: $this->nome, email: $this->email);
        //
        // $this->stripeCustomerId = $assinatura['customerId'];
        // $this->stripeSubscriptionId = $assinatura['subscriptionId'];
        // $this->stripeClientSecret = $assinatura['clientSecret'];
        // $this->stripePublicKey = config('services.stripe.key');
        // $this->step = 'pagamento';

        $this->finalizarCadastroSemAssinatura(app(RegistrarDonoEBarbeariaAction::class));
    }

    /**
     * Substitui finalizarCadastro() enquanto a regra de planos estiver
     * desativada: cria dono+barbearia sem assinatura Stripe (a action já
     * suporta stripeCustomerId/stripeSubscriptionId nulos, marcando a
     * barbearia como 'trial').
     */
    private function finalizarCadastroSemAssinatura(RegistrarDonoEBarbeariaAction $action): void
    {
        try {
            $user = $action->handle(
                nomeDono: $this->nome,
                email: $this->email,
                senha: $this->senha,
                nomeBarbearia: $this->nomeBarbearia,
                slugBarbearia: $this->slugBarbearia,
                telefoneDono: $this->telefoneDono,
                telefoneBarbearia: $this->telefoneBarbearia,
                enderecoBarbearia: $this->enderecoBarbearia,
                cidadeBarbearia: $this->cidadeBarbearia,
                provinciaBarbearia: $this->provinciaBarbearia,
                cuitBarbearia: $this->cuitBarbearia,
                idiomaPadrao: $this->idiomaPadrao,
                paisBarbearia: $this->paisBarbearia ?: null,
            );
        } catch (QueryException $e) {
            $this->tratarConflitoDeUnicidade($e);

            return;
        }

        Auth::login($user);

        $this->redirect(route('painel'), navigate: true);
    }

    public function voltarParaDados(): void
    {
        $this->step = 'dados';
    }

    /**
     * Chamado pelo JS (resources/js/stripe-checkout.js) só depois que
     * stripe.confirmPayment() retorna sucesso. Ainda assim não confia cegamente
     * no client: reconsulta a Subscription no Stripe antes de criar a conta —
     * mesmo padrão de "nunca confiar no payload, sempre reconferir na API" do
     * webhook do Mercado Pago (ver ProcessarWebhookMercadoPagoAction).
     */
    public function finalizarCadastro(RegistrarDonoEBarbeariaAction $action, StripeService $stripe): void
    {
        if (! $this->stripeSubscriptionId) {
            $this->addError('pagamento', __('painel.erro_assinatura_generico'));

            return;
        }

        $subscription = $stripe->buscarSubscription($this->stripeSubscriptionId);

        if (! in_array($subscription->status, ['active', 'trialing'], true)) {
            Log::warning('Onboarding: tentativa de finalizar cadastro com assinatura não ativa', [
                'stripe_subscription_id' => $this->stripeSubscriptionId,
                'status' => $subscription->status,
            ]);

            $this->addError('pagamento', __('painel.erro_pagamento_nao_confirmado'));

            return;
        }

        try {
            $user = $action->handle(
                nomeDono: $this->nome,
                email: $this->email,
                senha: $this->senha,
                nomeBarbearia: $this->nomeBarbearia,
                slugBarbearia: $this->slugBarbearia,
                stripeCustomerId: $this->stripeCustomerId,
                stripeSubscriptionId: $this->stripeSubscriptionId,
                telefoneDono: $this->telefoneDono,
                telefoneBarbearia: $this->telefoneBarbearia,
                enderecoBarbearia: $this->enderecoBarbearia,
                cidadeBarbearia: $this->cidadeBarbearia,
                provinciaBarbearia: $this->provinciaBarbearia,
                cuitBarbearia: $this->cuitBarbearia,
                idiomaPadrao: $this->idiomaPadrao,
                paisBarbearia: $this->paisBarbearia ?: null,
            );
        } catch (QueryException $e) {
            // Corrida rara (pré-check em avancarParaPagamento() x INSERT
            // aqui): a assinatura Stripe já foi criada, mas a conta não vai
            // existir — cancela pra não cobrar um customer órfão.
            $stripe->cancelarSubscription($this->stripeSubscriptionId);
            $this->step = 'dados';
            $this->tratarConflitoDeUnicidade($e);

            return;
        }

        Auth::login($user);

        $this->redirect(route('painel'), navigate: true);
    }

    /**
     * A validação de unicidade em registrar() é um pré-check: nada impede
     * que outra requisição concorrente insira o mesmo email ou slug entre
     * o pré-check e o INSERT dentro da transação da action, estourando a
     * constraint UNIQUE do banco. Sem isso, esse QueryException vazava
     * como um 500 cru em vez de um erro de validação amigável.
     */
    private function tratarConflitoDeUnicidade(QueryException $e): void
    {
        if (User::where('email', $this->email)->exists()) {
            $this->addError('email', validator(
                ['email' => $this->email],
                ['email' => 'unique:users,email'],
            )->errors()->first('email'));

            return;
        }

        if (Barbearia::withoutGlobalScopes()->where('slug', $this->slugBarbearia)->exists()) {
            $this->addError('slugBarbearia', validator(
                ['slugBarbearia' => $this->slugBarbearia],
                ['slugBarbearia' => 'unique:barbearias,slug'],
            )->errors()->first('slugBarbearia'));

            return;
        }

        throw $e;
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
