<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\ComprovantePagamento;
use App\Models\Filial;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class ComprovanteDownloadControllerTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private User $dono;

    private Pagamento $pagamento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('comprovantes');

        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);

        $servico = Servico::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        $barbeiro = Barbeiro::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'María', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'metodo' => 'transferencia_alias',
            'status' => 'aguardando_confirmacao',
            'forma_split' => 'manual',
        ]);

        $arquivo = UploadedFile::fake()->image('comprovante.jpg');
        $path = $arquivo->storeAs((string) $this->pagamento->id, 'fake-uuid.jpg', 'comprovantes');

        ComprovantePagamento::create([
            'pagamento_id' => $this->pagamento->id,
            'path' => $path,
            'nome_original' => 'comprovante.jpg',
            'mime' => 'image/jpeg',
            'tamanho' => $arquivo->getSize(),
            'enviado_em' => now(),
        ]);
    }

    public function test_dono_consegue_baixar_o_proprio_comprovante(): void
    {
        $this->actingAs($this->dono)
            ->get(route('admin.pagamentos.comprovante', $this->pagamento))
            ->assertOk();
    }

    public function test_atendente_sem_permissao_nao_acessa(): void
    {
        $atendente = User::create([
            'name' => 'Atendente', 'email' => 'atendente@example.com', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente', 'barbearia_atual_id' => $this->barbearia->id, 'ativo' => true,
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.pagamentos.comprovante', $this->pagamento))
            ->assertForbidden();
    }

    public function test_dono_de_outra_barbearia_nao_baixa_comprovante_alheio(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        $filialOutra = Filial::create(['barbearia_id' => $outraBarbearia->id, 'nome' => 'Matriz']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($outraBarbearia->id);

        $donoOutra = User::create([
            'name' => 'Otro Dueño', 'email' => 'otro@example.com', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'dono', 'barbearia_atual_id' => $outraBarbearia->id, 'filial_atual_id' => $filialOutra->id, 'ativo' => true,
        ]);
        $donoOutra->assignRole('dono');

        // Requisição HTTP real (não Livewire::test) — ResolveTenant/ResolveFilial
        // rodam de verdade a partir de barbearia_atual_id/filial_atual_id do
        // usuário autenticado, então o 404 aqui vem só do isolamento de
        // tenant, não de faltar bind manual.
        $this->actingAs($donoOutra)
            ->get(route('admin.pagamentos.comprovante', $this->pagamento))
            ->assertNotFound();
    }
}
