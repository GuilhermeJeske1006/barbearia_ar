<?php

namespace App\Providers;

use App\Http\Middleware\ResolveTenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire page components wrap their content in a layout view
        // resolved as 'layouts::{name}' by default (see config('livewire.
        // component_layout')) — this namespace makes that convention resolve
        // to resources/views/components/layouts/*.blade.php.
        View::addNamespace('layouts', resource_path('views/components/layouts'));

        // Livewire re-runs the page route's 'can:' middleware on every
        // wire:click/wire:submit AJAX call via a fake sub-request (see
        // PersistentMiddleware), but only for middleware on its own
        // allowlist. ResolveTenant isn't on it by default, so that fake
        // request never sets the Spatie team id — the permission check
        // then finds no team-scoped role and 403s, even for a user who
        // has the permission. Adding it here makes it re-run alongside
        // 'auth'/'can' so the team context exists before Authorize checks.
        app(PersistentMiddleware::class)->addPersistentMiddleware(ResolveTenant::class);

        // Throttle público (/b/{barbearia}): limita carregamento da página
        // por IP. A ação de criar agendamento (que chama gateway de
        // pagamento e envia notificação) tem limite próprio e mais restrito
        // em AgendamentoWizard::confirmar(), pois roda via endpoint AJAX do
        // Livewire e não passa por este middleware de rota.
        RateLimiter::for('publico', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Webhook do wuzapi: público (sem sessão), protegido só pelo token
        // no path — limita por token pra um webhookToken vazado/força-bruta
        // não conseguir bombardear o endpoint.
        RateLimiter::for('whatsapp-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->route('webhookToken'));
        });
    }
}
