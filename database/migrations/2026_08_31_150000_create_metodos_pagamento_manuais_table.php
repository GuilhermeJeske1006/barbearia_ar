<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Config genérica pra métodos de pagamento manuais (sem gateway/API) —
     * hoje só 'transferencia_alias', mas 'dados' é JSON de propósito pra
     * caber um método novo (ex.: PIX) sem migration nova. Ao contrário do
     * mp_access_token etc., que vivem direto em barbearias (1 conexão só,
     * por integração), aqui cabem N métodos por barbearia.
     */
    public function up(): void
    {
        Schema::create('metodos_pagamento_manuais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->string('tipo');
            $table->boolean('ativo')->default(false);
            $table->text('dados')->nullable();
            $table->timestamps();

            $table->unique(['barbearia_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pagamento_manuais');
    }
};
