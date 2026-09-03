<?php

namespace App\Support;

/**
 * Expressões pro plugin Alpine "mask" (resources/js/app.js), usadas como
 * x-mask:dynamic/x-mask nos inputs. Formato de telefone/documento varia por
 * país, não por idioma — 'es' sozinho não distingue Argentina de Colômbia.
 * Usamos a moeda da barbearia atual (já é o proxy de país usado em
 * Barbearia::SIMBOLOS_MOEDA e ConfiguracoesBarbearia::TIMEZONES) quando há
 * tenant resolvido (ver ResolveTenant); sem tenant — ex.: cadastro, antes de
 * a barbearia existir — cai pro idioma, igual ao comportamento anterior.
 */
class InputMasks
{
    public static function telefone(): string
    {
        return match (self::moeda()) {
            'BRL' => "\$input.length <= 14 ? '(99) 9999-9999' : '(99) 99999-9999'",
            'COP' => "'999 999 9999'",
            default => "'99 9999-9999'",
        };
    }

    /** CPF (BRL), Cédula (COP) ou DNI/CUIT (demais) — documento pessoal do cliente. */
    public static function documentoPessoal(): string
    {
        return match (self::moeda()) {
            'BRL' => '999.999.999-99',
            'COP' => '9999999999',
            default => '99-99999999-9',
        };
    }

    /** CNPJ (BRL), NIT (COP) ou CUIT (demais) — documento da barbearia. */
    public static function documentoEmpresa(): string
    {
        return match (self::moeda()) {
            'BRL' => '99.999.999/9999-99',
            'COP' => '999.999.999-9',
            default => '99-99999999-9',
        };
    }

    public static function placeholderTelefone(): string
    {
        return match (self::moeda()) {
            'BRL' => '(11) 91234-5678',
            'COP' => '300 1234567',
            default => '11 1234-5678',
        };
    }

    /** Ex. de CPF (BRL), Cédula (COP) ou DNI (demais). */
    public static function placeholderDocumentoPessoal(): string
    {
        return match (self::moeda()) {
            'BRL' => '000.000.000-00',
            'COP' => '1234567890',
            default => '20-12345678-9',
        };
    }

    /** Ex. de CNPJ (BRL), NIT (COP) ou CUIT (demais). */
    public static function placeholderDocumentoEmpresa(): string
    {
        return match (self::moeda()) {
            'BRL' => '00.000.000/0001-00',
            'COP' => '900.123.456-7',
            default => '20-12345678-9',
        };
    }

    private static function moeda(): ?string
    {
        if (app()->bound('barbearia')) {
            return app('barbearia')->moeda;
        }

        return app()->getLocale() === 'pt' ? 'BRL' : null;
    }
}
