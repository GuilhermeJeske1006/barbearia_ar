<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbearia_id')->constrained('barbearias')->cascadeOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->cascadeOnDelete();
            $table->foreignId('barbeiro_id')->nullable()->constrained('barbeiros')->nullOnDelete();

            $table->enum('categoria', [
                'aluguel', 'contas', 'produtos_insumos', 'salarios_comissoes',
                'manutencao', 'marketing', 'impostos', 'outros',
            ]);
            $table->string('descricao')->nullable();
            $table->string('fornecedor')->nullable();
            $table->decimal('valor', 10, 2);
            $table->date('data_despesa');

            // Recorrência: registro "template" tem recorrente=true e
            // proxima_geracao_em; instâncias geradas têm recorrente=false e
            // despesa_origem_id apontando pro template (ver
            // GerarDespesasRecorrentesAction).
            $table->boolean('recorrente')->default(false);
            $table->enum('frequencia', ['mensal'])->nullable();
            $table->unsignedTinyInteger('dia_vencimento')->nullable();
            $table->date('proxima_geracao_em')->nullable();
            $table->foreignId('despesa_origem_id')->nullable()->constrained('despesas')->nullOnDelete();

            $table->timestamps();

            $table->index('data_despesa');
            $table->index('categoria');
            $table->index(['recorrente', 'proxima_geracao_em']);
            // Idempotência da geração automática: nunca duplica a mesma
            // competência (mês) da mesma despesa-template.
            $table->unique(['despesa_origem_id', 'data_despesa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
    }
};
