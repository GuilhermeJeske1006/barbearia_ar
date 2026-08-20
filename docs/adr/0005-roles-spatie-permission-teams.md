# 0005 — Roles/permissions via spatie/laravel-permission com teams

**Status:** Aceito

## Contexto

Um barbeiro pode trabalhar em mais de uma barbearia parceira, com papéis potencialmente diferentes em cada uma. Precisamos de um sistema de roles/permissions que suporte esse cenário sem modelar manualmente uma tabela de vínculos user↔barbearia↔role do zero.

`spatie/laravel-permission` tem suporte nativo a "teams": a mesma role (`barbeiro`, `dono`, etc.) pode ser atribuída a um usuário múltiplas vezes, uma por `team_id`, e as checagens de permissão (`$user->can(...)`) respeitam automaticamente o team ativo no momento (via `setPermissionsTeamId()`).

## Decisão

Habilitado `teams => true` em `config/permission.php`, com `team_foreign_key` renomeado de `team_id` (default do pacote) para `barbearia_id`, reaproveitando a mesma convenção de nome usada em todo o resto do schema — evita ter duas colunas com significado idêntico (`team_id` vs `barbearia_id`) em tabelas diferentes do mesmo projeto.

Roles e permissions em si (os nomes, ex. `barbeiro`, `pdv.operar`) são **globais** — só o vínculo usuário↔role é que carrega o `barbearia_id`. Isso está codificado no `RoleAndPermissionSeeder`: as 5 roles (`super_admin`, `dono`, `atendente`, `barbeiro`, `cliente`) e as permissions são criadas uma única vez, não por tenant.

## Consequências

- Atribuir uma role a um usuário dentro do contexto de uma barbearia específica exige chamar `app(PermissionRegistrar::class)->setPermissionsTeamId($barbeariaId)` antes de `$user->assignRole(...)` — isso deve acontecer dentro do fluxo de onboarding/convite de barbeiro, não foi implementado ainda (ver seção 14 do ARQUITETURA.md, "não implementado").
- O middleware `ResolveTenant` só popula `barbearia.id` no container para o global scope de dados (`BelongsToBarbearia`) — ele **não** chama `setPermissionsTeamId()` automaticamente ainda. Isso é uma lacuna a fechar antes de implementar checagens de permissão reais no painel: sem isso, `$user->hasRole('barbeiro')` pode retornar considerando o team_id errado (ou nenhum).
- Super Admin, por não ter `barbearia_id` de contexto, precisa de uma role global (`super_admin` com `team_id` null) ou de uma checagem separada fora do sistema de teams — a decisão de como exatamente isso funciona fica para quando as telas de Super Admin forem implementadas.
