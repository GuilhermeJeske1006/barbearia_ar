<?php

namespace App\Actions\Notificacoes;

use App\Models\Agendamento;
use App\Models\User;
use App\Notifications\NovaReservaEmail;
use App\Notifications\NovaReservaRecebida;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;

class NotificarNovaReservaAction
{
    use Localizable;

    /** @var array<int, true> usuários que já receberam o e-mail desta reserva */
    private array $emailEnviado = [];

    public function handle(Agendamento $agendamento, ?int $autorId = null): void
    {
        $this->emailEnviado = [];
        $autor = $autorId ? User::find($autorId) : null;

        // PDV é venda de balcão já em curso: não vira aviso no sino da equipe.
        if ($agendamento->criado_por !== 'pdv') {
            $this->avisarEquipe($agendamento, $autorId, $autor);
        }

        // O dono recebe e-mail de toda reserva nova — online, agenda ou PDV —,
        // inclusive das que ele mesmo lançou, para ter o registro na caixa de entrada.
        $donos = User::whereIn('id', $this->idsComPapel($agendamento->barbearia_id, ['dono']))
            ->where('ativo', true)
            ->get()
            ->reject(fn (User $dono) => isset($this->emailEnviado[$dono->id]));

        foreach ($donos as $dono) {
            $aviso = (new NovaReservaRecebida($agendamento))->locale($dono->idioma ?? 'es');
            $this->enviarEmail($dono, $agendamento, $aviso, $autor);
        }
    }

    private function avisarEquipe(Agendamento $agendamento, ?int $autorId, ?User $autor): void
    {
        // Na agenda manual, quem criou já sabe — o sino avisa só os demais.
        $ids = $this->idsComPapel($agendamento->barbearia_id, ['dono', 'atendente'])
            ->reject(fn (int $id) => $id === $autorId);

        $barbeiroUserId = $agendamento->barbeiro->user_id;
        if ($barbeiroUserId && (int) $barbeiroUserId !== $autorId && ! $ids->contains($barbeiroUserId)) {
            $barbeiroUser = User::whereKey($barbeiroUserId)->where('ativo', true)->first();
            $temAcesso = $this->idsComPapel($agendamento->barbearia_id, ['barbeiro'])->contains((int) $barbeiroUserId);
            if ($barbeiroUser && $temAcesso) {
                $this->enviar($barbeiroUser, $agendamento, $autor, propriaAgenda: true);
            }
        }

        foreach (User::whereIn('id', $ids)->where('ativo', true)->get() as $usuario) {
            $this->enviar($usuario, $agendamento, $autor);
        }
    }

    /**
     * A reserva pública não possui sessão nem contexto de permissões do dono,
     * por isso a consulta vai direto na tabela de papéis do tenant.
     *
     * @param  list<string>  $papeis
     * @return Collection<int, int>
     */
    private function idsComPapel(int $barbeariaId, array $papeis): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('model_has_roles.barbearia_id', $barbeariaId)
            ->whereIn('roles.name', $papeis)
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function enviar(User $usuario, Agendamento $agendamento, ?User $autor, bool $propriaAgenda = false): void
    {
        $aviso = (new NovaReservaRecebida($agendamento, $propriaAgenda))->locale($usuario->idioma ?? 'es');
        $usuario->notify($aviso);
        $this->enviarEmail($usuario, $agendamento, $aviso, $autor);
    }

    private function enviarEmail(User $usuario, Agendamento $agendamento, NovaReservaRecebida $aviso, ?User $autor): void
    {
        if (! $usuario->email) {
            return;
        }

        $this->emailEnviado[$usuario->id] = true;
        $locale = $usuario->idioma ?? 'es';
        $dados = $this->withLocale($locale, fn () => $aviso->toArray($usuario) + [
            'origem' => $this->origem($agendamento, $autor),
            'assunto' => __($agendamento->criado_por === 'pdv'
                ? 'notificacoes.novo_atendimento_pdv_email_assunto'
                : 'notificacoes.nova_reserva_email_assunto', ['barbearia' => $agendamento->barbearia->nome]),
        ]);
        $email = (new NovaReservaEmail($dados, $agendamento->barbearia->nome))->locale($locale);

        DB::afterCommit(function () use ($usuario, $email) {
            try {
                $usuario->notify($email);
            } catch (\Throwable $e) {
                // O aviso no sino e a reserva já estão salvos, mesmo se o e-mail falhar.
                report($e);
            }
        });
    }

    private function origem(Agendamento $agendamento, ?User $autor): string
    {
        $origem = __(match ($agendamento->criado_por) {
            'cliente_online' => 'notificacoes.origem_online',
            'pdv' => 'notificacoes.origem_pdv',
            default => 'notificacoes.origem_agenda',
        });

        return $autor && $agendamento->criado_por !== 'cliente_online'
            ? $origem.' · '.__('notificacoes.lancado_por', ['nome' => $autor->name])
            : $origem;
    }
}
