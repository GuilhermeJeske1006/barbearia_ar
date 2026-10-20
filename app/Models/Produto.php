<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    protected $fillable = [
        'barbearia_id', 'filial_id', 'nome', 'codigo_barras', 'unidade_consumo', 'conteudo_embalagem', 'saldo_aberto', 'descricao', 'preco', 'estoque_qtd', 'estoque_minimo', 'ativo', 'apenas_insumo', 'foto_path',
    ];

    protected $casts = [
        // A view e o service comparam estritamente (=== 0, !== null): não
        // dependem do driver devolver int.
        'estoque_qtd' => 'integer',
        'estoque_minimo' => 'integer',
        'saldo_aberto' => 'decimal:2',
        'conteudo_embalagem' => 'decimal:2',
        'ativo' => 'boolean',
        'apenas_insumo' => 'boolean',
    ];

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class);
    }

    public function estoqueBaixo(): bool
    {
        return $this->estoque_qtd !== null
            && $this->estoque_minimo !== null
            && $this->estoque_qtd <= $this->estoque_minimo;
    }
}
