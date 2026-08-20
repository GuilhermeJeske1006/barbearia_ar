<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Renders a plain HTML form posting to Fortify's own POST /login
 * ('login.store') route — not wire:submit. This is a deliberate choice
 * (see docs/adr/0004): it lets us keep the page as a routable Livewire
 * component like the rest of the app, while reusing Fortify's tested
 * pipeline as-is (rate limiting, remember-me, redirect-if-authenticatable)
 * instead of reimplementing Auth::attempt() + throttling by hand.
 */
#[Layout('layouts::guest')]
class Login extends Component
{
    public function render()
    {
        return view('livewire.auth.login');
    }
}
