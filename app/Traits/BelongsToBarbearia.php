<?php

namespace App\Traits;

use App\Models\Barbearia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBarbearia
{
    public static function bootBelongsToBarbearia(): void
    {
        static::addGlobalScope('barbearia', function (Builder $builder) {
            // Fail-closed: sem tenant bindado, a query não retorna nada em
            // vez de vazar dados de todas as barbearias. Código que
            // legitimamente precisa operar entre tenants (webhooks, jobs
            // agendados, Super Admin) usa withoutGlobalScopes() de propósito
            // — ver docs/adr/0001.
            if (app()->bound('barbearia.id')) {
                $builder->where($builder->getModel()->getTable().'.barbearia_id', app('barbearia.id'));
            } else {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            // Sempre sobrescreve com o tenant resolvido quando há um
            // bindado — nunca confia em um barbearia_id vindo do caller
            // (mass assignment, payload de request, etc.), que poderia
            // apontar pra outro tenant. Ver docs/adr/0001.
            if (app()->bound('barbearia.id')) {
                $model->barbearia_id = app('barbearia.id');
            }
        });
    }

    public function barbearia(): BelongsTo
    {
        return $this->belongsTo(Barbearia::class);
    }
}
