<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagamento extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $fillable = [
        'barbearia_id', 'agendamento_id', 'cliente_id', 'valor_total', 'valor_comissao_barbeiro',
        'valor_barbearia', 'metodo', 'mp_payment_id', 'mp_preference_id', 'mp_status',
        'mp_split_status', 'forma_split', 'pago_em', 'raw_payload',
    ];

    protected $casts = [
        'pago_em' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }
}
