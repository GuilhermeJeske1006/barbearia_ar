<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barbeiro extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'barbearia_id', 'filial_id', 'nome', 'foto_path', 'percentual_comissao',
        'mp_user_id', 'mp_access_token', 'mp_refresh_token', 'ativo', 'aceita_online',
    ];

    protected $casts = [
        'mp_access_token' => 'encrypted',
        'mp_refresh_token' => 'encrypted',
        'ativo' => 'boolean',
        'aceita_online' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'barbeiro_servico')
            ->withPivot('percentual_comissao_override', 'duracao_minutos_override')
            ->withTimestamps();
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(BarbeiroHorario::class);
    }

    public function bloqueios(): HasMany
    {
        return $this->hasMany(BarbeiroBloqueio::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }

    public function percentualComissaoPara(Servico $servico): float
    {
        $override = $this->servicos()
            ->where('servico_id', $servico->id)
            ->first()?->pivot->percentual_comissao_override;

        return (float) ($override ?? $servico->percentual_comissao_padrao ?? $this->percentual_comissao);
    }

    public function duracaoParaServico(Servico $servico): int
    {
        $override = $this->servicos()
            ->where('servico_id', $servico->id)
            ->first()?->pivot->duracao_minutos_override;

        return (int) ($override ?? $servico->duracao_minutos);
    }
}
