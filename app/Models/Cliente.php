<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use App\Traits\BelongsToFilial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Cliente extends Model
{
    use BelongsToBarbearia, BelongsToFilial, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'barbearia_id', 'filial_id', 'nome', 'telefone', 'email', 'dni', 'user_id', 'idioma', 'observacoes',
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

        $digitos = static::normalizarTelefone($this->telefone);

        return $digitos !== '' ? $digitos : null;
    }

    /**
     * Só dígitos — usado pra casar telefones digitados de formas diferentes
     * (com/sem espaço, parênteses, hífen) contra o valor cru salvo em
     * `telefone` (o wizard/PDV não normaliza na escrita, então o mesmo
     * cliente pode ter variações salvas).
     */
    public static function normalizarTelefone(string $telefone): string
    {
        return preg_replace('/\D/', '', $telefone) ?? '';
    }
}
