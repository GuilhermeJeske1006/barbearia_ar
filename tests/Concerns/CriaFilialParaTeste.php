<?php

namespace Tests\Concerns;

use App\Models\Barbearia;
use App\Models\Filial;

/**
 * Cliente/Barbeiro/Servico/etc. agora são escopados por filial (BelongsToFilial),
 * segunda camada independente de BelongsToBarbearia — sem uma filial criada e
 * bindada em app('filial.id'), todo Model::create() desses models falha com
 * violação de NOT NULL. Mesmo padrão do bind manual de 'barbearia.id' que os
 * testes já fazem (rotas de teste não passam pelo middleware ResolveTenant/
 * ResolveFilial), só que pra filial.
 */
trait CriaFilialParaTeste
{
    protected function criarEBindarFilial(Barbearia $barbearia, string $nome = 'Matriz'): Filial
    {
        // firstOrCreate: RegistrarDonoEBarbeariaAction já cria uma filial
        // "Matriz" pra toda barbearia nova — criar uma segunda aqui deixaria
        // o dono (filial_atual_id aponta pra primeira) e os fixtures do
        // teste (stamped com a segunda) em filiais diferentes.
        $filial = Filial::firstOrCreate(['barbearia_id' => $barbearia->id, 'nome' => $nome]);

        app()->instance('filial.id', $filial->id);
        app()->instance('filial', $filial);

        return $filial;
    }
}
