<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * barbeiros.barbeiro_id/agendamentos.cliente_id/comissoes.barbeiro_id
     * usam cascadeOnDelete: excluir um Barbeiro ou Cliente hoje apaga em
     * cascata agendamentos, comissões e pesquisas de satisfação — histórico
     * financeiro/auditoria. Com SoftDeletes, Model::delete() só marca
     * deleted_at, sem disparar a cascade do banco.
     */
    public function up(): void
    {
        Schema::table('barbeiros', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barbeiros', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
