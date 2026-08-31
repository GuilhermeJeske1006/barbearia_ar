<?php

namespace App\Livewire\Admin\Configuracoes;

use App\Models\Barbearia;
use App\Models\MetodoPagamentoManual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app')]
class ConfigPagamentos extends Component
{
    private const TIPO_TRANSFERENCIA = MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS;

    public bool $exigePagamentoAntecipado = false;

    #[Validate('required|string|max:255')]
    public string $alias = '';

    #[Validate('required|string|max:255')]
    public string $titular = '';

    #[Validate('nullable|string|max:255')]
    public string $cbuCvu = '';

    #[Validate('nullable|string|max:255')]
    public string $banco = '';

    public bool $ativo = false;

    public function mount(): void
    {
        $this->exigePagamentoAntecipado = $this->barbearia()->exige_pagamento_antecipado;

        $metodo = $this->metodoTransferencia();

        if ($metodo) {
            $this->alias = (string) $metodo->alias();
            $this->titular = (string) $metodo->titular();
            $this->cbuCvu = (string) $metodo->cbuCvu();
            $this->banco = (string) $metodo->banco();
            $this->ativo = $metodo->ativo;
        }
    }

    private function barbearia(): Barbearia
    {
        return app('barbearia');
    }

    private function metodoTransferencia(): ?MetodoPagamentoManual
    {
        return MetodoPagamentoManual::where('tipo', self::TIPO_TRANSFERENCIA)->first();
    }

    public function atualizarExigePagamento(): void
    {
        $this->barbearia()->update(['exige_pagamento_antecipado' => $this->exigePagamentoAntecipado]);

        session()->flash('status', __('painel.preferencia_salva'));
    }

    public function desconectarMercadoPago(): void
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

    public function salvarTransferencia(): void
    {
        $this->validate();

        MetodoPagamentoManual::updateOrCreate(
            ['tipo' => self::TIPO_TRANSFERENCIA],
            [
                'ativo' => $this->ativo,
                'dados' => [
                    'alias' => $this->alias,
                    'titular' => $this->titular,
                    'cbu_cvu' => $this->cbuCvu ?: null,
                    'banco' => $this->banco ?: null,
                ],
            ],
        );

        session()->flash('status', __('painel.configuracoes_salvas'));
    }

    public function render()
    {
        return view('livewire.admin.configuracoes.config-pagamentos', [
            'barbearia' => $this->barbearia(),
        ]);
    }
}
