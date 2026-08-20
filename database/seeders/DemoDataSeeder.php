<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popula dados de demonstração em todas as tabelas de negócio, para 2
 * barbearias (prova o isolamento multi-tenant). Idempotente: roda com
 * firstOrCreate/updateOrCreate, seguro de repetir.
 */
class DemoDataSeeder extends Seeder
{
    private Generator $faker;

    private const SERVICOS = [
        ['nome' => 'Corte de Cabelo', 'duracao' => 30, 'preco' => 25.00],
        ['nome' => 'Barba', 'duracao' => 20, 'preco' => 15.00],
        ['nome' => 'Corte + Barba', 'duracao' => 45, 'preco' => 35.00],
        ['nome' => 'Sobrancelha', 'duracao' => 10, 'preco' => 8.00],
        ['nome' => 'Pézinho', 'duracao' => 15, 'preco' => 10.00],
    ];

    private const PRODUTOS = [
        ['nome' => 'Pomada Modeladora', 'preco' => 22.00],
        ['nome' => 'Óleo para Barba', 'preco' => 18.50],
        ['nome' => 'Shampoo Anticaspa', 'preco' => 16.00],
        ['nome' => 'Cera Capilar', 'preco' => 19.90],
        ['nome' => 'Balm Pós-Barba', 'preco' => 14.00],
    ];

    public function run(): void
    {
        $this->faker = FakerFactory::create('pt_BR');

        $this->call(RoleAndPermissionSeeder::class);

        $barbeariaAr = $this->criarBarbearia('Barbearia AR', 'barbearia-ar', 'guilhermeieski@gmail.com');
        $barbeariaSul = $this->criarBarbearia('Barbearia Sul', 'barbearia-sul', 'contato@barbeariasul.com.br');

        $this->popularBarbearia($barbeariaAr, 'guilhermeieski@gmail.com', 'Guilherme Jeske');
        $this->popularBarbearia($barbeariaSul, 'dono@barbeariasul.com.br', 'Marcos Ferreira');
    }

    private function criarBarbearia(string $nome, string $slug, string $email): Barbearia
    {
        return Barbearia::firstOrCreate(
            ['slug' => $slug],
            [
                'nome' => $nome,
                'email' => $email,
                'cidade' => $this->faker->city(),
                'provincia' => 'Buenos Aires',
                'telefone' => $this->faker->numerify('+54 9 11 ########'),
                'timezone' => 'America/Argentina/Buenos_Aires',
                'moeda' => 'ARS',
                'status' => 'ativa',
                'idioma_padrao' => 'pt',
                'exige_pagamento_antecipado' => false,
            ]
        );
    }

