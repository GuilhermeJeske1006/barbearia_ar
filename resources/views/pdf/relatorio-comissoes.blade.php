<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('painel.comisiones') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .periodo { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        thead th { background: #f8fafc; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; color: #64748b; }
        .servico-linha { display: block; }
        .valor { font-weight: bold; }
        .totais { margin-top: 18px; width: 50%; }
        .totais td { border-bottom: none; padding: 3px 8px; }
        .totais .label { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ __('painel.comisiones') }}</h1>
    <div class="periodo">{{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}</div>

    <table>
        <thead>
            <tr>
                <th>{{ __('painel.data') }}</th>
                <th>{{ __('painel.horario') }}</th>
                <th>{{ __('painel.barbeiro') }}</th>
                <th>{{ __('painel.servicos') }}</th>
                <th>{{ __('painel.valor') }}</th>
                <th>{{ __('painel.status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($comissoes as $comissao)
                <tr>
                    <td>{{ $comissao['data'] }}</td>
                    <td>{{ $comissao['horario'] ?? '-' }}</td>
                    <td>{{ $comissao['barbeiro'] }}</td>
                    <td>
                        @forelse ($comissao['servicos'] as $servico)
                            <span class="servico-linha">{{ $servico['nome'] }} ({{ \App\Support\Money::format($servico['preco']) }})</span>
                        @empty
                            -
                        @endforelse
                    </td>
                    <td class="valor">{{ \App\Support\Money::format($comissao['valor']) }}</td>
                    <td>{{ __('painel.status_'.$comissao['status']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('painel.nenhum_registro') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totais">
        <tr><td class="label">{{ __('painel.total') }}</td><td class="valor">{{ \App\Support\Money::format($totais['total']) }}</td></tr>
        <tr><td class="label">{{ __('painel.status_pendente') }}</td><td>{{ \App\Support\Money::format($totais['pendente']) }}</td></tr>
        <tr><td class="label">{{ __('painel.status_pago') }}</td><td>{{ \App\Support\Money::format($totais['pago']) }}</td></tr>
    </table>
</body>
</html>
