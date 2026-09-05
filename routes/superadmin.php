<?php

use App\Livewire\SuperAdmin\DetalheBarbearia;
use App\Livewire\SuperAdmin\ListaBarbearias;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin routes
|--------------------------------------------------------------------------
|
| Fora do grupo /painel de propósito: Super Admin opera através de
| barbearias, não dentro de uma. Por isso não leva 'tenant'/'filial'/
| 'assinatura.ativa' — só 'auth' + 'usuario.ativo' + 'superadmin' (checa
| users.tipo === 'super_admin', ver SuperAdminOnly). Ver docs/adr/0009.
|
*/
Route::middleware(['auth', 'usuario.ativo', 'superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/', fn () => redirect()->route('superadmin.barbearias'))->name('superadmin.dashboard');

    Route::get('/barbearias', ListaBarbearias::class)->name('superadmin.barbearias');

    Route::get('/barbearias/{barbearia}', DetalheBarbearia::class)->name('superadmin.barbearias.detalhe');
});
