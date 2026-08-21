<?php

use App\Http\Controllers\MercadoPagoConnectController;
use App\Livewire\Admin\Agenda\CalendarioAgenda;
use App\Livewire\Admin\Barbeiros\CrudBarbeiro;
use App\Livewire\Admin\Barbeiros\EscalaBarbeiro;
use App\Livewire\Admin\Clientes\CrudCliente;
use App\Livewire\Admin\Configuracoes\ConfigMercadoPago;
use App\Livewire\Admin\Configuracoes\ConfiguracoesBarbearia;
use App\Livewire\Admin\Configuracoes\ConfigWhatsApp;
use App\Livewire\Admin\Produtos\CrudProduto;
use App\Livewire\Admin\Relatorios\RelatorioComissoes;
use App\Livewire\Admin\Servicos\CrudServico;
use App\Livewire\Admin\Usuarios\CrudUsuario;
use App\Livewire\Admin\Usuarios\Permissoes;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Barbeiro\MeusHorarios;
use App\Livewire\Barbeiro\MinhaAgenda;
use App\Livewire\Barbeiro\MinhasComissoes;
use App\Livewire\Painel;
use App\Livewire\Pdv\TelaVendaDireta;
use App\Livewire\Perfil;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
|
| GET /login and GET /register are ours (fortify.views is false — see
| config/fortify.php). POST /login ('login.store') and POST /logout
| ('logout') are still registered by Fortify itself. Registration's POST
| goes through our own action (RegistrarDonoEBarbeariaAction), since the
| Fortify 'registration' feature is disabled — see the Livewire\Auth\Register
| component, which submits via wire:submit instead of a route.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware(['auth', 'tenant'])->prefix('painel')->group(function () {
    Route::get('/', Painel::class)->name('painel');

    Route::get('/perfil', Perfil::class)->name('perfil');

    Route::get('/agenda', CalendarioAgenda::class)
        ->middleware('can:agenda.gerenciar')->name('admin.agenda');

    Route::get('/pdv', TelaVendaDireta::class)
        ->middleware('can:pdv.operar')->name('pdv');

    Route::get('/barbeiros', CrudBarbeiro::class)
        ->middleware('can:barbeiros.gerenciar')->name('admin.barbeiros');

    Route::get('/barbeiros/{barbeiro}/horarios', EscalaBarbeiro::class)
        ->middleware('can:barbeiros.gerenciar')->name('admin.barbeiros.horarios');

    Route::get('/servicos', CrudServico::class)
        ->middleware('can:servicos.gerenciar')->name('admin.servicos');

    Route::get('/produtos', CrudProduto::class)
        ->middleware('can:produtos.gerenciar')->name('admin.produtos');

    Route::get('/clientes', CrudCliente::class)
        ->middleware('can:clientes.gerenciar')->name('admin.clientes');

    Route::get('/usuarios', CrudUsuario::class)
        ->middleware('can:usuarios.gerenciar')->name('admin.usuarios');

    Route::get('/permissoes', Permissoes::class)
        ->middleware('can:usuarios.gerenciar')->name('admin.permissoes');

    Route::get('/configuracoes', ConfiguracoesBarbearia::class)
        ->middleware('can:barbearia.gerenciar')->name('admin.configuracoes');

    Route::get('/mercadopago', ConfigMercadoPago::class)
        ->middleware('can:barbearia.gerenciar')->name('admin.mercadopago');

    Route::get('/mercadopago/conectar', [MercadoPagoConnectController::class, 'redirecionar'])
        ->middleware('can:barbearia.gerenciar')->name('mercadopago.conectar');

    Route::get('/whatsapp', ConfigWhatsApp::class)
        ->middleware('can:barbearia.gerenciar')->name('admin.whatsapp');

    Route::get('/relatorios/comissoes', RelatorioComissoes::class)
        ->middleware('can:financeiro.visualizar')->name('admin.relatorios.comissoes');

    Route::get('/minhas-comissoes', MinhasComissoes::class)
        ->middleware('can:comissoes.visualizar_propria')->name('barbeiro.minhas-comissoes');

    Route::get('/minha-agenda', MinhaAgenda::class)
        ->middleware('can:agenda.visualizar_propria')->name('barbeiro.minha-agenda');

    Route::get('/meus-horarios', MeusHorarios::class)
        ->middleware('can:horarios.visualizar_propria')->name('barbeiro.meus-horarios');
});

// Fora do prefixo /painel: a URL de callback é fixa, registrada uma única
// vez no app Mercado Pago Developers — não pode carregar o slug da barbearia.
Route::middleware('auth')->get('/mercadopago/callback', [MercadoPagoConnectController::class, 'callback'])
    ->name('mercadopago.callback');

if (app()->environment('local')) {
    Route::get('/_debug-login/{id}', function (int $id) {
        \Illuminate\Support\Facades\Auth::loginUsingId($id);

        return redirect('/painel/configuracoes');
    })->middleware('web');
}
