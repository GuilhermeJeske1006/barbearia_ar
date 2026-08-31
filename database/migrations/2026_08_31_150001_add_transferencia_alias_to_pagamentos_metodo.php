<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Só adiciona um valor aceito ao enum existente — não remove nem
     * renomeia nenhum dos já usados por Mercado Pago/PDV ('mp_checkout',
     * 'mp_point', 'dinheiro', 'outro'), então linhas existentes não são
     * afetadas.
     */
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->enum('metodo', ['mp_checkout', 'mp_point', 'dinheiro', 'outro', 'transferencia_alias'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->enum('metodo', ['mp_checkout', 'mp_point', 'dinheiro', 'outro'])->change();
        });
    }
};
