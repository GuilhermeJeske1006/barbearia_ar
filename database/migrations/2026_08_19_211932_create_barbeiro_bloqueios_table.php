<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbeiro_bloqueios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbeiro_id')->constrained('barbeiros')->cascadeOnDelete();
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbeiro_bloqueios');
    }
};
