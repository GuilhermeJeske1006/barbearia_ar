<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * filial_id nasce nullable em todas as tabelas (migrations anteriores);
     * aqui cria uma Filial "Matriz" por barbearia existente e carimba o
     * filial_id de todo dado já criado nela, antes da migration seguinte
     * travar a coluna como NOT NULL.
     */
    private const TABELAS = [
        'servicos', 'produtos', 'barbeiros', 'barbeiro_horarios', 'barbeiro_bloqueios',
        'clientes', 'agendamentos', 'pagamentos', 'comissoes', 'pesquisas_satisfacao',
        'movimentacoes_estoque',
    ];

    public function up(): void
    {
        $barbearias = DB::table('barbearias')
            ->select('id', 'endereco', 'cidade', 'provincia', 'telefone')
            ->get();

        foreach ($barbearias as $barbearia) {
            $filialId = DB::table('filiais')->insertGetId([
                'barbearia_id' => $barbearia->id,
                'nome' => 'Matriz',
                'endereco' => $barbearia->endereco,
                'cidade' => $barbearia->cidade,
                'provincia' => $barbearia->provincia,
                'telefone' => $barbearia->telefone,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (self::TABELAS as $tabela) {
                DB::table($tabela)->where('barbearia_id', $barbearia->id)->update(['filial_id' => $filialId]);
            }

            DB::table('users')->where('barbearia_atual_id', $barbearia->id)->update(['filial_atual_id' => $filialId]);
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)->update(['filial_id' => null]);
        }

        DB::table('users')->update(['filial_atual_id' => null]);
        DB::table('filiais')->where('nome', 'Matriz')->delete();
    }
};
