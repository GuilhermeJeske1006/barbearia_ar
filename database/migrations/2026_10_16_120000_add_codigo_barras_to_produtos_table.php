<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo_barras', 64)->nullable();
            $table->unique(['barbearia_id', 'filial_id', 'codigo_barras'], 'produtos_codigo_barras_unique');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropUnique('produtos_codigo_barras_unique');
            $table->dropColumn('codigo_barras');
        });
    }
};
