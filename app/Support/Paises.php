<?php

namespace App\Support;

/**
 * Lista de países (foco América Latina + Ibéria, mais comuns pro público
 * da app) usada no cadastro do barbeiro. Guardamos o código ISO 3166-1
 * alpha-2 em barbeiros.pais; nome exibido e bandeira são derivados daqui,
 * nunca digitados livremente — evita erro de digitação e garante bandeira certa.
 */
class Paises
{
    /** @var array<string, array{pt: string, es: string}> */
    private const NOMES = [
        'BR' => ['pt' => 'Brasil', 'es' => 'Brasil'],
        'AR' => ['pt' => 'Argentina', 'es' => 'Argentina'],
        'UY' => ['pt' => 'Uruguai', 'es' => 'Uruguay'],
        'PY' => ['pt' => 'Paraguai', 'es' => 'Paraguay'],
        'CL' => ['pt' => 'Chile', 'es' => 'Chile'],
        'BO' => ['pt' => 'Bolívia', 'es' => 'Bolivia'],
        'PE' => ['pt' => 'Peru', 'es' => 'Perú'],
        'EC' => ['pt' => 'Equador', 'es' => 'Ecuador'],
        'CO' => ['pt' => 'Colômbia', 'es' => 'Colombia'],
        'VE' => ['pt' => 'Venezuela', 'es' => 'Venezuela'],
        'PA' => ['pt' => 'Panamá', 'es' => 'Panamá'],
        'CR' => ['pt' => 'Costa Rica', 'es' => 'Costa Rica'],
        'MX' => ['pt' => 'México', 'es' => 'México'],
        'CU' => ['pt' => 'Cuba', 'es' => 'Cuba'],
        'DO' => ['pt' => 'República Dominicana', 'es' => 'República Dominicana'],
        'PT' => ['pt' => 'Portugal', 'es' => 'Portugal'],
        'ES' => ['pt' => 'Espanha', 'es' => 'España'],
        'IT' => ['pt' => 'Itália', 'es' => 'Italia'],
        'FR' => ['pt' => 'França', 'es' => 'Francia'],
        'DE' => ['pt' => 'Alemanha', 'es' => 'Alemania'],
        'US' => ['pt' => 'Estados Unidos', 'es' => 'Estados Unidos'],
        'JP' => ['pt' => 'Japão', 'es' => 'Japón'],
    ];

    /** @return array<string, string> código => nome no idioma atual, ordenado alfabeticamente */
    public static function lista(): array
    {
        $locale = app()->getLocale() === 'pt' ? 'pt' : 'es';

        $nomes = array_map(fn (array $n) => $n[$locale], self::NOMES);
        asort($nomes, SORT_LOCALE_STRING);

        return $nomes;
    }

    public static function nome(?string $codigo): ?string
    {
        if (! $codigo) {
            return null;
        }

        $locale = app()->getLocale() === 'pt' ? 'pt' : 'es';

        return self::NOMES[strtoupper($codigo)][$locale] ?? $codigo;
    }

    public static function bandeira(?string $codigo): ?string
    {
        if (! $codigo || strlen($codigo) !== 2) {
            return null;
        }

        $codigo = strtoupper($codigo);

        return mb_chr(0x1F1E6 + ord($codigo[0]) - 65).mb_chr(0x1F1E6 + ord($codigo[1]) - 65);
    }
}
