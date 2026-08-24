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
use App\Models\PesquisaSatisfacao;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popula UMA barbearia argentina completa e "pronta pra demo": barbeiros,
 * serviços, produtos e clientes com foto (gerada via GD, sem depender de
 * internet), preços em pesos argentinos (ARS) realistas, agenda com
 * histórico + futuro, pagamentos, comissões e pesquisas de satisfação.
 *
 * Rodar isolado: php artisan db:seed --class=BarbeariaCompletaSeeder
 */
class BarbeariaCompletaSeeder extends Seeder
{
    private Generator $faker;

    private Barbearia $barbearia;

    private const PALETA = [
        '1e3a5f', '7c2d12', '14532d', '581c87', '9a3412', '1e293b', '831843', '0f766e',
    ];

    private const SERVICOS = [
        ['nome' => 'Corte Clásico', 'duracao' => 30, 'preco' => 9000, 'comissao' => 40],
        ['nome' => 'Corte + Barba', 'duracao' => 50, 'preco' => 15500, 'comissao' => 40],
        ['nome' => 'Diseño de Barba', 'duracao' => 25, 'preco' => 7000, 'comissao' => 40],
        ['nome' => 'Afeitado Clásico a Navaja', 'duracao' => 30, 'preco' => 8500, 'comissao' => 45],
        ['nome' => 'Corte Niño', 'duracao' => 25, 'preco' => 7000, 'comissao' => 35],
        ['nome' => 'Coloración / Platinado', 'duracao' => 90, 'preco' => 26000, 'comissao' => 35],
        ['nome' => 'Perfilado de Cejas', 'duracao' => 15, 'preco' => 3500, 'comissao' => 40],
        ['nome' => 'Alisado / Progresiva', 'duracao' => 120, 'preco' => 32000, 'comissao' => 30],
        ['nome' => 'Pigmentación de Barba', 'duracao' => 40, 'preco' => 12500, 'comissao' => 40],
        ['nome' => 'Limpieza Facial', 'duracao' => 35, 'preco' => 11000, 'comissao' => 35],
    ];

    private const PRODUTOS = [
        ['nome' => 'Cera Modeladora Mate', 'preco' => 6500, 'estoque' => 25],
        ['nome' => 'Pomada Efecto Brillante', 'preco' => 7200, 'estoque' => 18],
        ['nome' => 'Aceite para Barba', 'preco' => 8900, 'estoque' => 22],
        ['nome' => 'Shampoo Anticaída', 'preco' => 9500, 'estoque' => 15],
        ['nome' => 'Balm Post-Afeitado', 'preco' => 5400, 'estoque' => 20],
        ['nome' => 'Minoxidil 5%', 'preco' => 21000, 'estoque' => 12],
        ['nome' => 'Cepillo para Barba', 'preco' => 4200, 'estoque' => 30],
        ['nome' => 'Navaja de Afeitar Clásica', 'preco' => 15800, 'estoque' => 8],
        ['nome' => 'Tijera de Precisión', 'preco' => 18500, 'estoque' => 6],
        ['nome' => 'Talco Refrescante', 'preco' => 3800, 'estoque' => 28],
        ['nome' => 'Colonia Barbershop', 'preco' => 13200, 'estoque' => 14],
        ['nome' => 'Kit Cuidado de Barba', 'preco' => 24500, 'estoque' => 10],
        ['nome' => 'Gel Fijador Extra Fuerte', 'preco' => 5900, 'estoque' => 26],
        ['nome' => 'Champú Carbón Activado', 'preco' => 10200, 'estoque' => 16],
    ];

    private const NOMES_BARBEIROS = [
        'Franco Gómez', 'Matías Rodríguez', 'Ezequiel Fernández', 'Nicolás Álvarez', 'Tomás Benítez',
    ];

    public function run(): void
    {
        $this->faker = FakerFactory::create('es_AR');
        $this->faker->seed(20260824);

        $this->call(RoleAndPermissionSeeder::class);

        $this->barbearia = Barbearia::firstOrCreate(
            ['slug' => 'barberia-el-punto'],
            [
                'nome' => 'Barbería El Punto',
                'cuit' => $this->faker->numerify('30-########-#'),
                'endereco' => $this->faker->streetAddress(),
                'cidade' => 'Buenos Aires',
                'provincia' => 'Buenos Aires',
                'telefone' => $this->faker->numerify('+54 9 11 ########'),
                'email' => 'contacto@barberiaelpunto.com.ar',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'moeda' => 'ARS',
                'status' => 'ativa',
                'idioma_padrao' => 'es',
                'exige_pagamento_antecipado' => false,
            ]
        );

        if (! $this->barbearia->logo_path) {
            $this->barbearia->update([
                'logo_path' => $this->salvarImagem('logos', $this->gerarLogo('EP', self::PALETA[0])),
            ]);
        }

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);

