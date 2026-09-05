<?php

use App\Http\Middleware\ResolveFilial;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTheme;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SuperAdminOnly;
use App\Http\Middleware\VerificarAssinaturaAtiva;
use App\Http\Middleware\VerificarUsuarioAtivo;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(__DIR__.'/../routes/public.php');

            Route::middleware('web')
                ->group(__DIR__.'/../routes/auth.php');

            Route::middleware('web')
                ->group(__DIR__.'/../routes/superadmin.php');

            Route::group([], __DIR__.'/../routes/webhooks.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->appendToGroup('web', [SetLocale::class, ResolveTheme::class]);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'filial' => ResolveFilial::class,
            'usuario.ativo' => VerificarUsuarioAtivo::class,
            'assinatura.ativa' => VerificarAssinaturaAtiva::class,
            'superadmin' => SuperAdminOnly::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/mercadopago', 'webhooks/whatsapp/*', 'webhooks/stripe']);

        // ResolveTenant precisa rodar antes de SubstituteBindings: rotas com
        // model binding implícito (ex.: {barbeiro}) resolvem o Eloquent
        // model dentro de SubstituteBindings, e o global scope de
        // BelongsToBarbearia é fail-closed sem 'barbearia.id' já bindado —
        // sem essa prioridade, o binding sempre falha com 404 (model não
        // encontrado) mesmo quando o registro existe no tenant correto.
        $middleware->priority([
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            AuthenticateSession::class,
            ResolveTenant::class,
            ResolveFilial::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
