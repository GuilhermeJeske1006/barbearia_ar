<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servico_produto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->unsignedInteger('quantidade_consumida')->default(1);
            $table->timestamps();

            $table->unique(['servico_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_produto');
    }
};
