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

    /**
     * Moeda/timezone padrão pra uma barbearia nova ao escolher o país no
     * cadastro (RegistrarDonoEBarbeariaAction) — só cobre os países com
     * moeda suportada em Barbearia::SIMBOLOS_MOEDA e timezone suportado em
     * ConfiguracoesBarbearia::TIMEZONES; os demais (sem par MP/moeda hoje)
     * retornam null e quem chama decide o fallback.
     */
    private const MOEDA_TIMEZONE_PADRAO = [
        'AR' => ['ARS', 'America/Argentina/Buenos_Aires'],
        'BR' => ['BRL', 'America/Sao_Paulo'],
        'UY' => ['UYU', 'America/Montevideo'],
        'CL' => ['CLP', 'America/Santiago'],
        'PE' => ['PEN', 'America/Lima'],
        'CO' => ['COP', 'America/Bogota'],
        'MX' => ['MXN', 'America/Mexico_City'],
    ];

    /** @return array{0: string, 1: string}|null [moeda, timezone] padrão pro país, ou null se não mapeado */
    public static function moedaETimezonePadrao(?string $codigo): ?array
    {
        if (! $codigo) {
            return null;
        }

        return self::MOEDA_TIMEZONE_PADRAO[strtoupper($codigo)] ?? null;
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
