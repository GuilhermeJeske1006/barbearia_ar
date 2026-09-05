# 0009 — Super Admin cross-tenant via Gate::before, fora do sistema de teams

**Status:** Aceito

## Contexto

`users.tipo` já tinha o valor `super_admin` e a role `super_admin` já existia no `RoleAndPermissionSeeder` (ver [0005](0005-roles-spatie-permission-teams.md)), mas sem nenhuma tela ou rota real — só comentários apontando pra isso. A ADR 0005 deixou em aberto: "a decisão de como exatamente isso funciona fica para quando as telas de Super Admin forem implementadas".

O problema de fundo: todo o resto do sistema de permissões é team-scoped (`spatie/laravel-permission` com `team_foreign_key = barbearia_id`, ver 0005) — uma role só vale dentro do `barbearia_id` atribuído. Super Admin, por definição, não pertence a nenhuma barbearia (`barbearia_atual_id` é `null`) e precisa agir sobre **todas**. Atribuir a role `super_admin` com `team_id = null` funcionaria parcialmente, mas toda checagem de permissão no código (`$user->can(...)`) depende do team ativo no container (`setPermissionsTeamId()`, bindado por `ResolveTenant`) — que nunca é setado nas rotas de Super Admin, já que elas não passam por esse middleware.

## Decisão

- **`Gate::before`** em `AppServiceProvider::boot()`: `$user->tipo === 'super_admin'` concede qualquer habilidade, antes de qualquer checagem de role/permission rodar. `superadmin:criar` **não** atribui a role `super_admin` do spatie/laravel-permission — `model_has_roles.barbearia_id` é `NOT NULL` na migration padrão do pacote (teams=true, não alterada), então `assignRole()` com team_id `null` quebra a inserção. Como o acesso não depende da role (só de `tipo`), a role ficou sem uso prático; a coluna `users.tipo` é a única fonte de verdade.
- **Rotas `/superadmin/*`** (`routes/superadmin.php`) ficam fora do grupo `/painel`: middleware `['auth', 'usuario.ativo', 'superadmin']`, sem `tenant`/`filial`/`assinatura.ativa`. `SuperAdminOnly` (novo middleware) barra com 403 quem não é `tipo === 'super_admin'`.
- **Criação do usuário** via comando artisan `superadmin:criar` (prompt de senha), não via seeder — credencial de super admin real não deve viver hardcoded em código versionado.
- **Redirect pós-login**: `config('fortify.home')` continua fixo em `/painel` pra todo mundo (Fortify não sabe distinguir por role antes de autenticar). `Painel::mount()` redireciona pra `superadmin.dashboard` quando `tipo === 'super_admin'`.

## Consequências

- Qualquer código que dependa de `$user->can(...)` já funciona corretamente para Super Admin sem precisar de tratamento especial espalhado pelo resto do app — o bypass mora em um único lugar.
- Telas de Super Admin (`App\Livewire\SuperAdmin\*`) que leem modelos tenant-scoped (`Barbearia`, etc.) usam `withoutGlobalScopes()`/queries diretas, já que não há `barbearia.id` bindado no container — mesmo padrão já usado em jobs/webhooks (ver [0001](0001-multi-tenancy-single-database.md)).
- `Gate::before` retorna `true` incondicionalmente pra `super_admin` — não há como restringir uma habilidade específica pra esse tipo de usuário sem adicionar uma exceção explícita na própria closure. Aceitável: Super Admin é por definição irrestrito nesse sistema.
- Ações de Super Admin sobre dados de terceiros (suspender barbearia, e futuramente impersonar usuário) devem logar quem fez o quê (`Log::info('superadmin.*', [...])`) — não há trilha de auditoria em tabela própria ainda; se o volume de ações crescer, vale revisitar.
