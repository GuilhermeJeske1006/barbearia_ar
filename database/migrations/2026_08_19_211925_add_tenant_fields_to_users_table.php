<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefone')->nullable()->after('email');
            $table->enum('tipo', ['super_admin', 'dono', 'atendente', 'barbeiro', 'cliente'])
                ->default('cliente')->after('telefone');
            $table->foreignId('barbearia_atual_id')->nullable()
                ->after('tipo')->constrained('barbearias')->nullOnDelete();
            $table->enum('idioma', ['es', 'pt'])->nullable()->after('barbearia_atual_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barbearia_atual_id');
            $table->dropColumn(['telefone', 'tipo', 'idioma']);
        });
    }
};
