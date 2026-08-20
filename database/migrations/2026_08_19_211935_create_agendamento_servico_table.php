<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos');
            $table->decimal('preco_cobrado', 10, 2);
            $table->decimal('percentual_comissao_aplicado', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_servico');
    }
};
