@props(['href', 'active' => false])

<a href="{{ $href }}" wire:navigate
    {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[12.5px] font-semibold transition-colors ' . ($active ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800/60 hover:text-white')]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $active ? 'bg-brand-500' : 'bg-slate-600' }}"></span>
    <span class="truncate">{{ $slot }}</span>
</a>
