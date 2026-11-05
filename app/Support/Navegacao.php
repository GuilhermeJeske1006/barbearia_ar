<?php

namespace App\Support;

class Navegacao
{
    public static function titulo(?string $rota): string
    {
        $titulos = [
            'painel' => 'painel.painel',
            'perfil' => 'painel.perfil',
            'admin.primeiros-passos' => 'onboarding.titulo',
            'admin.agenda' => 'painel.agenda',
            'pdv' => 'pdv.titulo',
            'admin.barbeiros' => 'painel.barberos',
            'admin.barbeiros.horarios' => 'painel.horarios_barbero',
            'admin.barbeiros.bloqueios' => 'painel.bloqueios',
            'admin.servicos' => 'painel.servicios',
            'admin.produtos' => 'painel.productos',
            'admin.produtos.estoque' => 'fluxos.estoque',
            'admin.clientes' => 'painel.clientes',
            'admin.usuarios' => 'painel.usuarios',
            'admin.permissoes' => 'painel.permissoes',
            'admin.configuracoes' => 'painel.ajustes',
            'admin.filiais' => 'painel.filiais',
            'admin.pagamentos' => 'painel.pagamentos_titulo',
            'admin.pagamentos-pendentes' => 'painel.pagamentos_pendentes_titulo',
            'admin.whatsapp' => 'painel.whatsapp_titulo',
            'admin.assinatura' => 'painel.assinatura_titulo',
            'admin.despesas' => 'painel.despesas',
            'admin.relatorios.comissoes' => 'painel.comisiones',
            'admin.relatorios.despesas' => 'painel.relatorio_despesas',
            'admin.relatorios.lucro' => 'painel.relatorio_lucro',
            'barbeiro.minhas-comissoes' => 'painel.comisiones',
            'barbeiro.minha-agenda' => 'painel.agenda',
            'barbeiro.meus-horarios' => 'painel.horarios_barbero',
        ];

        return __($titulos[$rota] ?? 'painel.painel');
    }

    /**
     * Pai do breadcrumb só quando o usuário consegue abri-lo — telas como
     * Assinatura (sem permissão) ou Relatório de despesas (só visualizar)
     * têm pai que exige permissão maior, e o link daria 403.
     */
    public static function paiAcessivel(?string $rota): ?string
    {
        $pai = self::pai($rota);

        if (! $pai) {
            return null;
        }

        $permissao = [
            'admin.barbeiros' => 'barbeiros.gerenciar',
            'admin.produtos' => 'produtos.gerenciar',
            'admin.usuarios' => 'usuarios.gerenciar',
            'admin.configuracoes' => 'barbearia.gerenciar',
            'admin.despesas' => 'financeiro.gerenciar',
        ][$pai] ?? null;

        return $permissao === null || auth()->user()?->can($permissao) ? $pai : null;
    }

    public static function pai(?string $rota): ?string
    {
        return match ($rota) {
            'admin.barbeiros.horarios', 'admin.barbeiros.bloqueios' => 'admin.barbeiros',
            'admin.produtos.estoque' => 'admin.produtos',
            'admin.permissoes' => 'admin.usuarios',
            'admin.pagamentos', 'admin.whatsapp', 'admin.filiais', 'admin.assinatura' => 'admin.configuracoes',
            'admin.relatorios.despesas' => 'admin.despesas',
            default => null,
        };
    }
}
