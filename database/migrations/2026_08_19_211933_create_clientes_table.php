<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->string('nome');
            $table->string('telefone');
            $table->string('email')->nullable();
            $table->string('dni')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('idioma', ['es', 'pt'])->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['barbearia_id', 'telefone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
