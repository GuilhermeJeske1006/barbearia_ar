<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barbearia extends Model
{
    use HasFactory;

    public const STATUS_WHATSAPP_DESCONECTADO = 'desconectado';

    public const STATUS_WHATSAPP_CONECTANDO = 'conectando';

    public const STATUS_WHATSAPP_CONECTADO = 'conectado';

    public const STATUS_WHATSAPP_ERRO = 'erro';

    public const SIMBOLOS_MOEDA = [
        'ARS' => '$',
        'BRL' => 'R$',
        'USD' => 'US$',
        'MXN' => '$',
        'CLP' => '$',
        'COP' => '$',
        'PEN' => 'S/',
        'UYU' => '$U',
    ];

    protected $fillable = [
        'nome', 'slug', 'cuit', 'endereco', 'cidade', 'provincia', 'telefone', 'email',
        'logo_path', 'timezone', 'moeda', 'mp_user_id', 'mp_access_token', 'mp_refresh_token',
        'mp_public_key', 'mp_token_expira_em', 'status', 'idioma_padrao', 'exige_pagamento_antecipado',
        'stripe_customer_id', 'stripe_subscription_id', 'subscription_status',
        'wuzapi_token', 'wuzapi_session_name', 'wuzapi_webhook_token',
        'status_conexao_whatsapp', 'numero_whatsapp_conectado', 'whatsapp_sincronizado_em',
        'whatsapp_notifica_confirmacao', 'whatsapp_notifica_lembrete', 'whatsapp_notifica_pesquisa_satisfacao',
    ];

    protected $casts = [
        'mp_access_token' => 'encrypted',
        'mp_refresh_token' => 'encrypted',
        'mp_token_expira_em' => 'datetime',
        'exige_pagamento_antecipado' => 'boolean',
        'wuzapi_token' => 'encrypted',
        'whatsapp_sincronizado_em' => 'datetime',
        'whatsapp_notifica_confirmacao' => 'boolean',
        'whatsapp_notifica_lembrete' => 'boolean',
        'whatsapp_notifica_pesquisa_satisfacao' => 'boolean',
    ];

    public function conectadaAoMercadoPago(): bool
    {
        return ! empty($this->mp_access_token);
    }

    public function assinaturaAtiva(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * subscription_status null significa "nunca passou pelo checkout Stripe"
     * — barbearia criada antes dessa coluna existir, ou via seeder/admin.
     * Não bloqueia esses casos: só barra quando SABEMOS que a assinatura
     * está mal (past_due, canceled, incomplete). Sem essa distinção, toda
     * barbearia pré-existente ficaria trancada fora do painel no dia em que
     * o middleware de gating (VerificarAssinaturaAtiva) fosse ativado.
     */
    public function precisaRegularizarAssinatura(): bool
    {
        return $this->subscription_status !== null
            && ! in_array($this->subscription_status, ['active', 'trialing'], true);
    }

    public function simboloMoeda(): string
    {
        return self::SIMBOLOS_MOEDA[$this->moeda] ?? '$';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function filiais(): HasMany
    {
        return $this->hasMany(Filial::class);
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
