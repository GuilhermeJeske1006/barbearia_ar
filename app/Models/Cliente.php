<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Cliente extends Model
{
    use BelongsToBarbearia, HasFactory, Notifiable;

    protected $fillable = [
        'barbearia_id', 'nome', 'telefone', 'email', 'dni', 'user_id', 'idioma', 'observacoes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    /**
     * Usado pelo WhatsAppChannel. Normaliza pra só dígitos (E.164 sem '+') —
     * telefone é digitado livremente no wizard/PDV, pode vir com espaços,
     * parênteses ou hífen.
     */
    public function routeNotificationForWhatsApp(): ?string
    {
        if (! $this->telefone) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $this->telefone);

        return $digitos !== '' ? $digitos : null;
    }
}
