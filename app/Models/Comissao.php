<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comissao extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    // Eloquent pluraliza "Comissao" -> "comissaos" (regras em inglês); a
    // tabela de verdade é "comissoes" (português correto).
    protected $table = 'comissoes';

    protected $fillable = [
        'barbeiro_id', 'barbearia_id', 'filial_id', 'pagamento_id', 'valor', 'status', 'data_referencia',
    ];

    protected $casts = [
        'data_referencia' => 'date',
    ];

    public function barbeiro(): BelongsTo
    {
        // withTrashed: comissão é histórico financeiro — um barbeiro
        // desligado (soft-deleted) não pode sumir do relatório.
        return $this->belongsTo(Barbeiro::class)->withTrashed();
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }
}
