<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('unidade_consumo', 2)->default('un');
            $table->decimal('conteudo_embalagem', 12, 2)->nullable();
            $table->decimal('saldo_aberto', 14, 2)->default(0);
        });
        Schema::table('servico_produto', function (Blueprint $table) {
            $table->decimal('quantidade_fracionada', 12, 2)->nullable();
        });
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->string('unidade', 2)->default('un');
            $table->decimal('quantidade', 14, 2)->change();
            $table->decimal('estoque_resultante', 14, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropColumn('unidade');
            $table->integer('quantidade')->change();
            $table->integer('estoque_resultante')->change();
        });
        Schema::table('servico_produto', fn (Blueprint $table) => $table->dropColumn('quantidade_fracionada'));
        Schema::table('produtos', fn (Blueprint $table) => $table->dropColumn(['unidade_consumo', 'conteudo_embalagem', 'saldo_aberto']));
    }
};
