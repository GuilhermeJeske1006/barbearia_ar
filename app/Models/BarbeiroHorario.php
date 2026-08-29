<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarbeiroHorario extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    protected $fillable = [
        'barbeiro_id', 'barbearia_id', 'filial_id', 'dia_semana', 'hora_inicio', 'hora_fim',
        'intervalo_inicio', 'intervalo_fim',
    ];

    public function barbeiro(): BelongsTo
    {
        return $this->belongsTo(Barbeiro::class);
    }
}
