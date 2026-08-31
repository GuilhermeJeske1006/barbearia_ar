<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separado de 'pagamentos' (e não um único path na própria linha) porque
     * um comprovante recusado pode ser reenviado — cada envio vira uma nova
     * linha, preservando o histórico de tentativas em vez de sobrescrever.
     * Sem barbearia_id próprio de propósito: acesso sempre passa pelo
     * Pagamento (já BelongsToBarbearia), que é quem decide o tenant.
     */
    public function up(): void
    {
        Schema::create('comprovantes_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->cascadeOnDelete();
            $table->string('path');
            $table->string('nome_original');
            $table->string('mime');
            $table->unsignedInteger('tamanho');
            $table->dateTime('enviado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprovantes_pagamento');
    }
};
