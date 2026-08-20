# Architecture Decision Records

Registro das decisões de arquitetura tomadas neste projeto — o quê, por quê, e quais alternativas foram descartadas. Formato: [MADR](https://adr.github.io/madr/) simplificado (Status / Contexto / Decisão / Consequências).

| ADR | Título | Status |
|---|---|---|
| [0001](0001-multi-tenancy-single-database.md) | Multi-tenancy: banco único com `barbearia_id` + global scope | Aceito |
| [0002](0002-prevencao-overbooking.md) | Prevenção de overbooking: constraint de exclusão + lock transacional | Aceito |
| [0003](0003-split-pagamento-logico.md) | Split de comissão: modelo lógico interno (não split real via API) | Aceito |
| [0004](0004-livewire-4-classic-components.md) | Livewire 4 em modo classe clássica, não single-file component | Aceito |
| [0005](0005-roles-spatie-permission-teams.md) | Roles/permissions via spatie/laravel-permission com teams | Aceito |
| [0006](0006-i18n-es-pt.md) | Internacionalização es/pt com middleware de resolução em cadeia | Aceito |
| [0007](0007-auth-fortify-hibrido.md) | Auth: Fortify para login/logout/reset, fluxo próprio para registro | Aceito |
| [0008](0008-pagamento-antecipado-opcional.md) | Exigência de pagamento antecipado é opt-in e nunca bloqueia o cliente | Aceito |

Nova decisão relevante → novo arquivo `NNNN-titulo-curto.md`, adicionar linha na tabela acima.
