<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * BarbeiroBloqueio nasceu sem barbearia_id/BelongsToBarbearia, ao
     * contrário do irmão BarbeiroHorario — o global scope de tenant nunca
     * filtrava esses registros. Coluna entra nullable, é preenchida a
     * partir do barbeiro relacionado e só então vira NOT NULL.
     */
    public function up(): void
    {
        Schema::table('barbeiro_bloqueios', function (Blueprint $table) {
            $table->foreignId('barbearia_id')->nullable()
                ->after('barbeiro_id')->constrained('barbearias')->cascadeOnDelete();
        });

        DB::statement('
            UPDATE barbeiro_bloqueios
            SET barbearia_id = (
                SELECT barbearia_id FROM barbeiros WHERE barbeiros.id = barbeiro_bloqueios.barbeiro_id
            )
        ');

        Schema::table('barbeiro_bloqueios', function (Blueprint $table) {
            $table->foreignId('barbearia_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barbeiro_bloqueios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barbearia_id');
        });
    }
};
