<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbeiros', function (Blueprint $table) {
            $table->string('pais')->nullable()->after('foto_path');
            $table->text('descricao')->nullable()->after('pais');
        });
    }

    public function down(): void
    {
        Schema::table('barbeiros', function (Blueprint $table) {
            $table->dropColumn(['pais', 'descricao']);
        });
    }
};
