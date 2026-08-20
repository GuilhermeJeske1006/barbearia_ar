<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $fillable = [
        'barbearia_id', 'nome', 'descricao', 'preco', 'estoque_qtd', 'ativo', 'foto_path',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
