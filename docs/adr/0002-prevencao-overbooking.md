# 0002 — Prevenção de overbooking: constraint de exclusão + lock transacional

**Status:** Aceito

## Contexto

Dois canais diferentes — agendamento público online e PDV/tablet — podem tentar reservar o mesmo barbeiro no mesmo horário simultaneamente. Sem uma guarda real, uma corrida entre dois requests concorrentes pode confirmar dois agendamentos sobrepostos para o mesmo barbeiro.

O `DisponibilidadeService` calcula slots livres para exibição na UI, mas essa lista é só um retrato do momento da consulta — pode ficar desatualizada entre a consulta e a confirmação.

## Decisão

Duas camadas de defesa, não uma só:

1. **Camada de banco (Postgres apenas):** a migration `create_agendamentos_table` cria, condicionalmente ao driver ser `pgsql`, uma constraint `EXCLUDE USING gist (barbeiro_id WITH =, tsrange(data_hora_inicio, data_hora_fim) WITH &&) WHERE (status NOT IN ('cancelado','no_show'))`. O banco rejeita fisicamente qualquer INSERT/UPDATE que sobreponha um intervalo já ocupado do mesmo barbeiro — não há como escapar dessa checagem, nem por bug de aplicação.
2. **Camada de aplicação (todos os drivers):** `DisponibilidadeService::estaLivre()` roda dentro de uma transação com `lockForUpdate()` no momento de confirmar o agendamento (`CriarAgendamentoAction`), servindo como guarda em MySQL/SQLite, onde a constraint de exclusão não existe.

## Consequências

- Em produção com Postgres, ficamos protegidos mesmo se algum código futuro esquecer de usar `CriarAgendamentoAction` e inserir direto no Eloquent — a constraint do banco pega de qualquer forma.
- Em dev com SQLite (ou se algum dia migrar para MySQL), a proteção depende inteiramente da disciplina de sempre passar pela Action/Service — não há rede de segurança no nível do banco.
- `btree_gist` precisa estar habilitada no Postgres (a migration já roda `CREATE EXTENSION IF NOT EXISTS btree_gist`), o que exige permissão de superuser ou de criar extensões no banco gerenciado — validar isso ao escolher provedor de hosting do Postgres em produção.
