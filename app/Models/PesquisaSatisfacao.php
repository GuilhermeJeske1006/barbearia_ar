<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesquisaSatisfacao extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $table = 'pesquisas_satisfacao';

    protected $fillable = [
        'barbearia_id', 'agendamento_id', 'nota', 'comentario', 'enviado_em', 'respondido_em',
    ];

    protected $casts = [
        'nota' => 'integer',
        'enviado_em' => 'datetime',
        'respondido_em' => 'datetime',
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
