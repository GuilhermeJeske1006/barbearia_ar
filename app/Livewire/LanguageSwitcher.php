<?php

namespace App\Livewire;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    private const SUPPORTED = ['es', 'pt'];

    public string $locale;

    public function mount(): void
    {
        $this->locale = App::getLocale();
    }

    public function trocar(string $locale): void
    {
        if (! in_array($locale, self::SUPPORTED, true)) {
            return;
        }

        $this->locale = $locale;
        App::setLocale($locale);
        session(['locale' => $locale]);
        Cookie::queue('locale', $locale, 60 * 24 * 365);

        if (Auth::check()) {
            Auth::user()->update(['idioma' => $locale]);
        }

        $this->dispatch('locale-changed', locale: $locale);

        $this->redirect(url()->previous(), navigate: true);
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
