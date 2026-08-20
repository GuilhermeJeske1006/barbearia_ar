<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbeiro_id')->constrained('barbeiros')->cascadeOnDelete();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            $table->enum('status', ['pendente', 'pago', 'estornado'])->default('pendente');
            $table->date('data_referencia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comissoes');
    }
};
