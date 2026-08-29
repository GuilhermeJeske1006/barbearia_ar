@props(['href', 'icon'])

<a href="{{ $href }}" wire:navigate
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-full border border-slate-200 bg-ivory px-4 py-2 text-[12.5px] font-semibold text-slate-700 transition-colors hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-brand-700 dark:hover:bg-slate-800 dark:hover:text-white']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0">
        @switch($icon)
            @case('calendar')
                <rect x="3" y="4" width="14" height="13" rx="2" />
                <line x1="3" y1="8" x2="17" y2="8" />
                <line x1="7" y1="2.5" x2="7" y2="5.5" />
                <line x1="13" y1="2.5" x2="13" y2="5.5" />
                @break
            @case('bag')
                <path d="M5 7h10l-.9 8.1a1.5 1.5 0 0 1-1.49 1.4H7.39A1.5 1.5 0 0 1 5.9 15.1L5 7Z" />
                <path d="M7.5 7V5.5a2.5 2.5 0 0 1 5 0V7" />
                @break
            @case('scissors')
                <circle cx="5" cy="5" r="2" />
                <circle cx="5" cy="15" r="2" />
                <line x1="6.5" y1="6.5" x2="16" y2="16" />
                <line x1="6.5" y1="13.5" x2="16" y2="4" />
                @break
            @case('users')
                <circle cx="7" cy="7" r="2.5" />
                <circle cx="13.5" cy="7.5" r="2" />
                <path d="M2.5 16c0-2.5 2-4.2 4.5-4.2s4.5 1.7 4.5 4.2" />
                <path d="M11 12c1.9.2 3.5 1.6 3.5 4" />
                @break
            @case('banknote')
                <rect x="2.5" y="6" width="15" height="8" rx="1.5" />
                <circle cx="10" cy="10" r="2" />
                @break
        @endswitch
    </svg>
    {{ $slot }}
</a>
