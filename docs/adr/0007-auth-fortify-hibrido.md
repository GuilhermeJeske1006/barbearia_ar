# 0007 — Auth: Fortify para login/logout/reset, fluxo próprio para registro

**Status:** Aceito

## Contexto

Precisávamos de login, registro e logout funcionando, com o registro criando um dono + uma barbearia numa única operação (ver decisão de onboarding self-service, registrada no commit que introduziu `RegistrarDonoEBarbeariaAction`). Laravel Fortify (já na stack por decisão do documento de arquitetura, seção 2) resolve autenticação por padrão, mas seu fluxo de registro (`Features::registration()` + `CreateNewUser` action) só sabe criar um `User` — não tem noção de criar uma `Barbearia` junto, e não há um ponto de extensão limpo para isso sem reescrever o controller.

Fortify também, por padrão (`'views' => true`), registra suas próprias rotas GET (`/login`, `/register`, etc.) esperando views Blade nomeadas por convenção — não Livewire.

## Decisão

Híbrido, dividido por responsabilidade:

- **`'views' => false`** em `config/fortify.php`: Fortify não registra as rotas GET. Nós definimos `GET /login` e `GET /register` em `routes/auth.php`, apontando direto para `App\Livewire\Auth\Login` e `App\Livewire\Auth\Register` (componentes roteáveis, consistente com a ADR-0004).
- **Login**: a view do componente Livewire é um `<form method="POST" action="{{ route('login.store') }}">` comum — não usa `wire:submit`. O POST cai direto na `AuthenticatedSessionController::store` do Fortify, reaproveitando 100% do pipeline já testado do pacote (rate limiting configurado em `FortifyServiceProvider`, remember-me, regeneração de sessão). O componente Livewire existe só para manter a página no lugar convencional do projeto — ele não reimplementa nada de autenticação.
- **Registro**: `Features::registration()` está **desabilitada**. O componente `Register` usa `wire:submit="registrar"`, que chama `App\Actions\Auth\RegistrarDonoEBarbeariaAction` diretamente — cria `Barbearia` + `User` (tipo `dono`) numa transação, atribui a role `dono` via `setPermissionsTeamId()` (fechando a lacuna registrada na ADR-0005), e loga o usuário com `Auth::login()`.
- **2FA e passkeys**: desabilitados em `features` (comentados, não removidos) — o scaffolding de actions do Fortify já existe pronto para quando a UI for construída.
- **Redirect pós-login**: `config('fortify.home')` alterado de `/home` (default) para `/painel`.

## Consequências

- Login continua "de graça" com qualquer melhoria futura do Fortify (novas proteções, correções de segurança) sem precisar tocar em código nosso — é literalmente a rota do pacote.
- Registro é 100% nosso: qualquer mudança de regra de negócio no onboarding (ex.: exigir CUIT no cadastro, adicionar um passo de convite de barbeiros) é só editar `RegistrarDonoEBarbeariaAction`, sem cruzar com internals do Fortify.
- Se 2FA for habilitado no futuro, o login por form simples deixa de bastar — o fluxo de 2FA do Fortify espera que a autenticação inicial passe pelo `AuthenticatedSessionController` (o que já acontece aqui) e redirecione para `two-factor.login` quando aplicável; como já usamos a rota nativa do Fortify para o POST de login, isso deve funcionar ao simplesmente reativar a feature — mas não foi testado nesta passada.
- `RoleAndPermissionSeeder` precisa rodar antes de qualquer registro em produção (cria a role `dono` que `RegistrarDonoEBarbeariaAction` atribui) — isso já é coberto por `DatabaseSeeder`, mas é uma dependência implícita a não esquecer em scripts de deploy/migração inicial.
