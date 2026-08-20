@props(['status'])

@php
    $tone = match ($status) {
        'pendente', 'confirmado' => 'amber',
        'em_atendimento' => 'blue',
        'concluido' => 'green',
        'cancelado', 'no_show' => 'red',
        default => 'slate',
    };
@endphp

<x-ui.badge :tone="$tone" {{ $attributes }}>
    {{ __("painel.status_{$status}") }}
</x-ui.badge>
