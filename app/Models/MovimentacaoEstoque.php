<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoEstoque extends Model
{
    use BelongsToBarbearia, BelongsToFilial;

    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'barbearia_id', 'filial_id', 'produto_id', 'agendamento_id', 'user_id',
        'tipo', 'quantidade', 'estoque_resultante', 'observacao',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
