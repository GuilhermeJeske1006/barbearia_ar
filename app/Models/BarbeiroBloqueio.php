<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarbeiroBloqueio extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory;

    protected $fillable = ['barbeiro_id', 'barbearia_id', 'filial_id', 'data_inicio', 'data_fim', 'motivo'];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
    ];

    public function barbeiro(): BelongsTo
    {
        return $this->belongsTo(Barbeiro::class);
    }
}
