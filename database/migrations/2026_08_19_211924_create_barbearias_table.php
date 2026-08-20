<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbearias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('cuit')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('provincia')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone')->default('America/Argentina/Buenos_Aires');
            $table->string('moeda')->default('ARS');
            $table->string('mp_user_id')->nullable();
            $table->text('mp_access_token')->nullable();
            $table->text('mp_refresh_token')->nullable();
            $table->string('mp_public_key')->nullable();
            $table->timestamp('mp_token_expira_em')->nullable();
            $table->enum('status', ['ativa', 'suspensa', 'trial'])->default('trial');
            $table->foreignId('plano_id')->nullable();
            $table->enum('idioma_padrao', ['es', 'pt'])->default('es');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbearias');
    }
};