    private function popularBarbearia(Barbearia $barbearia, string $emailDono, string $nomeDono): void
    {
        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('barbearia', $barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);

        $dono = $this->criarUsuario($emailDono, $nomeDono, 'dono', $barbearia->id, 'dono');

        $atendente = $this->criarUsuario(
            Str::before($emailDono, '@').'.atendente@'.Str::after($emailDono, '@'),
            $this->faker->name(),
            'atendente',
            $barbearia->id,
            'atendente'
        );

        $servicos = collect(self::SERVICOS)->map(fn (array $s) => Servico::firstOrCreate(
            ['barbearia_id' => $barbearia->id, 'nome' => $s['nome']],
            [
                'duracao_minutos' => $s['duracao'],
                'preco' => $s['preco'],
                'percentual_comissao_padrao' => 40,
                'ativo' => true,
            ]
        ));

        $produtos = collect(self::PRODUTOS)->map(fn (array $p) => Produto::firstOrCreate(
            ['barbearia_id' => $barbearia->id, 'nome' => $p['nome']],
            [
                'descricao' => $this->faker->sentence(8),
                'preco' => $p['preco'],
                'estoque_qtd' => $this->faker->numberBetween(5, 40),
                'ativo' => true,
            ]
        ));

        $barbeiros = collect(range(1, 3))->map(function (int $i) use ($barbearia, $servicos) {
            $nome = $this->faker->name('male');
            $email = Str::slug($nome).$i.'.'.$barbearia->slug.'@barbearia.test';

            $user = $this->criarUsuario($email, $nome, 'barbeiro', $barbearia->id, 'barbeiro');

            $barbeiro = Barbeiro::firstOrCreate(
                ['barbearia_id' => $barbearia->id, 'user_id' => $user->id],
                [
                    'nome' => $nome,
                    'percentual_comissao' => $this->faker->numberBetween(30, 50),
                    'ativo' => true,
                    'aceita_online' => true,
                ]
            );

            foreach ($servicos as $servico) {
                $barbeiro->servicos()->syncWithoutDetaching([
                    $servico->id => ['percentual_comissao_override' => $this->faker->boolean(20) ? $this->faker->numberBetween(35, 55) : null],
                ]);
            }

            foreach (range(1, 6) as $diaSemana) {
                BarbeiroHorario::firstOrCreate(
                    ['barbeiro_id' => $barbeiro->id, 'dia_semana' => $diaSemana],
                    [
                        'barbearia_id' => $barbearia->id,
                        'hora_inicio' => '09:00',
                        'hora_fim' => '19:00',
                        'intervalo_inicio' => '12:00',
                        'intervalo_fim' => '13:00',
                    ]
                );
            }

            return $barbeiro;
        });

        BarbeiroBloqueio::firstOrCreate(
            ['barbeiro_id' => $barbeiros->first()->id, 'motivo' => 'Férias'],
            [
                'data_inicio' => now()->addDays(20)->setTime(0, 0),
                'data_fim' => now()->addDays(25)->setTime(23, 59),
            ]
        );

        $clientes = collect(range(1, 10))->map(function (int $i) use ($barbearia) {
            $nome = $this->faker->name();
            $telefone = $this->faker->numerify('+54 9 11 ########');
            $userId = null;

            if ($i <= 3) {
                $email = Str::slug($nome).$i.'.cliente.'.$barbearia->slug.'@barbearia.test';
                $userId = $this->criarUsuario($email, $nome, 'cliente', null, 'cliente')->id;
            }

            return Cliente::firstOrCreate(
                ['barbearia_id' => $barbearia->id, 'telefone' => $telefone],
                [
                    'nome' => $nome,
                    'email' => $this->faker->boolean(60) ? $this->faker->safeEmail() : null,
                    'dni' => $this->faker->numerify('########'),
                    'user_id' => $userId,
                    'idioma' => $this->faker->randomElement(['es', 'pt']),
                    'observacoes' => $this->faker->boolean(20) ? $this->faker->sentence(6) : null,
                ]
            );
        });

        $this->gerarAgendamentos($barbearia, $barbeiros, $clientes, $servicos, $produtos, $dono, $atendente);
    }

