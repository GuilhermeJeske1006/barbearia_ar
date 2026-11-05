<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Guia de configuração inicial ("primeiros passos") de uma barbearia nova.
 *
 * O estado de cada etapa é derivado dos dados reais da barbearia e da filial
 * atual — não há flag "concluído" gravada. Assim o guia nunca fica
 * dessincronizado: desativar o único profissional, por exemplo, reabre a
 * etapa e a página de reservas volta a aparecer como "não pronta".
 *
 * Os critérios espelham o que Public\AgendamentoWizard exige para oferecer
 * horário: serviço ativo, vinculado a profissional ativo que aceita reserva
 * online e tem escala cadastrada.
 */
class PrimeirosPassosService
{
    /** Rota da tela → etapa do guia, usada pela faixa contextual. */
    public const ROTAS_ETAPAS = [
        'admin.configuracoes' => 'dados',
        'admin.barbeiros' => 'equipe',
        'admin.barbeiros.horarios' => 'horarios',
        'admin.servicos' => 'servicos',
        'admin.pagamentos' => 'pagamentos',
        'admin.whatsapp' => 'whatsapp',
    ];

    private const LIMITE_DETALHES = 5;

    /**
     * @return array{
     *     etapas: list<array<string, mixed>>,
     *     feitas: int,
     *     total: int,
     *     pronta: bool,
     *     completo: bool,
     *     proxima: ?array<string, mixed>,
     * }
     */
    public function resumo(User $user): array
    {
        /** @var Barbearia $barbearia */
        $barbearia = app('barbearia');

        $barbeirosAtivos = Barbeiro::where('ativo', true)
            ->withCount('horarios')
            ->orderBy('nome')
            ->get(['id', 'nome', 'user_id', 'aceita_online']);

        $servicosAtivos = Servico::where('ativo', true)
            ->withCount(['barbeiros as barbeiros_ativos_count' => fn ($query) => $query->where('ativo', true)])
            ->get(['id', 'nome']);

        $pronta = $this->filialAtiva() && Barbeiro::where('ativo', true)
            ->where('aceita_online', true)
            ->whereHas('horarios')
            ->whereHas('servicos', fn ($query) => $query->where('ativo', true))
            ->exists();

        $etapas = [
            $this->etapaDados($user, $barbearia),
            $this->etapaEquipe($user, $barbeirosAtivos),
            $this->etapaHorarios($user, $barbeirosAtivos),
            $this->etapaServicos($user, $servicosAtivos),
            $this->etapaPagamentos($user, $barbearia),
            $this->etapaWhatsApp($user, $barbearia),
            $this->etapaDivulgar($barbearia, $pronta),
        ];

        foreach ($etapas as $indice => $etapa) {
            $etapas[$indice]['numero'] = $indice + 1;
        }

        $pendenteObrigatoria = collect($etapas)->first(fn (array $etapa) => $etapa['obrigatoria'] && ! $etapa['concluida']);

        // "Completo" = configuração essencial feita e página aceitando
        // reservas. Não espera o primeiro agendamento: isso depende do
        // cliente, e manter faixas e cards até lá seria ruído. Divulgar o
        // link continua sugerido como próxima etapa na tela do guia.
        $completo = $pendenteObrigatoria === null && $pronta;
        $divulgar = collect($etapas)->firstWhere('chave', 'divulgar');

        return [
            'etapas' => $etapas,
            'feitas' => collect($etapas)->where('concluida', true)->count(),
            'total' => count($etapas),
            'pronta' => $pronta,
            'completo' => $completo,
            'proxima' => $pendenteObrigatoria ?? ($pronta && ! $divulgar['concluida'] ? $divulgar : null),
        ];
    }

    /**
     * Etapa ligada à tela atual, para a faixa contextual do layout.
     *
     * @param  array<string, mixed>  $resumo
     * @return array<string, mixed>|null
     */
    public function etapaDaRota(array $resumo, ?string $rota): ?array
    {
        $chave = self::ROTAS_ETAPAS[$rota] ?? null;

        return $chave
            ? collect($resumo['etapas'])->firstWhere('chave', $chave)
            : null;
    }

