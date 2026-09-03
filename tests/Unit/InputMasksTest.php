<?php

namespace Tests\Unit;

use App\Models\Barbearia;
use App\Support\InputMasks;
use Tests\TestCase;

class InputMasksTest extends TestCase
{
    public function test_mascaras_seguem_a_moeda_da_barbearia_atual_nao_o_idioma(): void
    {
        app()->setLocale('es');
        app()->instance('barbearia', new Barbearia(['moeda' => 'COP']));

        $this->assertSame("'999 999 9999'", InputMasks::telefone());
        $this->assertSame('9999999999', InputMasks::documentoPessoal());
        $this->assertSame('999.999.999-9', InputMasks::documentoEmpresa());
    }

    public function test_mascaras_brl_valem_mesmo_com_locale_es_quando_barbearia_e_brasileira(): void
    {
        app()->setLocale('es');
        app()->instance('barbearia', new Barbearia(['moeda' => 'BRL']));

        $this->assertSame('999.999.999-99', InputMasks::documentoPessoal());
        $this->assertSame('99.999.999/9999-99', InputMasks::documentoEmpresa());
    }

    public function test_sem_barbearia_resolvida_cai_pro_idioma_pt_como_antes(): void
    {
        app()->setLocale('pt');

        $this->assertSame('999.999.999-99', InputMasks::documentoPessoal());
    }

    public function test_sem_barbearia_resolvida_cai_pro_idioma_es_generico_como_antes(): void
    {
        app()->setLocale('es');

        $this->assertSame("'99 9999-9999'", InputMasks::telefone());
        $this->assertSame('99-99999999-9', InputMasks::documentoPessoal());
    }
}
