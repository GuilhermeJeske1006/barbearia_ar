<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Cria um usuário tipo=super_admin. Não existe seeder pra isso de propósito
 * — credencial de super admin não deve viver hardcoded em código versionado
 * (DatabaseSeeder roda em qualquer ambiente, inclusive produção via CI/CD).
 *
 * Não atribui a role 'super_admin' via spatie/laravel-permission: a tabela
 * model_has_roles.barbearia_id é NOT NULL (migration padrão do pacote com
 * teams=true, não alterada), então assignRole() com team_id null quebra a
 * inserção. O acesso cross-tenant não depende disso de qualquer forma — vem
 * do Gate::before em AppServiceProvider, chaveado por users.tipo. Ver
 * docs/adr/0009.
 */
class CriarSuperAdmin extends Command
{
    protected $signature = 'superadmin:criar {email?} {--nome=}';

    protected $description = 'Cria um usuário Super Admin (acesso cross-tenant)';

    public function handle(): int
    {
        $email = $this->argument('email') ?? text('E-mail', required: true);
        $nome = $this->option('nome') ?? text('Nome', required: true);

        $validator = Validator::make(
            ['email' => $email, 'nome' => $nome],
            ['email' => ['required', 'email', 'unique:users,email'], 'nome' => ['required', 'string', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $senha = password('Senha', required: true, validate: fn ($value) => strlen($value) < 8
            ? 'A senha precisa de pelo menos 8 caracteres.'
            : null);

        $user = User::create([
            'name' => $nome,
            'email' => $email,
            'password' => Hash::make($senha),
            'tipo' => 'super_admin',
            'telefone' => '',
            'barbearia_atual_id' => null,
            'ativo' => true,
        ]);

        $this->info("Super Admin criado: {$user->email}");

        return self::SUCCESS;
    }
}
