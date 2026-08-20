@props(['status'])

@php
    $tone = match ($status) {
        'pendente' => 'amber',
        'pago' => 'green',
        'estornado' => 'red',
        default => 'slate',
    };
@endphp

<x-ui.badge :tone="$tone" {{ $attributes }}>
    {{ __("painel.status_{$status}") }}
</x-ui.badge>
