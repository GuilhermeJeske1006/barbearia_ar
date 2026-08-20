<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->decimal('valor_total', 10, 2);
            $table->decimal('valor_comissao_barbeiro', 10, 2)->nullable();
            $table->decimal('valor_barbearia', 10, 2)->nullable();
            $table->enum('metodo', ['mp_checkout', 'mp_point', 'dinheiro', 'outro']);
            $table->string('mp_payment_id')->nullable()->unique();
            $table->string('mp_preference_id')->nullable();
            $table->string('mp_status')->nullable();
            $table->string('mp_split_status')->nullable();
            $table->enum('forma_split', ['marketplace_auto', 'manual'])->default('manual');
            $table->dateTime('pago_em')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
