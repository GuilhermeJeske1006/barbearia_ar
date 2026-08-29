<?php

namespace App\Livewire\Public;

use App\Actions\Notificacoes\NotificarMinhasReservasLinkAction;
use App\Models\Cliente;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formulário público: cliente digita o telefone, recebemos e disparamos (por
 * e-mail e/ou WhatsApp) um link assinado pra MinhasReservasLista com todas
 * as reservas casadas com esse número. Sempre responde igual, ache ou não —
 * não vaza se um telefone está cadastrado (mesmo espírito de "esqueci minha
 * senha" em apps com login de verdade).
 */
#[Layout('layouts::publico')]
class MinhasReservasBusca extends Component
{
    public string $telefone = '';

    public bool $enviado = false;

    public function buscar(NotificarMinhasReservasLinkAction $notificar): void
    {
        $throttleKey = 'minhas-reservas-buscar:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('telefone', __('agendamento.muitas_tentativas', [
                'segundos' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $this->validate(['telefone' => 'required|string|max:30']);

        $normalizado = Cliente::normalizarTelefone($this->telefone);

        if ($normalizado !== '') {
            // withoutGlobalScope('filial'): esta é uma rota pública anônima
            // (prefix 'b/{barbearia}', sem segmento de filial) — filial.id
            // NUNCA é bindado aqui (ResolveFilial só roda pra usuário
            // autenticado), então o scope 'filial' fail-closed sempre
            // devolveria vazio sem isto. O scope 'barbearia' continua ativo
            // (já bindado certo pelo ResolveTenant) e SoftDeletes continua
            // excluindo clientes soft-deleted normalmente — só 'filial' é
            // removido.
            $clientes = Cliente::withoutGlobalScope('filial')
                ->get(['id', 'telefone', 'nome', 'email', 'idioma', 'barbearia_id', 'filial_id'])
                ->filter(fn (Cliente $c) => Cliente::normalizarTelefone($c->telefone) === $normalizado);

            if ($clientes->isNotEmpty()) {
                try {
                    $notificar->handle($clientes, $normalizado);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->enviado = true;
    }

    public function render()
    {
        return view('livewire.public.minhas-reservas-busca');
    }
}
