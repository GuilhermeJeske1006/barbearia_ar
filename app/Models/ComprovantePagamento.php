<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprovantePagamento extends Model
{
    use HasFactory;

    protected $table = 'comprovantes_pagamento';

    protected $fillable = ['pagamento_id', 'path', 'nome_original', 'mime', 'tamanho', 'enviado_em'];

    protected $casts = [
        'enviado_em' => 'datetime',
    ];

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }
}
