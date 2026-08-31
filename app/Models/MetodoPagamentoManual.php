<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetodoPagamentoManual extends Model
{
    use BelongsToBarbearia, HasFactory;

    public const TIPO_TRANSFERENCIA_ALIAS = 'transferencia_alias';

    protected $table = 'metodos_pagamento_manuais';

    protected $fillable = ['barbearia_id', 'tipo', 'ativo', 'dados'];

    protected $casts = [
        'ativo' => 'boolean',
        // encrypted: alias/CBU-CVU são dado bancário, mesmo padrão de
        // sensibilidade do mp_access_token em Barbearia.
        'dados' => 'encrypted:array',
    ];

    public function barbearia(): BelongsTo
    {
        return $this->belongsTo(Barbearia::class);
    }

    public function alias(): ?string
    {
        return $this->dados['alias'] ?? null;
    }

    public function titular(): ?string
    {
        return $this->dados['titular'] ?? null;
    }

    public function cbuCvu(): ?string
    {
        return $this->dados['cbu_cvu'] ?? null;
    }

    public function banco(): ?string
    {
        return $this->dados['banco'] ?? null;
    }
}
