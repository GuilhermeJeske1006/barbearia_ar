<?php

namespace App\Livewire\Admin\Onboarding;

use App\Actions\Usuarios\AlternarAtendimentoDonoAction;
use App\Models\Barbeiro;
use App\Services\PrimeirosPassosService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Guia passo a passo para deixar uma barbearia nova pronta para receber
 * reservas. Toda a lógica de "o que falta" vive em PrimeirosPassosService;
 * aqui só exibimos e oferecemos o atalho "Eu também atendo" para o dono.
 */
#[Layout('layouts::app')]
class PrimeirosPassos extends Component
{
    /**
     * true quando renderizado dentro do Painel (dashboard) até a barbearia
     * concluir a configuração: título vira h2 e etapas concluídas ficam
     * compactas. Como página própria (rota admin.primeiros-passos) é false.
     */
    #[Locked]
    public bool $embutido = false;

    /**
     * Embutido não passa pelo middleware da rota, então repete a checagem
     * de 'can:barbearia.gerenciar' aqui.
     */
    public function mount(bool $embutido = false): void
    {
        abort_unless(auth()->user()?->can('barbearia.gerenciar'), 403);

        $this->embutido = $embutido;
    }

    public function euTambemAtendo(AlternarAtendimentoDonoAction $action): void
    {
        $user = auth()->user();

        abort_unless($user?->tipo === 'dono' && $user->can('barbeiros.gerenciar'), 403);

        // Só inclui: aqui o botão nunca remove o dono da equipe (o toggle
        // completo continua em Profissionais).
        if (Barbeiro::where('user_id', $user->id)->exists()) {
            return;
        }

        $action->handle($user);

        $this->dispatch('toast',
            titulo: __('onboarding.barbeiro_salvo'),
            mensagem: __('onboarding.atendimento_dono_ativado'),
        );
        $this->dispatch('primeiros-passos-atualizado');
    }

    #[On('primeiros-passos-atualizado')]
    public function atualizar(): void
    {
        // Re-render basta: o resumo é recalculado a partir dos dados.
    }

    public function render()
    {
        return view('livewire.admin.onboarding.primeiros-passos', [
            'resumo' => app(PrimeirosPassosService::class)->resumo(auth()->user()),
            'filial' => app()->bound('filial') ? app('filial') : null,
            'multiplasFiliais' => app('barbearia')->filiais()->count() > 1,
        ]);
    }
}
