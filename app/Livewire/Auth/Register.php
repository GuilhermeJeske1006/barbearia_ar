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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?string $stripeCustomerId = null;

    #[Locked]
    public ?string $stripeSubscriptionId = null;

    #[Locked]
    public ?string $stripeClientSecret = null;

    #[Locked]
    public ?string $stripePublicKey = null;

    /**
     * Etapas visuais dentro do passo 'dados': 1 conta do dono, 2 identidade
     * da barbearia, 3 localização e idioma, 4 revisão. Cada avanço valida só
     * os campos da etapa atual; o envio final (avancarParaPagamento) valida
     * tudo de novo e volta para a etapa do primeiro campo com erro.
     */
    public const TOTAL_ETAPAS = 4;

    #[Locked]
    public int $etapa = 1;

    /**
     * A URL pública acompanha o nome enquanto ainda for a gerada a partir do
     * nome anterior; editada à mão, deixa de acompanhar. Usa o hook
     * "updating" (valor antigo ainda no componente) porque o Livewire 4 só
     * roda os "updated" depois de aplicar todos os campos da requisição — um
     * updatedNomeBarbearia sobrescrevia a URL enviada junto com o nome.
     */
    public function updatingNomeBarbearia(mixed $valor): void
    {
        if ($this->slugBarbearia === Str::slug($this->nomeBarbearia)) {
            $this->slugBarbearia = Str::slug((string) $valor);
        }
    }

    public function updatedSlugBarbearia(): void
    {
        $this->slugBarbearia = Str::slug($this->slugBarbearia);
    }

    /**
     * @return array<string, mixed>
     */
    private function regrasDaEtapa(int $etapa): array
    {
        return match ($etapa) {
            1 => [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'telefoneDono' => 'nullable|string|max:30',
                'senha' => ['required', 'string', Password::default(), 'confirmed'],
            ],
            2 => [
                'nomeBarbearia' => 'required|string|max:255',
                'slugBarbearia' => 'required|string|max:255|alpha_dash|unique:barbearias,slug',
                'telefoneBarbearia' => 'nullable|string|max:30',
                'cuitBarbearia' => 'nullable|string|max:30',
            ],
            3 => [
                'enderecoBarbearia' => 'nullable|string|max:255',
                'cidadeBarbearia' => 'nullable|string|max:100',
                'provinciaBarbearia' => 'nullable|string|max:100',
                'paisBarbearia' => 'nullable|string|size:2',
                'idiomaPadrao' => 'required|in:es,pt',
            ],
            default => [],
        };
    }

    /**
     * Rótulos dos campos nas mensagens de validação — sem isso o Laravel
     * usava o nome interno ("slug barbearia já está em uso").
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nome' => __('painel.nome_completo'),
            'email' => __('painel.email'),
            'telefoneDono' => __('painel.telefone'),
            'senha' => __('painel.senha'),
            'nomeBarbearia' => __('painel.nome_barbearia'),
            'slugBarbearia' => __('painel.url_barbearia'),
            'telefoneBarbearia' => __('painel.telefone'),
            'cuitBarbearia' => __('painel.cuit'),
            'enderecoBarbearia' => __('painel.endereco'),
            'cidadeBarbearia' => __('painel.cidade'),
            'provinciaBarbearia' => __('painel.provincia'),
            'paisBarbearia' => __('painel.pais'),
            'idiomaPadrao' => __('painel.idioma_padrao'),
        ];
    }

    private function etapaDoCampo(string $campo): int
    {
        foreach (range(1, self::TOTAL_ETAPAS - 1) as $etapa) {
            if (array_key_exists($campo, $this->regrasDaEtapa($etapa))) {
                return $etapa;
            }
        }

        return self::TOTAL_ETAPAS;
    }

    /** Enter no formulário: avança nas etapas 1-3 e cria a conta na revisão. */
    public function submeter(): void
    {
        $this->etapa < self::TOTAL_ETAPAS
            ? $this->avancarEtapa()
            : $this->avancarParaPagamento();
    }

    public function avancarEtapa(): void
    {
        $regras = $this->regrasDaEtapa($this->etapa);

        if ($regras !== []) {
            $this->validate($regras);
        }

        $this->etapa = min($this->etapa + 1, self::TOTAL_ETAPAS);
        $this->dispatch('etapa-cadastro-alterada');
    }

    public function voltarEtapa(): void
    {
        $this->resetValidation();
        $this->etapa = max($this->etapa - 1, 1);
        $this->dispatch('etapa-cadastro-alterada');
    }

    /** Só volta: pular adiante deixaria etapas sem validação. */
    public function irParaEtapa(int $etapa): void
    {
        if ($etapa >= 1 && $etapa < $this->etapa) {
            $this->resetValidation();
            $this->etapa = $etapa;
            $this->dispatch('etapa-cadastro-alterada');
        }
    }

    /**
     * @param  array<string, mixed>  $regras
     */
    private function validarTudo(array $regras): void
    {
        try {
            $this->validate($regras);
        } catch (ValidationException $e) {
            $primeiroCampo = array_key_first($e->validator->errors()->messages());
            $this->etapa = $this->etapaDoCampo((string) $primeiroCampo);
            $this->dispatch('etapa-cadastro-alterada');

            throw $e;
        }
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
        $this->validarTudo([
            ...$this->regrasDaEtapa(1),
            ...$this->regrasDaEtapa(2),
            ...$this->regrasDaEtapa(3),
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

        // Conta nova cai no guia de configuração, não num painel vazio.
        $this->redirect(route('admin.primeiros-passos'), navigate: true);
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

        // Conta nova cai no guia de configuração, não num painel vazio.
        $this->redirect(route('admin.primeiros-passos'), navigate: true);
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
            $this->etapa = $this->etapaDoCampo('email');
            $this->dispatch('etapa-cadastro-alterada');

            return;
        }

        if (Barbearia::withoutGlobalScopes()->where('slug', $this->slugBarbearia)->exists()) {
            $this->addError('slugBarbearia', validator(
                ['slugBarbearia' => $this->slugBarbearia],
                ['slugBarbearia' => 'unique:barbearias,slug'],
            )->errors()->first('slugBarbearia'));
            $this->etapa = $this->etapaDoCampo('slugBarbearia');
            $this->dispatch('etapa-cadastro-alterada');

            return;
        }

        throw $e;
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
