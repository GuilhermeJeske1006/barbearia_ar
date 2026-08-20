<?php

namespace App\Support;

/**
 * Expressões pro plugin Alpine "mask" (resources/js/app.js), usadas como
 * x-mask:dynamic/x-mask nos inputs. Formato de telefone/documento varia por
 * idioma (pt = Brasil, es = Argentina) — mesmo par de campos, máscara
 * diferente, refletindo os placeholders já existentes em lang/{pt,es}/painel.php.
 */
class InputMasks
{
    public static function telefone(): string
    {
        return app()->getLocale() === 'pt'
            ? "\$input.length <= 14 ? '(99) 9999-9999' : '(99) 99999-9999'"
            : "'99 9999-9999'";
    }

    /** CPF (pt) ou DNI/CUIT (es) — documento pessoal do cliente. */
    public static function documentoPessoal(): string
    {
        return app()->getLocale() === 'pt'
            ? '999.999.999-99'
            : '99-99999999-9';
    }

    /** CNPJ (pt) ou CUIT (es) — documento da barbearia. */
    public static function documentoEmpresa(): string
    {
        return app()->getLocale() === 'pt'
            ? '99.999.999/9999-99'
            : '99-99999999-9';
    }
}