        $this->criarUsuario('admin@gmail.com', 'Guilherme Jeske', 'dono', 'dono');
        $atendente = $this->criarUsuario('atendente@barberiaelpunto.com.ar', $this->faker->name(), 'atendente', 'atendente');

        $servicos = $this->criarServicos();
        $produtos = $this->criarProdutos();
        $barbeiros = $this->criarBarbeiros($servicos);
        $clientes = $this->criarClientes();

        if (! Agendamento::exists()) {
            $this->gerarAgendamentos($barbeiros, $clientes, $servicos, $produtos, $atendente);
        } else {
            $this->command?->warn('Barbería El Punto já tem agendamentos — pulando geração de agenda/pagamentos pra não duplicar.');
        }

        $this->command?->info('Barbería El Punto: '.$barbeiros->count().' barbeiros, '.$servicos->count().' serviços, '.$produtos->count().' produtos, '.$clientes->count().' clientes.');
    }

    private function criarUsuario(string $email, string $nome, string $tipo, string $role): User
    {
        $barbeariaId = $tipo === 'cliente' ? null : $this->barbearia->id;

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nome,
                'password' => 'password',
                'telefone' => $this->faker->numerify('+54 9 11 ########'),
                'tipo' => $tipo,
                'barbearia_atual_id' => $barbeariaId,
                'idioma' => 'es',
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $user->unsetRelation('roles');
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function criarServicos()
    {
        return collect(self::SERVICOS)->map(fn (array $s) => Servico::updateOrCreate(
            ['barbearia_id' => $this->barbearia->id, 'nome' => $s['nome']],
            [
                'descricao' => $this->faker->sentence(10),
                'duracao_minutos' => $s['duracao'],
                'preco' => $s['preco'],
                'percentual_comissao_padrao' => $s['comissao'],
                'ativo' => true,
            ]
        ));
    }

    private function criarProdutos()
    {
        return collect(self::PRODUTOS)->values()->map(function (array $p, int $i) {
            $produto = Produto::updateOrCreate(
                ['barbearia_id' => $this->barbearia->id, 'nome' => $p['nome']],
                [
                    'descricao' => $this->faker->sentence(8),
                    'preco' => $p['preco'],
                    'estoque_qtd' => $p['estoque'],
                    'ativo' => true,
                ]
            );

            if (! $produto->foto_path) {
                $cor = self::PALETA[$i % count(self::PALETA)];
                $produto->update(['foto_path' => $this->salvarImagem('produtos', $this->gerarFotoProduto($p['nome'], $cor))]);
            }

            return $produto;
        });
    }

    private function criarBarbeiros($servicos)
    {
        return collect(self::NOMES_BARBEIROS)->values()->map(function (string $nome, int $i) use ($servicos) {
            $email = Str::slug($nome).'@barberiaelpunto.com.ar';
            $user = $this->criarUsuario($email, $nome, 'barbeiro', 'barbeiro');

            $barbeiro = Barbeiro::updateOrCreate(
                ['barbearia_id' => $this->barbearia->id, 'user_id' => $user->id],
                [
                    'nome' => $nome,
                    'percentual_comissao' => $this->faker->numberBetween(35, 50),
                    'ativo' => true,
                    'aceita_online' => true,
                ]
            );

            if (! $barbeiro->foto_path) {
                $iniciais = collect(explode(' ', $nome))->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
                $cor = self::PALETA[$i % count(self::PALETA)];
                $barbeiro->update(['foto_path' => $this->salvarImagem('barbeiros', $this->gerarAvatarBarbeiro($iniciais, $cor))]);
            }

            foreach ($servicos as $servico) {
                $barbeiro->servicos()->syncWithoutDetaching([
                    $servico->id => ['percentual_comissao_override' => $this->faker->boolean(20) ? $this->faker->numberBetween(35, 55) : null],
                ]);
            }

            foreach (range(1, 6) as $diaSemana) {
                BarbeiroHorario::firstOrCreate(
                    ['barbeiro_id' => $barbeiro->id, 'dia_semana' => $diaSemana],
                    [
                        'barbearia_id' => $this->barbearia->id,
                        'hora_inicio' => '09:00',
                        'hora_fim' => '20:00',
                        'intervalo_inicio' => '13:00',
                        'intervalo_fim' => '14:00',
                    ]
                );
            }

            BarbeiroBloqueio::firstOrCreate(
                ['barbeiro_id' => $barbeiro->id, 'motivo' => $this->faker->randomElement(['Vacaciones', 'Turno médico', 'Franco extra'])],
                [
                    'data_inicio' => now()->addDays($this->faker->numberBetween(5, 30))->setTime(0, 0),
                    'data_fim' => now()->addDays($this->faker->numberBetween(31, 40))->setTime(23, 59),
                ]
            );

            return $barbeiro;
        });
    }

    private function criarClientes()
    {
        return collect(range(1, 25))->map(function (int $i) {
            $nome = $this->faker->name();
            $telefone = $this->faker->numerify('+54 9 11 ########');
            $userId = null;

            if ($i <= 5) {
                $email = Str::slug($nome).$i.'@cliente.barberiaelpunto.com.ar';
                $userId = $this->criarUsuario($email, $nome, 'cliente', 'cliente')->id;
            }

            return Cliente::firstOrCreate(
                ['barbearia_id' => $this->barbearia->id, 'telefone' => $telefone],
                [
                    'nome' => $nome,
                    'email' => $this->faker->boolean(60) ? $this->faker->safeEmail() : null,
                    'dni' => $this->faker->numerify('########'),
                    'user_id' => $userId,
                    'idioma' => 'es',
                    'observacoes' => $this->faker->boolean(15) ? $this->faker->sentence(6) : null,
                ]
            );
        });
    }

    private function gerarAgendamentos($barbeiros, $clientes, $servicos, $produtos, User $atendente): void
    {
        $criadoresPossiveis = ['cliente_online', 'atendente', 'pdv'];

        foreach (range(1, 40) as $i) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servicosEscolhidos = $servicos->random(random_int(1, 2));
            $inicio = now()->subDays(random_int(1, 45))->setTime(random_int(9, 18), $this->faker->randomElement([0, 30]));
            $duracao = $servicosEscolhidos->sum('duracao_minutos');

            $agendamento = Agendamento::create([
                'barbearia_id' => $this->barbearia->id,
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
            if ($this->faker->boolean(35)) {
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
                'barbearia_id' => $this->barbearia->id,
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
                'barbearia_id' => $this->barbearia->id,
                'pagamento_id' => $pagamento->id,
                'valor' => $valorComissao,
                'status' => $this->faker->randomElement(['pendente', 'pendente', 'pago']),
                'data_referencia' => $agendamento->data_hora_fim->toDateString(),
            ]);

            if ($this->faker->boolean(60)) {
                $enviado = $agendamento->data_hora_fim->clone()->addHours(2);
                $respondido = $this->faker->boolean(70) ? $enviado->clone()->addHours(random_int(1, 30)) : null;

                PesquisaSatisfacao::create([
                    'barbearia_id' => $this->barbearia->id,
                    'agendamento_id' => $agendamento->id,
                    'nota' => $respondido ? $this->faker->randomElement([3, 4, 4, 5, 5, 5]) : null,
                    'comentario' => $respondido && $this->faker->boolean(50) ? $this->faker->sentence(8) : null,
                    'enviado_em' => $enviado,
                    'respondido_em' => $respondido,
                ]);

                $agendamento->update(['pesquisa_enviada_em' => $enviado]);
            }
        }

        foreach (range(1, 12) as $i) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servicosEscolhidos = $servicos->random(random_int(1, 2));
            $inicio = now()->addDays(random_int(1, 14))->setTime(random_int(9, 18), $this->faker->randomElement([0, 30]));
            $duracao = $servicosEscolhidos->sum('duracao_minutos');

            $agendamento = Agendamento::create([
                'barbearia_id' => $this->barbearia->id,
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

        foreach (['no_show', 'cancelado', 'no_show', 'cancelado', 'cancelado'] as $status) {
            $barbeiro = $barbeiros->random();
            $cliente = $clientes->random();
            $servico = $servicos->random();
            $inicio = now()->subDays(random_int(1, 20))->setTime(random_int(9, 18), 0);

            $agendamento = Agendamento::create([
                'barbearia_id' => $this->barbearia->id,
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

    private function salvarImagem(string $pasta, string $binario): string
    {
        $caminho = $pasta.'/'.Str::random(20).'.jpg';
        Storage::disk('public')->put($caminho, $binario);

        return $caminho;
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function capturarJpeg($imagem): string
    {
        ob_start();
        imagejpeg($imagem, null, 90);
        $binario = ob_get_clean();
        imagedestroy($imagem);

        return $binario;
    }

    private function textoNegrito($im, int $x, int $y, string $texto, int $corTexto): void
    {
        $fonte = 5;
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dy]) {
            imagestring($im, $fonte, $x + $dx, $y + $dy, $texto, $corTexto);
        }
    }

    /** Avatar estilo "barbeiro": círculo de fundo, silhueta com cabelo/barba e iniciais. */
    private function gerarAvatarBarbeiro(string $iniciais, string $corHex): string
    {
        $tam = 480;
        $im = imagecreatetruecolor($tam, $tam);
        [$r, $g, $b] = $this->rgb($corHex);
        $fundo = imagecolorallocate($im, $r, $g, $b);
        imagefill($im, 0, 0, $fundo);

        $pele = imagecolorallocate($im, 224, 172, 133);
        $cabelo = imagecolorallocate($im, 40, 30, 25);
        $roupa = imagecolorallocate($im, max($r - 30, 0), max($g - 30, 0), max($b - 30, 0));
        $branco = imagecolorallocatealpha($im, 255, 255, 255, 20);

        imagefilledellipse($im, 240, 470, 420, 340, $roupa);
        imagefilledellipse($im, 240, 210, 210, 240, $pele);
        imagefilledarc($im, 240, 150, 220, 200, 180, 360, $cabelo, IMG_ARC_PIE);
        imagefilledarc($im, 240, 290, 130, 90, 0, 180, $cabelo, IMG_ARC_PIE);

        imagefilledellipse($im, 240, 420, $tam, 140, $branco);
        $this->textoNegrito($im, 210, 400, $iniciais, imagecolorallocate($im, 255, 255, 255));

        return $this->capturarJpeg($im);
    }

    /** Foto de produto: garrafa/pote estilizado sobre fundo claro com rótulo. */
    private function gerarFotoProduto(string $nome, string $corHex): string
    {
        $tam = 480;
        $im = imagecreatetruecolor($tam, $tam);
        [$r, $g, $b] = $this->rgb($corHex);
        $fundoClaro = imagecolorallocate($im, 245, 243, 240);
        imagefill($im, 0, 0, $fundoClaro);

        $sombra = imagecolorallocate($im, 225, 222, 218);
        imagefilledellipse($im, 240, 420, 260, 40, $sombra);

        $corFrasco = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, 170, 160, 310, 400, $corFrasco);
        imagefilledrectangle($im, 200, 110, 280, 160, $corFrasco);
        imagefilledrectangle($im, 190, 90, 290, 115, imagecolorallocate($im, 30, 30, 30));

        imagefilledrectangle($im, 180, 230, 300, 300, imagecolorallocate($im, 255, 255, 255));

        $iniciais = collect(explode(' ', $nome))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
        $this->textoNegrito($im, 215, 255, $iniciais, imagecolorallocate($im, 30, 30, 30));

        return $this->capturarJpeg($im);
    }

    /** Logo da barbearia: emblema circular com tesoura estilizada e iniciais. */
    private function gerarLogo(string $iniciais, string $corHex): string
    {
        $tam = 480;
        $im = imagecreatetruecolor($tam, $tam);
        [$r, $g, $b] = $this->rgb($corHex);
        $transparenteBase = imagecolorallocate($im, 250, 250, 250);
        imagefill($im, 0, 0, $transparenteBase);

        $corPrincipal = imagecolorallocate($im, $r, $g, $b);
        $dourado = imagecolorallocate($im, 197, 160, 89);

        imagefilledellipse($im, 240, 240, 440, 440, $corPrincipal);
        imagearc($im, 240, 240, 400, 400, 0, 360, $dourado);
        imagearc($im, 240, 240, 390, 390, 0, 360, $dourado);

        imagesetthickness($im, 6);
        imageline($im, 150, 170, 330, 310, $dourado);
        imageline($im, 330, 170, 150, 310, $dourado);
        imagefilledellipse($im, 150, 170, 30, 30, $dourado);
        imagefilledellipse($im, 150, 310, 30, 30, $dourado);

        $this->textoNegrito($im, 195, 350, $iniciais, imagecolorallocate($im, 255, 255, 255));

        return $this->capturarJpeg($im);
    }
}
