<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbeiro_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbeiro_id')->constrained('barbeiros')->cascadeOnDelete();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->time('intervalo_inicio')->nullable();
            $table->time('intervalo_fim')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbeiro_horarios');
    }
};
