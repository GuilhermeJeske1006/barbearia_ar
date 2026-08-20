<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class ThemeToggle extends Component
{
    public string $theme;

    public function mount(): void
    {
        $this->theme = session('theme', 'light');
    }

    public function alternar(): void
    {
        $this->theme = $this->theme === 'dark' ? 'light' : 'dark';
        session(['theme' => $this->theme]);
        Cookie::queue('theme', $this->theme, 60 * 24 * 365);

        $this->dispatch('theme-changed', theme: $this->theme);
    }

    public function render()
    {
        return view('livewire.theme-toggle');
    }
}
