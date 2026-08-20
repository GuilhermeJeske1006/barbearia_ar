<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barbearia extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome', 'slug', 'cuit', 'endereco', 'cidade', 'provincia', 'telefone', 'email',
        'logo_path', 'timezone', 'moeda', 'mp_user_id', 'mp_access_token', 'mp_refresh_token',
        'mp_public_key', 'mp_token_expira_em', 'status', 'plano_id', 'idioma_padrao', 'exige_pagamento_antecipado',
    ];

    protected $casts = [
        'mp_access_token' => 'encrypted',
        'mp_refresh_token' => 'encrypted',
        'mp_token_expira_em' => 'datetime',
        'exige_pagamento_antecipado' => 'boolean',
    ];

    public function conectadaAoMercadoPago(): bool
    {
        return ! empty($this->mp_access_token);
    }

    public function barbeiros(): HasMany
    {
        return $this->hasMany(Barbeiro::class);
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
