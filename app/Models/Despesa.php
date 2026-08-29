<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Despesa extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    protected $fillable = [
        'barbearia_id', 'filial_id', 'barbeiro_id', 'categoria', 'descricao',
        'fornecedor', 'valor', 'data_despesa', 'recorrente', 'frequencia',
        'dia_vencimento', 'proxima_geracao_em', 'despesa_origem_id',
    ];

    protected $casts = [
        'data_despesa' => 'date',
        'proxima_geracao_em' => 'date',
        'recorrente' => 'boolean',
    ];

    public function barbeiro(): BelongsTo
    {
        // withTrashed: despesa é histórico financeiro — mesmo raciocínio de
        // Comissao::barbeiro() e Pagamento::cliente().
        return $this->belongsTo(Barbeiro::class)->withTrashed();
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'despesa_origem_id');
    }

    public function geradas(): HasMany
    {
        return $this->hasMany(self::class, 'despesa_origem_id');
    }
}
