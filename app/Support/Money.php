<?php

namespace App\Support;

class Money
{
    public static function simbolo(): string
    {
        if (! app()->bound('barbearia')) {
            return '$';
        }

        $barbearia = app('barbearia');

        return "({$barbearia->moeda}) {$barbearia->simboloMoeda()}";
    }

    public static function format(float|int|string|null $valor): string
    {
        return self::simbolo().' '.number_format((float) $valor, 2, ',', '.');
    }
}
