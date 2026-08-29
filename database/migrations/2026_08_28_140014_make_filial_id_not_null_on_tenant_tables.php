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
            $table->dropIndex(['barbearia_id', 'telefone']);
            $table->index(['filial_id', 'telefone']);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['filial_id', 'telefone']);
            $table->index(['barbearia_id', 'telefone']);
        });

        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('filial_id')->nullable()->change();
            });
        }
    }
};
