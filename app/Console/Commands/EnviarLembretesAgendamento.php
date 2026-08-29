<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Notifications\AgendamentoLembrete;
use Illuminate\Console\Command;

/**
 * Roda periodicamente (ver bootstrap/app.php -> withSchedule) buscando
 * agendamentos confirmados que começam daqui a ~2h e ainda não receberam
 * lembrete. Janela de 20min em vez de comparar minuto exato — dá margem
 * pra tolerar o comando não rodar no segundo cravado, sem duplicar (o
 * 'lembrete_enviado_em' garante isso mesmo se a janela se sobrepuser entre
 * duas execuções).
 */
class EnviarLembretesAgendamento extends Command
{
    protected $signature = 'agendamentos:enviar-lembretes';

    protected $description = 'Envia lembrete por e-mail de agendamentos confirmados que começam em ~2h';

    private const HORAS_DE_ANTECEDENCIA = 2;

    private const JANELA_MINUTOS = 20;

    public function handle(): int
    {
        $inicioJanela = now()->addHours(self::HORAS_DE_ANTECEDENCIA)->subMinutes(self::JANELA_MINUTOS / 2);
        $fimJanela = now()->addHours(self::HORAS_DE_ANTECEDENCIA)->addMinutes(self::JANELA_MINUTOS / 2);

        // Este comando varre TODAS as barbearias numa só passada (igual
        // Super Admin) — withoutGlobalScopes() aqui é deliberado, e o
        // eager-load de 'cliente' também precisa bypassar o scope (o
        // BelongsToBarbearia é fail-closed sem tenant bindado — ver
        // docs/adr/0001), senão viria tudo nulo.
        $agendamentos = Agendamento::withoutGlobalScopes()
            ->where('status', 'confirmado')
            ->whereNull('lembrete_enviado_em')
            ->whereBetween('data_hora_inicio', [$inicioJanela, $fimJanela])
            ->with(['cliente' => fn ($query) => $query->withoutGlobalScopes()])
            ->get();

        foreach ($agendamentos as $agendamento) {
            // Rebinda o tenant por linha antes de qualquer outro acesso a
            // relação — protege contra código futuro nesse loop que espere
            // o scope normal funcionando (ex.: outra notificação, outro
            // relacionamento tenant-scoped).
            app()->instance('barbearia.id', $agendamento->barbearia_id);
            app()->instance('filial.id', $agendamento->filial_id);

            $cliente = $agendamento->cliente;

            if ($cliente->email || $cliente->telefone) {
                // AgendamentoLembrete despoja as relações do agendamento no
                // próprio construtor antes de ser enfileirada — necessário
                // porque uma relação carregada aqui viraria parte do
                // payload serializado do job e o Laravel tentaria
                // recarregá-la sozinho ao desserializar (outro processo,
                // sem tenant bindado ainda). Ver o comentário lá.
                $locale = $cliente->idioma ?? $agendamento->barbearia->idioma_padrao ?? 'es';
                $cliente->notify((new AgendamentoLembrete($agendamento))->locale($locale));
            }

            $agendamento->update(['lembrete_enviado_em' => now()]);
        }

        $this->info("Lembretes processados: {$agendamentos->count()}.");

        return self::SUCCESS;
    }
}
