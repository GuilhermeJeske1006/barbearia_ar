<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'telefone', 'tipo', 'barbearia_atual_id', 'filial_atual_id', 'idioma', 'ativo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * Sem isso, um User::create() que esquece 'ativo' fica com null em
     * memória (o default do banco só se aplica na próxima leitura) — o cast
     * 'boolean' vira null em false, deslogando o usuário na hora via
     * VerificarUsuarioAtivo mesmo a coluna sendo true por padrão.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    public function barbeariaAtual(): BelongsTo
    {
        return $this->belongsTo(Barbearia::class, 'barbearia_atual_id');
    }

    public function filialAtual(): BelongsTo
    {
        return $this->belongsTo(Filial::class, 'filial_atual_id');
    }

    public function barbeiro(): HasMany
    {
        return $this->hasMany(Barbeiro::class);
    }

    /**
     * Local scope, não global: User não pode usar BelongsToBarbearia como os
     * outros modelos — o lookup de login do Fortify roda antes de qualquer
     * tenant ser resolvido, e um global scope fail-closed quebraria o login.
     * Aplica o filtro só onde for explicitamente chamado (ex.: CrudUsuario).
     */
    public function scopeDoTenantAtual(Builder $query): Builder
    {
        return $query->where('barbearia_atual_id', app('barbearia.id'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }
}
