<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento_produto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedInteger('quantidade')->default(1);
            $table->decimal('preco_cobrado', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_produto');
    }
};
