<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->text('wuzapi_token')->nullable();
            $table->string('wuzapi_session_name')->nullable();
            $table->string('wuzapi_webhook_token', 64)->nullable()->unique();
            $table->string('status_conexao_whatsapp')->default('desconectado');
            $table->string('numero_whatsapp_conectado')->nullable();
            $table->timestamp('whatsapp_sincronizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->dropColumn([
                'wuzapi_token', 'wuzapi_session_name', 'wuzapi_webhook_token',
                'status_conexao_whatsapp', 'numero_whatsapp_conectado', 'whatsapp_sincronizado_em',
            ]);
        });
    }
};
