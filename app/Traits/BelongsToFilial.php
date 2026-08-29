<?php

namespace App\Traits;

use App\Models\Filial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Segunda camada de isolamento, independente de BelongsToBarbearia: uma
 * filial pertence a uma barbearia, mas o isolamento entre filiais da MESMA
 * barbearia não é coberto pelo scope de tenant. Mesma filosofia fail-closed
 * do ADR-0001 — sem filial bindada, a query não retorna nada.
 */
trait BelongsToFilial
{
    public static function bootBelongsToFilial(): void
    {
        static::addGlobalScope('filial', function (Builder $builder) {
            if (app()->bound('filial.id')) {
                $builder->where($builder->getModel()->getTable().'.filial_id', app('filial.id'));
            } else {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (app()->bound('filial.id')) {
                $model->filial_id = app('filial.id');
            }
        });
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }
}
