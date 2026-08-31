<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagamento extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    protected $fillable = [
        'barbearia_id', 'filial_id', 'agendamento_id', 'cliente_id', 'valor_total', 'valor_comissao_barbeiro',
        'valor_barbearia', 'metodo', 'mp_payment_id', 'mp_preference_id', 'mp_status',
        'mp_split_status', 'forma_split', 'pago_em', 'raw_payload',
        'status', 'decidido_por_id', 'decidido_em', 'motivo_recusa',
    ];

    protected $casts = [
        'pago_em' => 'datetime',
        'raw_payload' => 'array',
        'decidido_em' => 'datetime',
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function comprovantes(): HasMany
    {
        return $this->hasMany(ComprovantePagamento::class);
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por_id');
    }

    public function cliente(): BelongsTo
    {
        // withTrashed: pagamento é histórico financeiro — um cliente
        // removido (soft-deleted) não pode sumir do registro.
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }
}
