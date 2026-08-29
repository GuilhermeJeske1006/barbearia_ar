<?php

namespace App\Console\Commands;

use App\Actions\Despesa\GerarDespesasRecorrentesAction;
use Illuminate\Console\Command;

class GerarDespesasRecorrentes extends Command
{
    protected $signature = 'despesas:gerar-recorrentes';

    protected $description = 'Gera instâncias mensais de despesas recorrentes cuja próxima data já chegou';

    public function handle(GerarDespesasRecorrentesAction $action): int
    {
        $total = $action->handle();

        $this->info("Despesas recorrentes geradas: {$total}.");

        return self::SUCCESS;
    }
}
