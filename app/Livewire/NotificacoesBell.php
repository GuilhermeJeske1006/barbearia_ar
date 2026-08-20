<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificacoesBell extends Component
{
    public function marcarComoLida(string $id): void
    {
        Auth::user()->unreadNotifications()->find($id)?->markAsRead();
    }

    public function marcarTodasComoLidas(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    #[On('notificacao-recebida')]
    public function atualizar(): void
    {
        // Só força o re-render; os dados vêm de render() abaixo.
    }

    public function render()
    {
        $usuario = Auth::user();

        return view('livewire.notificacoes-bell', [
            'notificacoes' => $usuario->notifications()->latest()->limit(10)->get(),
            'naoLidas' => $usuario->unreadNotifications()->count(),
        ]);
    }
}
