<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->foreignId('barbeiro_id')->constrained('barbeiros')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->enum('criado_por', ['cliente_online', 'atendente', 'pdv']);
            $table->dateTime('data_hora_inicio');
            $table->dateTime('data_hora_fim');
            $table->enum('status', [
                'pendente', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'no_show',
            ])->default('pendente');
            $table->boolean('origem_pdv')->default(false);
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barbeiro_id', 'data_hora_inicio', 'data_hora_fim']);
        });

        // Overbooking guard: Postgres can enforce a true range-exclusion constraint.
        // On MySQL, concurrent double-booking must instead be prevented at the
        // application layer via a transactional lockForUpdate() check.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(<<<'SQL'
                ALTER TABLE agendamentos
                ADD CONSTRAINT agendamentos_sem_sobreposicao
                EXCLUDE USING gist (
                    barbeiro_id WITH =,
                    tsrange(data_hora_inicio, data_hora_fim) WITH &&
                )
                WHERE (status NOT IN ('cancelado', 'no_show'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
