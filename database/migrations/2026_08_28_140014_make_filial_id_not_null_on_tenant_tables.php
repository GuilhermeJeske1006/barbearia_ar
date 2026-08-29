<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABELAS = [
        'servicos', 'produtos', 'barbeiros', 'barbeiro_horarios', 'barbeiro_bloqueios',
        'clientes', 'agendamentos', 'pagamentos', 'comissoes', 'pesquisas_satisfacao',
        'movimentacoes_estoque',
    ];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('filial_id')->nullable(false)->change();
            });
        }

        Schema::table('clientes', function (Blueprint $table) {
            // No MySQL, a FK de barbearia_id depende desse índice composto
            // pra existir (é o único índice cobrindo a coluna) — dropar
            // direto falha com "needed in a foreign key constraint". Um
            // índice simples em barbearia_id assume essa dependência antes,
            // liberando o composto pra ser removido.
            $table->index('barbearia_id');
            $table->dropIndex(['barbearia_id', 'telefone']);
            $table->index(['filial_id', 'telefone']);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Mesmo problema do up(), na direção oposta: o composto
            // (filial_id, telefone) é o único índice cobrindo a FK de
            // filial_id — precisa de um índice simples assumindo a
            // dependência antes de poder ser removido.
            $table->index('filial_id');
            $table->dropIndex(['filial_id', 'telefone']);
            $table->index(['barbearia_id', 'telefone']);
            // Agora coberto de novo pelo composto acima — libera pra remover.
            $table->dropIndex(['barbearia_id']);
        });

        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('filial_id')->nullable()->change();
            });
        }
    }
};