    private function filialAtiva(): bool
    {
        return ! app()->bound('filial') || app('filial')->ativo !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function etapa(string $chave, bool $obrigatoria, bool $concluida, ?array $acao, array $extras = []): array
    {
        return [
            'chave' => $chave,
            'titulo' => __("onboarding.passo_{$chave}_titulo"),
            'descricao' => __("onboarding.passo_{$chave}_descricao"),
            'obrigatoria' => $obrigatoria,
            'concluida' => $concluida,
            'bloqueada' => false,
            'motivo_bloqueio' => null,
            'acao' => $acao,
            'acoes_extras' => [],
            'detalhes' => [],
            ...$extras,
        ];
    }

    /**
     * @return array{rotulo: string, url: string, externa: bool}|null
     */
    private function acao(User $user, ?string $permissao, string $rotulo, string $url, bool $externa = false): ?array
    {
        if ($permissao && ! $user->can($permissao)) {
            return null;
        }

        return ['rotulo' => $rotulo, 'url' => $url, 'externa' => $externa];
    }

    private function etapaDados(User $user, Barbearia $barbearia): array
    {
        $faltando = collect([
            __('painel.telefone') => $barbearia->telefone,
            __('painel.endereco') => $barbearia->endereco,
        ])->filter(fn ($valor) => blank($valor))->keys();

        return $this->etapa('dados', true, $faltando->isEmpty(),
            $this->acao($user, 'barbearia.gerenciar', __('onboarding.passo_dados_acao'), route('admin.configuracoes')),
            [
                'detalhes' => $faltando->isEmpty() ? [] : [[
                    'texto' => __('onboarding.passo_dados_falta', ['campos' => $faltando->join(', ')]),
                ]],
            ],
        );
    }

    /**
     * @param  Collection<int, Barbeiro>  $barbeirosAtivos
     */
    private function etapaEquipe(User $user, Collection $barbeirosAtivos): array
    {
        $donoForaDaEquipe = $user->tipo === 'dono'
            && $user->can('barbeiros.gerenciar')
            && ! Barbeiro::where('user_id', $user->id)->exists();

        return $this->etapa('equipe', true, $barbeirosAtivos->isNotEmpty(),
            $this->acao($user, 'barbeiros.gerenciar', __('onboarding.passo_equipe_acao'), route('admin.barbeiros', ['novo' => 1])),
            [
                'dono_fora_da_equipe' => $donoForaDaEquipe,
                'detalhes' => $barbeirosAtivos->isEmpty() ? [] : [[
                    'texto' => __('onboarding.passo_equipe_total', ['total' => $barbeirosAtivos->count()]),
                    'url' => $user->can('barbeiros.gerenciar') ? route('admin.barbeiros') : null,
                ]],
            ],
        );
    }

    /**
     * @param  Collection<int, Barbeiro>  $barbeirosAtivos
     */
    private function etapaHorarios(User $user, Collection $barbeirosAtivos): array
    {
        $semHorario = $barbeirosAtivos->where('horarios_count', 0)->values();
        $podeGerenciar = $user->can('barbeiros.gerenciar');

        $destino = $semHorario->first() ?? $barbeirosAtivos->first();

        return $this->etapa('horarios', true, $barbeirosAtivos->contains(fn (Barbeiro $b) => $b->horarios_count > 0),
            $destino ? $this->acao($user, 'barbeiros.gerenciar', __('onboarding.passo_horarios_acao'), route('admin.barbeiros.horarios', $destino)) : null,
            [
                'bloqueada' => $barbeirosAtivos->isEmpty(),
                'motivo_bloqueio' => $barbeirosAtivos->isEmpty() ? __('onboarding.passo_horarios_requer') : null,
                'detalhes' => $semHorario->isEmpty() ? [] : [[
                    'texto' => __('onboarding.passo_horarios_sem_horario'),
                    'links' => $semHorario->take(self::LIMITE_DETALHES)
                        ->map(fn (Barbeiro $barbeiro) => [
                            'rotulo' => $barbeiro->nome,
                            'url' => $podeGerenciar ? route('admin.barbeiros.horarios', $barbeiro) : null,
                        ])->all(),
                ]],
            ],
        );
    }

    /**
     * @param  Collection<int, Servico>  $servicosAtivos
     */
    private function etapaServicos(User $user, Collection $servicosAtivos): array
    {
        $semProfissional = $servicosAtivos->where('barbeiros_ativos_count', 0)->count();

        return $this->etapa('servicos', true, $servicosAtivos->contains(fn (Servico $s) => $s->barbeiros_ativos_count > 0),
            $this->acao($user, 'servicos.gerenciar', __('onboarding.passo_servicos_acao'), route('admin.servicos', ['novo' => 1])),
            [
                // Não bloqueia sem equipe: o serviço pode ser cadastrado antes
                // e vinculado depois; o aviso abaixo mostra o que falta.
                'detalhes' => $semProfissional === 0 ? [] : [[
                    'texto' => __('onboarding.passo_servicos_sem_profissional', ['total' => $semProfissional]),
                    'url' => $user->can('servicos.gerenciar') ? route('admin.servicos') : null,
                    'rotulo' => __('onboarding.passo_servicos_revisar'),
                ]],
            ],
        );
    }

    private function etapaPagamentos(User $user, Barbearia $barbearia): array
    {
        return $this->etapa('pagamentos', false,
            $barbearia->conectadaAoMercadoPago() || $barbearia->metodoTransferenciaAtivo() !== null,
            $this->acao($user, 'barbearia.gerenciar', __('onboarding.passo_pagamentos_acao'), route('admin.pagamentos')),
        );
    }

    private function etapaWhatsApp(User $user, Barbearia $barbearia): array
    {
        return $this->etapa('whatsapp', false,
            $barbearia->status_conexao_whatsapp === Barbearia::STATUS_WHATSAPP_CONECTADO,
            $this->acao($user, 'barbearia.gerenciar', __('onboarding.passo_whatsapp_acao'), route('admin.whatsapp')),
        );
    }

    private function etapaDivulgar(Barbearia $barbearia, bool $pronta): array
    {
        $concluida = Agendamento::exists();

        return $this->etapa('divulgar', false, $concluida,
            ['rotulo' => __('onboarding.passo_divulgar_acao'), 'url' => route('public.agendamento', $barbearia->slug), 'externa' => true],
            [
                'bloqueada' => ! $pronta && ! $concluida,
                'motivo_bloqueio' => ! $pronta && ! $concluida ? __('onboarding.passo_divulgar_requer') : null,
                'link_publico' => route('public.agendamento', $barbearia->slug),
                // Não bloqueia o "completo", mas não é opcional como
                // pagamentos/WhatsApp: é o passo que traz o primeiro cliente.
                'final' => true,
            ],
        );
    }
}
