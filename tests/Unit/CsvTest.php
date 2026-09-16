<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_neutraliza_formulas_sem_alterar_texto_comum(?string $input, string $expected): void
    {
        $this->assertSame($expected, Csv::safeCell($input));
    }

    public static function cells(): array
    {
        return [
            ['=1+1', "'=1+1"],
            ['+SUM(A1:A2)', "'+SUM(A1:A2)"],
            ['-1+1', "'-1+1"],
            ['@SUM(A1:A2)', "'@SUM(A1:A2)"],
            ["\t=1", "'\t=1"],
            ['  =1', "'  =1"],
            ['Corte, barba', 'Corte, barba'],
            ['João', 'João'],
            [null, ''],
        ];
    }
}
