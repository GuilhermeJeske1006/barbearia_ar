<?php

namespace App\Livewire\Admin\Configuracoes;

use App\Models\Barbearia;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class ConfigMercadoPago extends Component
{
    public bool $exigePagamentoAntecipado = false;

    public function mount(): void
    {
        $this->exigePagamentoAntecipado = $this->barbearia()->exige_pagamento_antecipado;
    }

    private function barbearia(): Barbearia
    {
        return app('barbearia');
    }

    public function atualizarExigePagamento(): void
    {
        $this->barbearia()->update(['exige_pagamento_antecipado' => $this->exigePagamentoAntecipado]);

        session()->flash('status', __('painel.preferencia_salva'));
    }

    public function desconectar(): void
    {
        $this->barbearia()->update([
            'mp_user_id' => null,
            'mp_access_token' => null,
            'mp_refresh_token' => null,
            'mp_public_key' => null,
            'mp_token_expira_em' => null,
        ]);

        session()->flash('status', __('painel.mp_desconectado'));
    }

    public function render()
    {
        return view('livewire.admin.configuracoes.config-mercado-pago', [
            'barbearia' => $this->barbearia(),
        ]);
    }
}
