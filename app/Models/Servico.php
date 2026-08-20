<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servico extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $fillable = [
        'barbearia_id', 'nome', 'descricao', 'duracao_minutos', 'preco', 'percentual_comissao_padrao', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function barbeiros(): BelongsToMany
    {
        return $this->belongsToMany(Barbeiro::class, 'barbeiro_servico')
            ->withPivot('percentual_comissao_override')
            ->withTimestamps();
    }
}
