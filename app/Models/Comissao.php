<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comissao extends Model
{
    use BelongsToBarbearia, HasFactory;

    // Eloquent pluraliza "Comissao" -> "comissaos" (regras em inglês); a
    // tabela de verdade é "comissoes" (português correto).
    protected $table = 'comissoes';

    protected $fillable = [
        'barbeiro_id', 'barbearia_id', 'pagamento_id', 'valor', 'status', 'data_referencia',
    ];

    protected $casts = [
        'data_referencia' => 'date',
    ];

    public function barbeiro(): BelongsTo
    {
        return $this->belongsTo(Barbeiro::class);
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }
}
