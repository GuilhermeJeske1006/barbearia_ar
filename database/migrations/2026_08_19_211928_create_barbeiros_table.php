<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbeiros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->string('nome');
            $table->string('foto_path')->nullable();
            $table->decimal('percentual_comissao', 5, 2);
            $table->string('mp_user_id')->nullable();
            $table->text('mp_access_token')->nullable();
            $table->text('mp_refresh_token')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('aceita_online')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbeiros');
    }
};
