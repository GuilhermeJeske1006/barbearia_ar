@props(['href', 'active' => false, 'badge' => null, 'badgeLabel' => null])

<a href="{{ $href }}" wire:navigate @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12.5px] font-semibold transition-colors ' . ($active ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800/60 hover:text-white')]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $active ? 'bg-brand-500' : 'bg-slate-600' }}"></span>
    <span class="truncate">{{ $slot }}</span>
    @if (filled($badge))
        <span class="ml-auto shrink-0 rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white" aria-hidden="{{ $badgeLabel ? 'true' : 'false' }}">{{ $badge }}</span>
        @if ($badgeLabel)
            <span class="sr-only">({{ $badgeLabel }})</span>
        @endif
    @endif
</a>
