<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->boolean('exige_pagamento_antecipado')->default(false)->after('idioma_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->dropColumn('exige_pagamento_antecipado');
        });
    }
};
