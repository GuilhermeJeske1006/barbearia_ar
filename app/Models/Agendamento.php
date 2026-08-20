<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agendamento extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $fillable = [
        'barbearia_id', 'barbeiro_id', 'cliente_id', 'criado_por', 'data_hora_inicio',
        'data_hora_fim', 'status', 'origem_pdv', 'observacoes', 'pagamento_id',
        'created_by', 'updated_by', 'lembrete_enviado_em',
    ];

    protected $casts = [
        'data_hora_inicio' => 'datetime',
        'data_hora_fim' => 'datetime',
        'origem_pdv' => 'boolean',
        'lembrete_enviado_em' => 'datetime',
    ];

    public function barbeiro(): BelongsTo
    {
        return $this->belongsTo(Barbeiro::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico')
            ->withPivot(['preco_cobrado', 'percentual_comissao_aplicado'])
            ->withTimestamps();
    }

    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'agendamento_produto')
            ->withPivot(['quantidade', 'preco_cobrado'])
            ->withTimestamps();
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
