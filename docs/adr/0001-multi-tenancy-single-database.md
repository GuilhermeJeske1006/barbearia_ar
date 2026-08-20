# 0001 — Multi-tenancy: banco único com `barbearia_id` + global scope

**Status:** Aceito

## Contexto

O sistema atende múltiplas barbearias independentes na mesma plataforma. Precisamos de isolamento lógico de dados entre tenants (uma barbearia nunca deve ver dados de outra), mas a escala esperada no lançamento é de dezenas/centenas de barbearias — não milhares com exigência de isolamento físico ou de compliance que force banco-por-tenant.

Alternativas consideradas:
- **Banco por tenant** (ex.: `stancl/tenancy`): isolamento mais forte, mas exige provisionar/migrar N bancos, complica backups, connection pooling e queries cross-tenant (relatórios do Super Admin).
- **Schema por tenant** (Postgres): meio-termo, ainda exige gestão de N schemas e migrations replicadas.
- **Banco único, coluna `barbearia_id`**: mais simples operacionalmente, isolamento garantido na camada de aplicação.

## Decisão

Banco único. Toda tabela pertencente a um tenant tem `barbearia_id` (FK). O trait `App\Traits\BelongsToBarbearia` aplica um Global Scope Eloquent que filtra automaticamente por `barbearia_id` a partir do valor bindado no container (`app('barbearia.id')`), e auto-preenche esse campo na criação de novos registros.

O middleware `App\Http\Middleware\ResolveTenant` resolve o tenant ativo por:
1. Parâmetro de rota `{barbearia}` (slug) — rotas públicas `/b/{slug}`.
2. `users.barbearia_atual_id` do usuário autenticado — painel administrativo.

Super Admin não passa por esse middleware nas suas rotas, então nunca sofre o scope.

## Consequências

- Toda query de modelo tenant-scoped precisa do contexto de tenant bindado, ou retorna vazio — isso é intencional (fail-closed), mas significa que jobs/comandos em background (ex.: processar webhook) que rodam fora de uma request precisam usar `withoutGlobalScopes()` explicitamente e filtrar manualmente.
- Migração futura para banco-por-tenant continua possível se a escala exigir, mas não é o caminho mais curto — vira um projeto de migração de dados, não uma troca de flag.
- Índices em `barbearia_id` (e compostos, ex. `(barbearia_id, telefone)` em `clientes`) são obrigatórios para manter as queries eficientes conforme o número de tenants cresce, já que todas as tabelas compartilham o mesmo espaço físico.

### Correção: o scope era fail-*open*, não fail-closed (bug real, corrigido)

Esta ADR sempre descreveu o comportamento como fail-closed, mas a implementação original do `BelongsToBarbearia` fazia o oposto: só adicionava o `WHERE barbearia_id = ?` **se** houvesse um tenant bindado; sem bind, a query rodava **sem nenhum filtro** — ou seja, vazava registros de todas as barbearias em vez de não devolver nada. Isso só veio à tona ao implementar as notificações por e-mail (Fase 6): `ProcessarWebhookMercadoPagoAction` e o comando `agendamentos:enviar-lembretes` acessam modelos tenant-scoped (`Cliente`, via relação) fora do ciclo de uma request HTTP normal, onde não há `ResolveTenant` pra bindar nada — e o scope, sendo fail-open, deixava passar.

Corrigido: quando `app()->bound('barbearia.id')` é falso, o scope agora adiciona `whereRaw('1 = 0')` em vez de nenhum filtro. Isso quebrou (corretamente) qualquer código que dependesse implicitamente do vazamento — o que revelou os pontos reais que precisavam de tratamento explícito:

- **`ProcessarWebhookMercadoPagoAction`**: resolve o `Agendamento` via `withoutGlobalScopes()->find()` (não tem tenant ainda — é assim que descobre qual é), e a partir daí **bindа o tenant** (`app()->instance('barbearia.id', $agendamento->barbearia_id)`) antes de tocar em qualquer relação ou outro model tenant-scoped. As buscas de `Pagamento` que antes usavam `withoutGlobalScopes()` explicitamente pararam de precisar disso — ficam corretamente escopadas pelo bind.
- **`agendamentos:enviar-lembretes`**: varre todas as barbearias numa passada só (isso é correto, é um comando tipo Super Admin) — mas o eager-load de `cliente` precisa de `withoutGlobalScopes()` explícito nessa relação (`->with(['cliente' => fn ($q) => $q->withoutGlobalScopes()])`), e o loop rebinda o tenant por linha antes de qualquer outro acesso.
- Testes que acessavam relações tenant-scoped diretamente (não através de uma action/comando que já resolve isso) precisaram de bind manual no `setUp()` — mesmo padrão já usado nos testes de CRUD desde a Fase 1, só que agora **obrigatório** em vez de opcional.

`Model::fresh()`/`->update()` em uma instância já carregada **não** são afetados por este bug nem pela correção — o Eloquent usa `newModelQuery()` (sem scopes) internamente pra isso, então salvar/recarregar um registro já em mãos sempre funcionou independente do bind.

Ver `tests/Unit/BelongsToBarbeariaTest.php` — regressão dedicada garantindo que, sem tenant bindado, a contagem é sempre zero (nunca "todos os registros").
