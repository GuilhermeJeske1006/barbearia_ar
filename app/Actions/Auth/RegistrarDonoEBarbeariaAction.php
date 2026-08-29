<?php

namespace App\Actions\Auth;

use App\Models\Barbearia;
use App\Models\Filial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Onboarding self-service: quem se registra vira o 'dono' de uma barbearia
 * nova, criada na mesma transação. Não passa pelo CreateNewUser do Fortify —
 * ver docs/adr/0003 (não, esse é sobre split; a decisão de registro fica
 * registrada aqui: registro cria dono + barbearia juntos, não é um convite).
 */
class RegistrarDonoEBarbeariaAction
{
    /**
     * stripeCustomerId/stripeSubscriptionId nullable: o fluxo real de
     * onboarding (Livewire\Auth\Register::finalizarCadastro) sempre os
     * passa, depois do checkout transparente confirmar o pagamento — sem
     * trial, sem barbearia "fantasma" sem assinatura por trás. Ficam
     * opcionais aqui só para não quebrar os vários testes de outras
     * features que chamam essa action direto pra montar um fixture de
     * barbearia+dono sem precisar simular o Stripe.
     */
    public function handle(
        string $nomeDono,
        string $email,
        string $senha,
        string $nomeBarbearia,
        string $slugBarbearia,
        ?string $stripeCustomerId = null,
        ?string $stripeSubscriptionId = null,
        string $telefoneDono = '',
        string $telefoneBarbearia = '',
        string $enderecoBarbearia = '',
        string $cidadeBarbearia = '',
        string $provinciaBarbearia = '',
        string $cuitBarbearia = '',
        string $idiomaPadrao = 'pt',
    ): User {
        return DB::transaction(function () use (
            $nomeDono, $email, $senha, $telefoneDono,
            $nomeBarbearia, $slugBarbearia, $telefoneBarbearia,
            $enderecoBarbearia, $cidadeBarbearia, $provinciaBarbearia,
            $cuitBarbearia, $idiomaPadrao,
            $stripeCustomerId, $stripeSubscriptionId,
        ) {
            $temAssinaturaStripe = $stripeCustomerId && $stripeSubscriptionId;

            $barbearia = Barbearia::create([
                'nome' => $nomeBarbearia,
                'slug' => $slugBarbearia,
                'telefone' => $telefoneBarbearia ?: null,
                'endereco' => $enderecoBarbearia ?: null,
                'cidade' => $cidadeBarbearia ?: null,
                'provincia' => $provinciaBarbearia ?: null,
                'cuit' => $cuitBarbearia ?: null,
                'idioma_padrao' => $idiomaPadrao,
                'status' => $temAssinaturaStripe ? 'ativa' : 'trial',
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'subscription_status' => $temAssinaturaStripe ? 'active' : null,
            ]);

            // Toda barbearia nasce com uma filial "Matriz" — sem isso não
            // haveria onde estampar barbeiros/clientes/agendamentos, já
            // que filial_id é obrigatório em todo dado tenant-scoped.
            $filial = Filial::create([
                'barbearia_id' => $barbearia->id,
                'nome' => 'Matriz',
                'endereco' => $enderecoBarbearia ?: null,
                'cidade' => $cidadeBarbearia ?: null,
                'provincia' => $provinciaBarbearia ?: null,
                'telefone' => $telefoneBarbearia ?: null,
            ]);

            $user = User::create([
                'name' => $nomeDono,
                'email' => $email,
                'password' => Hash::make($senha),
                'telefone' => $telefoneDono ?: null,
                'tipo' => 'dono',
                'idioma' => $idiomaPadrao,
                'barbearia_atual_id' => $barbearia->id,
                'filial_atual_id' => $filial->id,
                'ativo' => true,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
            $user->assignRole('dono');

            return $user;
        });
    }
}
