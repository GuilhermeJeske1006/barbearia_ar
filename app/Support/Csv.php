<?php

namespace App\Support;

class Csv
{
    public static function safeCell(?string $value): string
    {
        $value ??= '';

        // Quoting CSV fields does not stop spreadsheet formula execution.
        if (preg_match('/^[\s\x00-\x1F]*[=+@-]|^[\t\r\n]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
