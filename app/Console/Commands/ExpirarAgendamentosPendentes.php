<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use Illuminate\Console\Command;

/**
 * Rede de segurança pra reserva 'pendente' órfã: o próprio código já cancela
 * o agendamento quando a criação da preferência MP falha na hora (ver
 * AgendamentoWizard::confirmar e TelaVendaDireta::finalizar), mas isso não
 * cobre o cliente que simplesmente fecha a aba/desiste no meio do checkout
 * hospedado da MP sem nenhum erro acontecer no nosso lado — nesse caso o
 * agendamento fica 'pendente' pra sempre, ocupando o horário do barbeiro.
 */
class ExpirarAgendamentosPendentes extends Command
{
    protected $signature = 'agendamentos:expirar-pendentes';

    protected $description = 'Cancela agendamentos pendentes de pagamento há mais de 30 minutos, liberando o horário';

    private const MINUTOS_DE_TOLERANCIA = 30;

    public function handle(): int
    {
        // Varre todas as barbearias numa só passada — mesmo padrão de
        // EnviarLembretesAgendamento.
        $expirados = Agendamento::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->where('created_at', '<', now()->subMinutes(self::MINUTOS_DE_TOLERANCIA))
            ->get();

        foreach ($expirados as $agendamento) {
            $agendamento->update(['status' => 'cancelado']);
        }

        $this->info("Agendamentos pendentes expirados: {$expirados->count()}.");

        return self::SUCCESS;
    }
}