    private function criarUsuario(string $email, string $nome, string $tipo, ?int $barbeariaId, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nome,
                'password' => 'password',
                'telefone' => $this->faker->numerify('+54 9 11 ########'),
                'tipo' => $tipo,
                'barbearia_atual_id' => $barbeariaId,
                'idioma' => 'pt',
                'email_verified_at' => now(),
            ]
        );

        if ($barbeariaId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($barbeariaId);
            $user->unsetRelation('roles');
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        return $user;
    }

    private function gerarAgendamentos($barbearia, $barbeiros, $clientes, $servicos, $produtos, User $dono, User $atendente): void
    {
        $criadoresPossiveis = ['cliente_online', 'atendente', 'pdv'];

        // 15 agendamentos passados (concluídos, com pagamento + comissão), últimos 20 dias.
        foreach (range(1, 15) as $i) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servicosEscolhidos = $servicos->random(random_int(1, 2));
            $inicio = now()->subDays(random_int(1, 20))->setTime(random_int(9, 17), $this->faker->randomElement([0, 30]));
            $duracao = $servicosEscolhidos->sum('duracao_minutos');

            $agendamento = Agendamento::create([
                'barbearia_id' => $barbearia->id,
                'barbeiro_id' => $barbeiro->id,
                'cliente_id' => $cliente->id,
                'criado_por' => $this->faker->randomElement($criadoresPossiveis),
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => (clone $inicio)->addMinutes($duracao),
                'status' => 'concluido',
                'created_by' => $atendente->id,
            ]);

            $valorServicos = 0;
            $valorComissao = 0;
            foreach ($servicosEscolhidos as $servico) {
                $percentual = $barbeiro->percentualComissaoPara($servico);
                $agendamento->servicos()->attach($servico->id, [
                    'preco_cobrado' => $servico->preco,
                    'percentual_comissao_aplicado' => $percentual,
                ]);
                $valorServicos += (float) $servico->preco;
                $valorComissao += (float) $servico->preco * $percentual / 100;
            }

            $valorProdutos = 0;
            if ($this->faker->boolean(40)) {
                $produto = $produtos->random();
                $qtd = random_int(1, 2);
                $agendamento->produtos()->attach($produto->id, [
                    'quantidade' => $qtd,
                    'preco_cobrado' => $produto->preco,
                ]);
                $valorProdutos = (float) $produto->preco * $qtd;
            }

            $valorTotal = round($valorServicos + $valorProdutos, 2);
            $valorComissao = round($valorComissao, 2);

            $pagamento = Pagamento::create([
                'barbearia_id' => $barbearia->id,
                'agendamento_id' => $agendamento->id,
                'cliente_id' => $cliente->id,
                'valor_total' => $valorTotal,
                'valor_comissao_barbeiro' => $valorComissao,
                'valor_barbearia' => round($valorTotal - $valorComissao, 2),
                'metodo' => $this->faker->randomElement(['dinheiro', 'mp_checkout', 'mp_point']),
                'mp_payment_id' => $this->faker->boolean(50) ? (string) $this->faker->unique()->numberBetween(100000000, 999999999) : null,
                'mp_status' => $this->faker->boolean(50) ? 'approved' : null,
                'forma_split' => 'manual',
                'pago_em' => $agendamento->data_hora_fim,
            ]);

            $agendamento->update(['pagamento_id' => $pagamento->id]);

            Comissao::create([
                'barbeiro_id' => $barbeiro->id,
                'barbearia_id' => $barbearia->id,
                'pagamento_id' => $pagamento->id,
                'valor' => $valorComissao,
                'status' => $this->faker->randomElement(['pendente', 'pendente', 'pago']),
                'data_referencia' => $agendamento->data_hora_fim->toDateString(),
            ]);
        }

        // 6 agendamentos futuros (pendente/confirmado), sem pagamento ainda.
        foreach (range(1, 6) as $i) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servicosEscolhidos = $servicos->random(random_int(1, 2));
            $inicio = now()->addDays(random_int(1, 10))->setTime(random_int(9, 17), $this->faker->randomElement([0, 30]));
            $duracao = $servicosEscolhidos->sum('duracao_minutos');

            $agendamento = Agendamento::create([
                'barbearia_id' => $barbearia->id,
                'barbeiro_id' => $barbeiro->id,
                'cliente_id' => $cliente->id,
                'criado_por' => $this->faker->randomElement($criadoresPossiveis),
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => (clone $inicio)->addMinutes($duracao),
                'status' => $this->faker->randomElement(['pendente', 'confirmado']),
                'created_by' => $atendente->id,
            ]);

            foreach ($servicosEscolhidos as $servico) {
                $agendamento->servicos()->attach($servico->id, [
                    'preco_cobrado' => $servico->preco,
                    'percentual_comissao_aplicado' => $barbeiro->percentualComissaoPara($servico),
                ]);
            }
        }

        // 2 agendamentos passados sem comparecimento/cancelados, sem pagamento.
        foreach (['no_show', 'cancelado'] as $status) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servico = $servicos->random();
            $inicio = now()->subDays(random_int(1, 15))->setTime(random_int(9, 17), 0);

            $agendamento = Agendamento::create([
                'barbearia_id' => $barbearia->id,
                'barbeiro_id' => $barbeiro->id,
                'cliente_id' => $cliente->id,
                'criado_por' => $this->faker->randomElement($criadoresPossiveis),
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => (clone $inicio)->addMinutes((int) $servico->duracao_minutos),
                'status' => $status,
                'created_by' => $atendente->id,
            ]);

            $agendamento->servicos()->attach($servico->id, [
                'preco_cobrado' => $servico->preco,
                'percentual_comissao_aplicado' => $barbeiro->percentualComissaoPara($servico),
            ]);
        }
    }
}
