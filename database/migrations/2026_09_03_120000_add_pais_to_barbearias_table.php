<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->string('pais', 2)->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('barbearias', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
};
