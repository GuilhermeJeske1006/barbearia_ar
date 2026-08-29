<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbeiro_servico', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracao_minutos_override')->nullable()->after('percentual_comissao_override');
        });
    }

    public function down(): void
    {
        Schema::table('barbeiro_servico', function (Blueprint $table) {
            $table->dropColumn('duracao_minutos_override');
        });
    }
};
