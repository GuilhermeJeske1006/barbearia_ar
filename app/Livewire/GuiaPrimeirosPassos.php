<?php

namespace App\Livewire;

use App\Services\PrimeirosPassosService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Faixa exibida no topo das telas que fazem parte do guia de primeiros
 * passos (Ajustes, Profissionais, Horários, Serviços, Pagamentos, WhatsApp)
 * enquanto a configuração essencial não terminou. Liga cada tela à etapa
 * seguinte em vez de deixar o dono descobrir sozinho para onde ir.
 *
 * A rota fica gravada no mount: nas requisições de update do Livewire,
 * request()->route() já é a rota interna do Livewire, não a da página.
 */
class GuiaPrimeirosPassos extends Component
{
    #[Locked]
    public ?string $rota = null;

    public function mount(): void
    {
        $this->rota = request()->route()?->getName();
    }

    #[On('primeiros-passos-atualizado')]
    public function atualizar(): void
    {
        // Re-render recalcula o resumo com os dados recém-salvos.
    }

    public function render()
    {
        $dados = ['etapa' => null, 'resumo' => null, 'seguinte' => null];
        $user = auth()->user();

        if (
            app()->bound('barbearia')
            && array_key_exists($this->rota, PrimeirosPassosService::ROTAS_ETAPAS)
            && $user?->can('barbearia.gerenciar')
        ) {
            $servico = app(PrimeirosPassosService::class);
            $resumo = $servico->resumo($user);

            if (! $resumo['completo']) {
                $etapa = $servico->etapaDaRota($resumo, $this->rota);

                $dados = [
                    'etapa' => $etapa,
                    'resumo' => $resumo,
                    // Próxima etapa pendente que não seja a própria tela.
                    'seguinte' => collect($resumo['etapas'])->first(
                        fn (array $item) => $item['obrigatoria'] && ! $item['concluida'] && $item['chave'] !== $etapa['chave']
                    ),
                ];
            }
        }

        return view('livewire.guia-primeiros-passos', $dados);
    }
}
