<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->boolean('whatsapp_notifica_confirmacao')->default(true);
            $table->boolean('whatsapp_notifica_lembrete')->default(true);
            $table->boolean('whatsapp_notifica_pesquisa_satisfacao')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_notifica_confirmacao', 'whatsapp_notifica_lembrete', 'whatsapp_notifica_pesquisa_satisfacao',
            ]);
        });
    }
};
