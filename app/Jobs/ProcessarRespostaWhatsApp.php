<?php

namespace App\Jobs;

use App\Actions\Notificacoes\ProcessarRespostaPesquisaSatisfacaoAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarRespostaWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $barbeariaId,
        public readonly string $telefone,
        public readonly string $mensagem,
    ) {}

    public function handle(ProcessarRespostaPesquisaSatisfacaoAction $action): void
    {
        $action->handle($this->barbeariaId, $this->telefone, $this->mensagem);
    }
}
