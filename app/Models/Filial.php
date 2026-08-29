<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Filial extends Model
{
    use BelongsToBarbearia, HasFactory, SoftDeletes;

    // Eloquent pluraliza "Filial" -> "filials" (regras em inglês); a tabela
    // de verdade é "filiais" (português correto) — mesmo caso de Comissao.
    protected $table = 'filiais';

    protected $fillable = [
        'barbearia_id', 'nome', 'endereco', 'cidade', 'provincia', 'telefone', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

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
