# Arquitetura do Sistema de Barbearias (Argentina) — SaaS Multi-tenant

**Stack:** Laravel + Livewire (monolito server-driven), MySQL/PostgreSQL, Mercado Pago (Marketplace/Connect)
**Modelo de negócio:** SaaS multi-tenant — múltiplas barbearias independentes usam a mesma plataforma, cada uma com seus próprios barbeiros, clientes, produtos e agenda, com isolamento lógico de dados.
**Idiomas:** interface bilíngue, com seletor de idioma Espanhol (padrão) / Português (ver seção 13).

> Decisões de arquitetura com justificativa detalhada (contexto, alternativas descartadas, consequências) ficam em [`docs/adr/`](adr/README.md).

---

## 1. Visão geral

O sistema permite que qualquer barbearia na Argentina se cadastre na plataforma, configure seus barbeiros (com a comissão de cada um), seus serviços/produtos, sua agenda de horários, e comece a receber agendamentos online através de um link público. Cada barbearia também opera um PDV em tablet na recepção para venda direta e cobrança na hora, com o pagamento via Mercado Pago já dividindo automaticamente o valor entre a conta da barbearia e a conta do barbeiro conforme o percentual configurado.

Dois grandes fluxos de entrada de receita convivem no sistema:

1. **Agendamento online** (cliente reserva um horário pelo link público, paga ou não antecipadamente).
2. **Venda direta no PDV/tablet** (cliente chega, atendente ou o próprio cliente seleciona barbeiro + horário livre no momento e paga na hora).

Ambos os fluxos convergem para a mesma entidade central: o **Atendimento** (agendamento confirmado/realizado), que é o que gera a comissão do barbeiro e o registro financeiro.

---

## 2. Stack tecnológica

| Camada | Tecnologia | Observação |
|---|---|---|
| Backend/Frontend | Laravel 11 + Livewire 3 | Monolito, sem SPA separada |
| UI Components | Livewire + Alpine.js + Tailwind CSS | Flux UI ou similar para componentes prontos |
| Banco de dados | PostgreSQL (recomendado) ou MySQL 8 | Postgres lida melhor com constraints de agenda/exclusão de horário |
| Autenticação | Laravel Fortify/Breeze + Livewire | Sessões, 2FA opcional |
| Autorização | spatie/laravel-permission | Roles e permissions por tenant |
| Multi-tenancy | Single database, coluna `barbearia_id` + Global Scopes | Ver seção 4 |
| Pagamentos | Mercado Pago SDK PHP (`mercadopago/dx-php`) | OAuth Marketplace + Checkout Pro/Bricks + Webhooks |
| Filas/Jobs | Laravel Queue (database ou Redis) | Processar webhooks do MP, notificações, lembretes |
| Notificações | WhatsApp (API oficial ou provedor tipo Twilio/Wati) + E-mail | Lembrete de agendamento é crítico para reduzir no-show |
| Cache | Redis | Sessões, cache de disponibilidade de horários |
| Storage | S3-compatible (ou local) | Fotos de barbeiros, logo da barbearia, fotos de produtos |
| Deploy | Docker + Laravel Forge/Vapor ou VPS | Considerar timezone `America/Argentina/Buenos_Aires` e moeda ARS |

> **Nota de implementação (scaffold inicial):** este repositório foi criado com Laravel 13 e Livewire 4 (versões atuais disponíveis no momento do scaffold), usando componentes Livewire em modo **classe clássica** (`--class`) para manter a estrutura `app/Livewire/*.php` descrita na seção 8 — Livewire 4 tem também um modo de single-file component que não foi usado aqui. Banco de dev local está em SQLite por conveniência; Postgres é a recomendação para produção (ver seção 5.5 sobre a constraint de exclusão).

---

## 3. Papéis de usuário (roles)

| Role | Escopo | Permissões principais |
|---|---|---|
| **Super Admin** | Plataforma inteira | Gerencia barbearias cadastradas, planos, suporte, vê métricas globais |
| **Dono/Admin da Barbearia** | 1 barbearia | Cadastra barbeiros, produtos, serviços, configura MP, vê relatórios financeiros e comissões |
| **Atendente/Recepção** | 1 barbearia | Opera o PDV/tablet, gerencia agenda, cadastra clientes |
| **Barbeiro** | 1 barbearia (pode atuar em mais de uma) | Vê sua própria agenda, seus atendimentos, suas comissões |
| **Cliente** | Público/autoatendimento | Agenda pelo link público, acompanha seus agendamentos (sem necessariamente logar — pode ser via telefone/CPF+código) |

Implementado com `spatie/laravel-permission`, com um **Team/Tenant scoping** nativo do próprio pacote (ele suporta `teams` — usamos `barbearia_id` como "team_id"), garantindo que um usuário pode ter roles diferentes em barbearias diferentes (ex.: um barbeiro que trabalha em 2 barbearias parceiras).

---

## 4. Estratégia de multi-tenancy

Para o estágio atual (SaaS mas provavelmente dezenas/centenas de barbearias, não milhares com necessidade de isolamento físico), a abordagem recomendada é:

- **Banco único, linha por tenant**: toda tabela relevante tem `barbearia_id` (FK).
- **Global Scope automático**: um trait `BelongsToBarbearia` aplica um `Global Scope` que filtra automaticamente pelo `barbearia_id` da barbearia ativa na sessão (para usuários) ou do contexto (para rotas públicas).
- **Middleware `ResolveTenant`**: identifica a barbearia atual por:
  - Subdomínio (`barbearia-x.suaplataforma.com.ar`) **ou**
  - Slug na URL (`suaplataforma.com.ar/b/barbearia-x`) — mais simples de começar
- **Super Admin** não sofre o scope (bypassa via role check).

Essa abordagem evita a complexidade operacional de "database per tenant" (múltiplos bancos, migrations em massa) enquanto mantém isolamento lógico robusto. Migração futura para banco-por-tenant (ex.: com `stancl/tenancy`) fica possível se a escala exigir.

---

## 5. Modelagem de dados (entidades principais)

### 5.1 Núcleo / Tenant

**`barbearias`**
| Campo | Tipo | Obs |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | usado na URL pública |
| cuit | string nullable | identificador fiscal argentino |
| endereco, cidade, provincia | string | Argentina: usar províncias, não "estado" |
| telefone, email | string | |
| logo_path | string nullable | |
| timezone | string default `America/Argentina/Buenos_Aires` | |
| moeda | string default `ARS` | |
| mp_user_id | string nullable | ID da conta Mercado Pago conectada (vendedor principal) |
| mp_access_token / mp_refresh_token | text encrypted | tokens OAuth do Marketplace |
| mp_public_key | string nullable | |
| status | enum(ativa, suspensa, trial) | |
| plano_id | FK nullable | se houver monetização por assinatura da própria plataforma |
| idioma_padrao | enum(es, pt) default `es` | idioma padrão usado na página pública de agendamento (`/b/{slug}`) e em notificações, quando o cliente não escolher outro |
| created_at / updated_at | timestamp | |

**`users`**
| Campo | Tipo | Obs |
|---|---|---|
| id | bigint PK | |
| name, email, password | | |
| telefone | string nullable | |
| tipo | enum(super_admin, dono, atendente, barbeiro, cliente) | redundante com roles, útil para queries rápidas |
| barbearia_atual_id | FK nullable | contexto ativo (para users com múltiplos vínculos) |
| idioma | enum(es, pt) nullable | preferência de idioma do usuário no painel; se nulo, usa `idioma_padrao` da barbearia (ou espanhol, se não houver contexto) |
| email_verified_at, remember_token | | |

### 5.2 Barbeiros e comissão

**`barbeiros`**
| Campo | Tipo | Obs |
|---|---|---|
| id | PK | |
| user_id | FK users | login do barbeiro (nullable se ele não tiver acesso ao sistema) |
| barbearia_id | FK | |
| nome | string | (pode ser distinto do user, para barbeiro sem login) |
| foto_path | string nullable | |
| percentual_comissao | decimal(5,2) | ex.: 50.00 = 50% — **default da barbearia**, pode ser sobrescrito por serviço |
| mp_user_id | string nullable | conta MP do próprio barbeiro, para receber o split |
| mp_access_token / mp_refresh_token | text encrypted | se o barbeiro conectar conta própria (marketplace multi-vendor) |
| ativo | boolean | |
| aceita_online | boolean | se aparece para agendamento público |

**`barbeiro_servico`** (pivot — permite comissão diferente por serviço)
| Campo | Tipo |
|---|---|
| barbeiro_id | FK |
| servico_id | FK |
| percentual_comissao_override | decimal nullable |

**`barbeiro_horarios`** (escala de trabalho recorrente)
| Campo | Tipo | Obs |
|---|---|---|
| id | PK | |
| barbeiro_id | FK | |
| barbearia_id | FK | |
| dia_semana | tinyint (0-6) | |
| hora_inicio, hora_fim | time | |
| intervalo_inicio, intervalo_fim | time nullable | almoço |

**`barbeiro_bloqueios`** (folgas, férias, exceções pontuais)
| Campo | Tipo |
|---|---|
| id | PK |
| barbeiro_id | FK |
| data_inicio, data_fim | datetime |
| motivo | string nullable |

### 5.3 Produtos e serviços

**`produtos`** (itens vendidos, ex.: pomada, shampoo)
| Campo | Tipo |
|---|---|
| id | PK |
| barbearia_id | FK |
| nome, descricao | string/text |
| preco | decimal(10,2) |
| estoque_qtd | int nullable |
| ativo | boolean |
| foto_path | string nullable |

**`servicos`** (o que é "agendável" — corte, barba, combo)
| Campo | Tipo | Obs |
|---|---|---|
| id | PK | |
| barbearia_id | FK | |
| nome | string | ex.: "Corte + Barba" |
| duracao_minutos | int | usado para calcular slots da agenda |
| preco | decimal(10,2) | |
| percentual_comissao_padrao | decimal nullable | pode herdar da barbearia |
| ativo | boolean | |

### 5.4 Cliente

**`clientes`**
| Campo | Tipo |
|---|---|
| id | PK |
| barbearia_id | FK (ou global, se cliente puder ser compartilhado entre barbearias — recomendo por barbearia para simplicidade de LGPD/dados) |
| nome | string |
| telefone | string (usado como identificador principal na Argentina — WhatsApp) |
| email | string nullable |
| dni | string nullable |
| user_id | FK nullable | se o cliente tiver conta/login |
| observacoes | text nullable |

### 5.5 Agendamento / Atendimento (entidade central)

**`agendamentos`**
| Campo | Tipo | Obs |
|---|---|---|
| id | PK | |
| barbearia_id | FK | |
| barbeiro_id | FK | |
| cliente_id | FK | |
| criado_por | enum(cliente_online, atendente, pdv) | origem |
| data_hora_inicio | datetime | |
| data_hora_fim | datetime | calculado por duração do(s) serviço(s) |
| status | enum(pendente, confirmado, em_atendimento, concluido, cancelado, no_show) | |
| origem_pdv | boolean | true = veio da tela de venda direta (tablet) |
| observacoes | text nullable | |
| pagamento_id | FK nullable | |
| created_at/updated_at | | |

**`agendamento_servico`** (pivot — permite múltiplos serviços num mesmo agendamento)
| Campo | Tipo |
|---|---|
| agendamento_id | FK |
| servico_id | FK |
| preco_cobrado | decimal | snapshot do preço no momento (preços podem mudar depois) |
| percentual_comissao_aplicado | decimal | snapshot da comissão no momento |

**`agendamento_produto`** (produtos vendidos junto, ex.: cliente comprou pomada no balcão)
| Campo | Tipo |
|---|---|
| agendamento_id | FK nullable | pode ser venda avulsa sem agendamento |
| produto_id | FK |
| quantidade | int |
| preco_cobrado | decimal |

> **Nota de disponibilidade**: para evitar overbooking, a tabela `agendamentos` deve ter uma **constraint de exclusão** (Postgres `EXCLUDE USING gist` sobre `barbeiro_id` + range de `[data_hora_inicio, data_hora_fim)`) ou, no mínimo, uma validação transacional com `lockForUpdate()` antes de confirmar. Isso é crítico porque dois canais diferentes (cliente online e PDV) podem tentar reservar o mesmo horário simultaneamente.
>
> No scaffold, a migration `create_agendamentos_table` cria a constraint `EXCLUDE` automaticamente quando o driver é `pgsql`, e o `DisponibilidadeService::estaLivre()` faz o `lockForUpdate()` transacional que serve como guarda em qualquer driver (inclusive MySQL/SQLite, onde a constraint de banco não existe).

### 5.6 Pagamentos (Mercado Pago)

**`pagamentos`**
| Campo | Tipo | Obs |
|---|---|---|
| id | PK | |
| barbearia_id | FK | |
| agendamento_id | FK nullable | |
| cliente_id | FK nullable | |
| valor_total | decimal(10,2) | |
| valor_comissao_barbeiro | decimal(10,2) | calculado |
| valor_barbearia | decimal(10,2) | valor_total - comissão (- taxa MP) |
| metodo | enum(mp_checkout, mp_point, dinheiro, outro) | tablet pode ter leitor físico MP Point também |
| mp_payment_id | string nullable | ID retornado pelo Mercado Pago |
| mp_preference_id | string nullable | |
| mp_status | string | approved, pending, rejected, refunded, etc. |
| mp_split_status | string nullable | status do repasse ao barbeiro |
| forma_split | enum(marketplace_auto, manual) | |
| pago_em | datetime nullable | |
| raw_payload | json nullable | resposta bruta do MP para auditoria |
| created_at/updated_at | | |

**`comissoes`** (livro-razão de comissões por barbeiro — pode ser derivado de `pagamentos`, mas materializar facilita relatórios)
| Campo | Tipo |
|---|---|
| id | PK |
| barbeiro_id | FK |
| barbearia_id | FK |
| pagamento_id | FK |
| valor | decimal |
| status | enum(pendente, pago, estornado) |
| data_referencia | date | para fechamento mensal/quinzenal |

### 5.7 Link de agendamento público

Não precisa de tabela própria — é uma rota pública `GET /b/{slug}` que renderiza um componente Livewire (wizard: escolhe serviço → escolhe barbeiro (ou "qualquer disponível") → escolhe data/horário livre → dados do cliente → paga ou confirma sem pagamento, conforme configuração da barbearia).

Opcionalmente, uma tabela `links_agendamento` se você quiser links customizados por barbeiro específico (ex.: `suaplataforma.com.ar/b/barbearia-x/joao`).

---

## 6. Fluxos principais

### 6.1 Agendamento online (cliente)

1. Cliente acessa `suaplataforma.com.ar/b/{slug-barbearia}`.
2. Escolhe serviço(s) → sistema calcula duração total.
3. Escolhe barbeiro (ou "sem preferência").
4. Sistema calcula **slots disponíveis** cruzando: `barbeiro_horarios` (escala recorrente) − `barbeiro_bloqueios` (folgas) − `agendamentos` já existentes no período, respeitando a duração do serviço.
5. Cliente escolhe horário, informa nome/telefone (ou loga).
6. Se a barbearia exige pagamento antecipado (configurável): gera preferência de pagamento no Mercado Pago (Checkout Pro ou Bricks) já com o **split marketplace** configurado (ver seção 7) → cliente paga → webhook confirma → `agendamento.status = confirmado`.
7. Se não exige pagamento antecipado: `agendamento.status = confirmado` direto, pagamento é feito presencialmente.
8. Disparo de notificação (WhatsApp/e-mail) de confirmação e lembrete (ex.: 2h antes).

### 6.2 Venda direta / PDV (tablet)

Tela otimizada para touch, layout grande, poucos cliques:

1. Tela inicial: grid de **barbeiros disponíveis agora** (com foto, nome, e indicador se está livre/ocupado).
2. Atendente/cliente toca no barbeiro → mostra próximos horários livres do dia (ou "agora" se ele estiver livre).
3. Seleciona serviço(s)/produto(s) → resumo do valor total.
4. Botão "Pagar" → integra com **Mercado Pago Point** (maquininha pareada via Bluetooth com o tablet, usando o SDK Point/Smart) **ou** gera QR Code dinâmico (Checkout Pro / Payment Brick) para o cliente pagar pelo celular.
5. Webhook confirma pagamento → cria/atualiza `agendamento` com `status = concluido`, `origem_pdv = true`, `pagamento` vinculado, comissão calculada automaticamente.
6. Tela de "sucesso" com opção de imprimir/enviar recibo.

### 6.3 Controle de vagas

Serviço centralizado `DisponibilidadeService`:

- Input: `barbeiro_id`, `data`, `duracao_minutos`.
- Cruza escala + bloqueios + agendamentos existentes.
- Retorna array de slots livres (ex.: de 30 em 30 min, configurável por barbearia).
- Usado tanto pelo agendamento público quanto pelo PDV — **fonte única da verdade**, evitando duplicidade de regras.

---

## 7. Integração com Mercado Pago (split automático)

### 7.1 Modelo: Marketplace / OAuth Connect

O Mercado Pago oferece o fluxo de **Marketplace** onde:

- A **plataforma** (você) se registra como aplicação no Mercado Pago Developers e obtém `Client ID`/`Client Secret`.
- Cada **barbearia** conecta sua própria conta Mercado Pago via **OAuth** (fluxo "Conectar com Mercado Pago") — armazenando `mp_access_token`/`mp_refresh_token` em `barbearias`.
- Opcionalmente, cada **barbeiro** também conecta sua própria conta MP (se o modelo de negócio pagar o barbeiro diretamente, e não só via relatório interno).
- Ao criar a preferência de pagamento (ou o pagamento direto via Bricks), usa-se o campo **`marketplace_fee`** (ou `application_fee`, dependendo do produto MP) para reter a parte da plataforma, e o parâmetro de **split** para direcionar o valor à conta do vendedor (barbearia) — o pagamento é processado **na conta da barbearia**, com uma fração retida para a plataforma como taxa de uso do SaaS.
- Para o split adicional **barbearia → barbeiro**, há duas abordagens:
  - **(a) Split real de 3 pontas** via `Order` API do Mercado Pago (mais recente) permitindo múltiplos recebedores num único pagamento — recomendado se o volume justificar a complexidade.
  - **(b) Split "lógico" interno**: o pagamento inteiro entra na conta MP da barbearia; o sistema apenas **calcula e registra** (tabela `comissoes`) quanto é devido a cada barbeiro, e o repasse físico é feito pela barbearia por fora (transferência bancária/pix argentino equivalente) — mais simples de implementar e mais comum na prática de barbearias pequenas.

  > Recomendação prática: começar com **(b)** para o MVP (menor complexidade de compliance/KYC de múltiplos vendedores) e evoluir para **(a)** se os próprios barbeiros precisarem receber automaticamente na própria conta MP.
  >
  > O scaffold implementa (b): `ProcessarWebhookMercadoPagoAction` calcula a comissão e grava em `comissoes`, sem mover dinheiro entre contas MP.

### 7.2 Webhooks

- Endpoint `POST /webhooks/mercadopago` (público, validando assinatura `x-signature`).
- Recebe eventos `payment.created`, `payment.updated`.
- Job assíncrono `ProcessarWebhookMercadoPago` consulta a API do MP para confirmar o status real (nunca confia só no payload do webhook), atualiza `pagamentos.mp_status`, e dispara side-effects (confirmar agendamento, gerar comissão, notificar cliente).
- Idempotência: usar `mp_payment_id` como chave única para evitar processar o mesmo evento duas vezes.

### 7.3 Formas de pagamento no PDV (tablet)

- **Mercado Pago Point** (maquininha física) via SDK/Point Integration API — ideal para cartão presencial.
- **QR Code dinâmico** (Bricks/Checkout Pro) — cliente paga com o próprio app do Mercado Pago.
- Ambos passam pelo mesmo pipeline de webhook.

---

## 8. Estrutura de pastas (Laravel + Livewire)

```
app/
  Actions/
    Agendamento/
      CriarAgendamentoAction.php
      CalcularSlotsDisponiveisAction.php
    Pagamento/
      CriarPreferenciaMercadoPagoAction.php
      ProcessarWebhookMercadoPagoAction.php
      CalcularComissaoAction.php
  Livewire/
    Public/
      AgendamentoWizard.php          // fluxo público do cliente
    Pdv/
      TelaVendaDireta.php            // tela do tablet
      SelecionarBarbeiro.php
      Checkout.php
    Admin/
      Barbeiros/CrudBarbeiro.php
      Servicos/CrudServico.php
      Produtos/CrudProduto.php
      Agenda/CalendarioAgenda.php
      Relatorios/RelatorioComissoes.php
    Barbeiro/
      MinhaAgenda.php
      MinhasComissoes.php
  Models/
    Barbearia.php
    Barbeiro.php
    Cliente.php
    Servico.php
    Produto.php
    Agendamento.php
    Pagamento.php
    Comissao.php
  Services/
    DisponibilidadeService.php
    MercadoPagoService.php
    ComissaoService.php
  Http/Middleware/
    ResolveTenant.php
    EnsureBarbeariaAtiva.php
  Traits/
    BelongsToBarbearia.php
routes/
  web.php          // rotas autenticadas do painel
  public.php       // rotas públicas (/b/{slug})
  webhooks.php      // webhook MP
```

---

## 9. Tela de PDV (tablet) — diretrizes de UX

- Layout **landscape**, botões grandes (mínimo 64px de área tocável), alto contraste.
- Fluxo em no máximo **3-4 telas**: Barbeiro → Serviço/Produto → Resumo/Pagamento → Confirmação.
- Modo "quiosque" (kiosk mode do navegador/tablet) para impedir que o cliente saia do app.
- Timeout de sessão automático (volta pra tela inicial após X segundos de inatividade) para o próximo cliente.
- Indicador visual claro de status do pagamento (processando / aprovado / recusado) com polling ou broadcast via Laravel Echo/WebSockets enquanto aguarda confirmação do MP.
- Opção de o **atendente logar** com PIN rápido (não a senha completa) para autorizar descontos/cancelamentos.

---

## 10. Controle de login e segurança

- Laravel Fortify (ou Breeze) + Livewire para telas de login/registro.
- 2FA opcional para donos de barbearia (dados financeiros sensíveis).
- Roles via `spatie/laravel-permission` com **teams** = `barbearia_id`.
- Tokens do Mercado Pago (`mp_access_token`, `mp_refresh_token`) **sempre criptografados** (`encrypted` cast do Eloquent) e nunca expostos ao frontend.
- Rate limiting nas rotas públicas de agendamento (evitar spam de reservas).
- Auditoria: registrar quem criou/alterou/cancelou cada agendamento (`created_by`, `updated_by`).

---

## 11. Requisitos específicos da Argentina

- **Moeda**: ARS, formatação `$ 1.234,56` (ponto para milhar, vírgula para decimal).
- **Timezone**: `America/Argentina/Buenos_Aires` (considerar que a Argentina não usa horário de verão desde 2009 — mais simples que o Brasil).
- **Identificação fiscal**: CUIT/CUIL para a barbearia (pode ser exigido pelo Mercado Pago Argentina no onboarding OAuth).
- **Mercado Pago Argentina** tem particularidades locais (métodos de pagamento como Rapipago/Pago Fácil em dinheiro, cuotas/parcelamento) — validar quais métodos habilitar no Checkout.
- **Idioma**: idioma padrão da interface é o espanhol (rioplatense/argentino), mas o sistema oferece **seletor de idioma Espanhol/Português** (ver seção 13) para atender donos/barbeiros e clientes brasileiros ou que prefiram português.

---

## 12. Roadmap de implementação sugerido (MVP → evolução)

**Fase 1 — Fundação**
- Auth + multi-tenancy + CRUD de Barbearia, Barbeiro, Serviço, Produto, Cliente.
- Escalas de horário e bloqueios.
- Estrutura de internacionalização (`lang/es/`, `lang/pt_BR/`, middleware `SetLocale`, componente `LanguageSwitcher` — ver seção 13).

**Fase 2 — Agenda**
- `DisponibilidadeService` + calendário admin (Livewire + FullCalendar.js ou similar).
- Link público de agendamento (sem pagamento ainda).

**Fase 3 — Pagamentos**
- Conexão OAuth com Mercado Pago por barbearia.
- Checkout no agendamento público (split lógico simples — modelo (b) da seção 7.1).
- Webhook + confirmação automática.

**Fase 4 — PDV/Tablet**
- Tela de venda direta.
- Integração Mercado Pago Point (maquininha) e/ou QR dinâmico.

**Fase 5 — Financeiro/Comissões**
- Relatórios de comissão por barbeiro/período.
- Fechamento e exportação (PDF/Excel).

**Fase 6 — Refinamento**
- Notificações WhatsApp automáticas (confirmação + lembrete).
- Split real via Order API (modelo (a)), se necessário.
- App/PWA para o barbeiro acompanhar a própria agenda no celular.

---

## 13. Internacionalização (i18n) — seletor de idioma Espanhol/Português

O sistema deve suportar dois idiomas de interface: **Espanhol (padrão, `es`)** e **Português (`pt-BR`)**, com um seletor visível para o usuário trocar a qualquer momento.

### 13.1 Onde o idioma é escolhido

| Contexto | Como funciona |
|---|---|
| Painel administrativo (dono, atendente, barbeiro) | Seletor de idioma no topo/menu do usuário. A escolha é salva em `users.idioma` e passa a valer em todos os logins seguintes. |
| Página pública de agendamento (`/b/{slug}`) | Seletor de idioma visível no cabeçalho da página (bandeiras ou dropdown "ES / PT"). Detecta automaticamente o idioma preferido do navegador (`Accept-Language`) na primeira visita como sugestão, mas o cliente pode trocar manualmente. A escolha é salva em cookie/sessão para a visita atual. |
| Tela de PDV/tablet | Seletor de idioma na tela inicial (útil se a barbearia atender público brasileiro/turistas), com fallback para `barbearias.idioma_padrao`. |
| Notificações (WhatsApp/e-mail) | Enviadas no idioma salvo do cliente (`clientes.idioma`, ver abaixo) ou no `idioma_padrao` da barbearia se não houver preferência registrada. |

Adicionar campo **`idioma`** (`enum(es, pt)` nullable) também na tabela `clientes`, capturado no momento do agendamento, para que lembretes e confirmações futuras já saiam no idioma correto sem precisar perguntar de novo.

### 13.2 Implementação técnica (Laravel)

- Arquivos de tradução em `lang/es/*.php` e `lang/pt_BR/*.php` (usar o formato de arrays do Laravel, com chaves nomeadas — evitar `__('Texto literal em espanhol')` direto no código, para não acoplar a string original a um idioma específico).
- **Middleware `SetLocale`**: roda em toda request e resolve o idioma ativo nesta ordem de prioridade:
  1. Parâmetro explícito na URL/sessão (usuário trocou manualmente agora).
  2. `users.idioma` (se autenticado) ou `clientes.idioma` (se identificado na página pública).
  3. Cookie de idioma salvo em visita anterior.
  4. Header `Accept-Language` do navegador.
  5. `barbearias.idioma_padrao` da barbearia do contexto atual.
  6. Fallback final: `es`.
- Componente Livewire reutilizável `LanguageSwitcher` (dropdown "🇦🇷 Español / 🇧🇷 Português"), que ao ser clicado dispara um evento que atualiza `session('locale')`, persiste em `users.idioma`/`clientes.idioma` quando aplicável, e recarrega os textos da página via `wire:navigate` (sem perder o estado do fluxo de agendamento em andamento).
- Formatos localizados por idioma: datas (`d/m/Y` es-AR vs. `dd/mm/aaaa` pt-BR são iguais na prática, mas nomes de mês/dia da semana mudam), e moeda sempre em **ARS** independente do idioma da interface (o idioma muda o texto, não a moeda — a barbearia opera na Argentina).
- Mensagens de erro de validação, e-mails transacionais e templates de WhatsApp também precisam de versão nos dois idiomas.

### 13.3 Impacto no roadmap

Recomenda-se já estruturar o projeto com `lang/es/` e `lang/pt_BR/` desde a **Fase 1** (Fundação), mesmo que a tradução completa para português só seja finalizada em fase posterior — assim evita-se retrabalho de "hardcoded strings" espalhadas pelo código.

---

## 14. Estado do scaffold

O que já existe neste repositório (gerado a partir deste documento):

- Projeto Laravel 13 + Livewire 4 (componentes classe clássica) + `spatie/laravel-permission` (teams via `barbearia_id`) + `mercadopago/dx-php`.
- Todas as migrations da seção 5, na ordem correta de dependência, incluindo a constraint `EXCLUDE` do Postgres para `agendamentos` (condicional ao driver).
- Models com relacionamentos e o trait `BelongsToBarbearia` (global scope + auto-preenchimento de `barbearia_id` na criação).
- `ResolveTenant` e `SetLocale` middlewares, registrados em `bootstrap/app.php`.
- `DisponibilidadeService` (cálculo de slots + guarda transacional `lockForUpdate` contra overbooking) — testado manualmente via tinker (escala semanal → slots → agendamento → segunda tentativa no mesmo horário rejeitada).
- Actions de agendamento e pagamento, `MercadoPagoWebhookController` com verificação de assinatura `x-signature` e job assíncrono idempotente.
- Seeder de roles/permissions (`super_admin`, `dono`, `atendente`, `barbeiro`, `cliente`).
- Esqueleto de `lang/es` e `lang/pt_BR`, `LanguageSwitcher`.
- Stubs vazios (sem lógica de UI) para os demais componentes Livewire listados na seção 8 — próximo passo natural é o CRUD de barbeiros/serviços/produtos e o wizard de agendamento público.
- **Auth funcional**: Laravel Fortify + registro próprio (`App\Actions\Auth\RegistrarDonoEBarbeariaAction`, que cria dono + barbearia numa transação — onboarding é self-service, ver `docs/adr/0007`). Login/logout via rotas nativas do Fortify, `/painel` protegido por `auth` + `tenant`. Coberto por testes em `tests/Feature/Auth/`.
- **CRUD admin real** para Barbeiro/Serviço/Produto/Cliente (`app/Livewire/Admin/{Barbeiros,Servicos,Produtos,Clientes}`), rotas `/painel/{barbeiros,servicos,produtos,clientes}` protegidas por `can:{recurso}.gerenciar` (as permissions do `RoleAndPermissionSeeder`), nav condicional no layout via `@can`, busca em Clientes. Coberto por testes em `tests/Feature/Admin/`, incluindo isolamento entre tenants.
- **Escalas de horário**: `App\Livewire\Admin\Barbeiros\EscalaBarbeiro` (`/painel/barbeiros/{barbeiro}/horarios`) — grade semanal (7 dias) com horário de trabalho + intervalo de almoço opcional por dia, persistindo em `barbeiro_horarios`. Isso fecha a Fase 1 do roadmap.

Com isso, a **Fase 1 (Fundação)** do roadmap está completa: auth, multi-tenancy, CRUD de Barbearia/Barbeiro/Serviço/Produto/Cliente, escalas de horário, e estrutura de i18n.

**Nota de teste**: `Livewire::test()` monta o componente direto, sem passar pela stack de middleware HTTP — `ResolveTenant` nunca roda, então `app('barbearia.id')` não fica bindado sozinho. Os testes de CRUD fazem esse bind manualmente no `setUp()` (mesma coisa que a middleware faria numa request real). Testes que batem numa rota de verdade via `$this->get()`/`$this->post()` não precisam disso — o middleware roda normalmente, inclusive para o route model binding tenant-scoped (`{barbeiro}` de outra barbearia dá 404 automaticamente via global scope).

**Bug real pego durante o desenvolvimento**: em `CrudCliente`, a busca por nome/telefone usava `->where(...)->orWhere(...)` solto no topo da query — isso escaparia do `WHERE barbearia_id = ?` do global scope (que usa `where`, não um grupo) e vazaria clientes de outras barbearias em qualquer busca por telefone. Corrigido agrupando a busca num `where(fn ($q) => ...)` aninhado. Vale a pena revisar esse padrão (`orWhere` top-level numa query já filtrada por scope) em qualquer CRUD futuro.

- **Wizard de agendamento público** (`App\Livewire\Public\AgendamentoWizard`, rota `GET /b/{slug}`, seção 6.1): fluxo de 5 etapas (serviço → barbeiro ou "sem preferência" → data/horário → dados do cliente → confirmação), sem pagamento antecipado ainda (Fase 3). "Sem preferência" calcula a união dos horários livres de todos os barbeiros que aceitam online e, ao confirmar, pega o primeiro efetivamente livre — a checagem final com lock fica a cargo do `CriarAgendamentoAction` já existente, então a lógica de prevenção de overbooking (ADR-0002) não precisou ser duplicada. Cliente é resolvido por `telefone` via `firstOrCreate` (sem criar duplicado se o número já existe na barbearia). Layout próprio `layouts::publico`, mais largo que o `layouts::guest` de login. Coberto por `tests/Feature/Public/AgendamentoWizardTest.php`.

**Dois bugs reais pegos pelos testes desta etapa**: (1) `servicosSelecionadosCollection()` era `private` mas a view Blade do componente chama `$this->servicosSelecionadosCollection()` — PHP trata o template compilado como escopo externo à classe, então isso quebraria em produção com erro de visibilidade; só não apareceu no `php -l` porque lint não executa a view. (2) O link "agendar outro" na tela de confirmação usava `route('public.agendamento', request()->route('barbearia'))` — funciona numa request normal, mas quebra no ciclo de vida AJAX do Livewire (e nos testes, que não passam pela rota) porque `request()->route()` não tem o parâmetro nesse contexto. Trocado por `app('barbearia')->slug`, que vem do bind de tenant e não depende da rota da request atual.

- **Fase 3 — Pagamentos (Mercado Pago)**:
  - **OAuth Connect** (`App\Http\Controllers\MercadoPagoConnectController`): `GET /painel/mercadopago/conectar` redireciona pro authorize da MP com um `state` aleatório salvo na sessão junto do id da barbearia (a URL de callback é fixa/global — não carrega slug — então é a sessão que resolve "para qual barbearia isso é"); `GET /mercadopago/callback` troca o `code` por token e salva em `barbearias.mp_access_token/mp_refresh_token/mp_user_id/mp_public_key`. Protegido por `can:barbearia.gerenciar` (só dono).
  - **Configuração** (`App\Livewire\Admin\Configuracoes\ConfigMercadoPago`, `/painel/mercadopago`): status da conexão, desconectar, e o toggle `barbearias.exige_pagamento_antecipado` (novo campo — decide se o wizard público exige pagamento antes de confirmar).
  - **Wizard público**: se `exige_pagamento_antecipado` E a barbearia está conectada, `AgendamentoWizard::confirmar()` cria o agendamento com `status = pendente` (bloqueando o horário do mesmo jeito que um confirmado) e redireciona pro checkout da MP via `CriarPreferenciaMercadoPagoAction`; senão, confirma direto como já fazia. Barbearia com a exigência ligada mas **não conectada** ao MP não trava o cliente — cai automaticamente no fluxo sem pagamento (ver `docs/adr/0008`).
  - **Webhook → confirmação**: `ProcessarWebhookMercadoPagoAction` foi ajustado pra reaproveitar o `Pagamento` "reservado" na hora do checkout (identificado por `agendamento_id` + `mp_payment_id` ainda nulo) em vez de sempre criar um novo — ver bug abaixo.
  - `MercadoPagoService::buscarPagamento()` foi extraído (antes o Action instanciava `PaymentClient` do SDK direto) especificamente pra permitir mockar a chamada à API da MP nos testes — sem credenciais reais de sandbox neste ambiente, é assim que o fluxo de webhook é testado (`tests/Feature/Pagamentos/`).

**Três bugs reais pegos nesta etapa**:
1. **`ProcessarWebhookMercadoPagoAction` duplicava o `Pagamento`**: como o pagamento é criado (com `mp_preference_id`, sem `mp_payment_id`) no momento do checkout, e o webhook antes só procurava por `mp_payment_id` (que ainda não existia), ele sempre caía no fallback e criava uma segunda linha em `pagamentos` — a "reservada" ficava órfã, sem nunca ser marcada como paga. Corrigido: o webhook agora procura primeiro por `mp_payment_id` (idempotência em reenvios) e, se não achar, pelo `Pagamento` pendente do mesmo `agendamento_id` sem `mp_payment_id` (o reservado no checkout).
2. **`Comissao` apontava pra tabela errada**: o model não declarava `$table`, e o Eloquent pluraliza "Comissao" em inglês → `comissaos`, enquanto a migration criou `comissoes` (português correto). Isso silenciosamente nunca deu erro porque nenhum teste anterior de fato criava uma `Comissao` — só apareceu ao testar o webhook completando um pagamento aprovado. Rodei uma checagem manual (`getTable()` de todos os models) pra garantir que não havia outro caso igual — não havia.
3. Nenhum bug novo no OAuth connect, mas vale registrar a decisão: o `state` do OAuth carrega o id da barbearia via sessão porque a URL de callback do Mercado Pago é fixa (cadastrada uma vez no app MP Developers) e não pode incluir o slug da barbearia na rota.

- **Calendário admin** (`App\Livewire\Admin\Agenda\CalendarioAgenda`, `/painel/agenda`, fecha a Fase 2): visão do dia em colunas por barbeiro ativo (via `with()`, sem N+1), navegação anterior/hoje/próximo/data manual, e transições de status controladas por uma tabela explícita de transições permitidas (`pendente/confirmado → em_atendimento/cancelado/no_show`, `em_atendimento → concluido/cancelado`) — uma tentativa de pular estado (ex.: `concluido → pendente`) é silenciosamente ignorada, não lança erro. Protegido por `can:agenda.gerenciar`. Coberto por `tests/Feature/Admin/CalendarioAgendaTest.php`, incluindo isolamento entre tenants e a rejeição de transição inválida.

- **Fase 4 — PDV/Tablet** (`App\Livewire\Pdv\TelaVendaDireta`, `/painel/pdv`, protegido por `can:pdv.operar`): kiosk de venda direta em 5 telas dentro de um único componente (barbeiro → serviços/produtos → dados+pagamento → sucesso, com uma tela extra de "aguardando pagamento" pro caminho Mercado Pago) — mesma escolha de "um componente, várias etapas" já usada no `AgendamentoWizard` (ver `docs/adr/0004`). Layout próprio `layouts::pdv`: tema escuro, alto contraste, botões grandes, e timeout de sessão automático via Alpine (`x-data` com `setTimeout`, qualquer clique reseta o timer; 90s parado chama `$wire.novaVenda()` e volta pra tela inicial — seção 9 do documento de arquitetura). Os stubs `Pdv/SelecionarBarbeiro.php` e `Pdv/Checkout.php` da seção 8 foram removidos por não serem usados — mesma lógica de simplificação da ADR-0004, registrada aqui em vez de um ADR novo por ser a mesma decisão já tomada.
  - **"Ocupado agora"**: a grade de barbeiros mostra quem está livre vs. em atendimento no instante atual (consulta por `data_hora_inicio <= agora < data_hora_fim` com status `confirmado`/`em_atendimento`), não impede escolher um barbeiro ocupado — só informa.
  - **Sem seleção de horário**: venda no PDV é sempre "agora" (`Carbon::now()`), diferente do wizard público. A duração ainda vem da soma dos serviços escolhidos, e o `CriarAgendamentoAction` (mesmo Action do wizard público) segue protegendo contra overbooking do mesmo jeito.
  - **Venda casada de produtos**: `CriarAgendamentoAction` ganhou um parâmetro opcional `produtosComQuantidade` (produto_id => quantidade) pra popular `agendamento_produto` — só o PDV usa isso por enquanto, mas fica disponível pra qualquer fluxo futuro que precise.
  - **Dois métodos de pagamento**: dinheiro (fecha a venda na hora, `Pagamento` criado com `pago_em = now()`, comissão registrada de imediato) ou Mercado Pago (reaproveita a mesma preferência Checkout Pro do wizard público — não a API dedicada de "QR dinâmico"/Point, que exigiria hardware ou um produto MP diferente e não dava pra testar aqui; ver decisão abaixo). O caminho MP cria o agendamento como `pendente` e mostra um link "Abrir pagamento" pro cliente pagar pelo próprio celular; a tela faz `wire:poll` a cada 3s até o status virar `concluido` via webhook.

**Correção real puxada por este trabalho**: `ProcessarWebhookMercadoPagoAction` fechava todo pagamento aprovado como `status = confirmado`, que faz sentido pro wizard público (agendamento é pro futuro) mas está errado pro PDV (o atendimento já aconteceu — devia fechar como `concluido`, mesmo status que o caminho "dinheiro" usa). Corrigido pra `$agendamento->origem_pdv ? 'concluido' : 'confirmado'`.

**Decisão explícita, não implementada**: Mercado Pago Point (maquininha física via Bluetooth) fica de fora — exige um SDK de hardware e um dispositivo físico pareado, nada disso é possível construir ou verificar neste ambiente. O caminho MP do PDV usa Checkout Pro (o cliente escaneia/abre o link com o próprio celular), que é a alternativa que o próprio documento de arquitetura já cita na seção 6.2 ("QR Code dinâmico"). Também não foi implementada geração de imagem de QR code real — o link é mostrado como texto/botão grande; renderizar um QR de verdade na tela (ex.: com uma lib JS) é polimento de UI que fica pra depois.

- **Fase 5 — Financeiro/Comissões**:
  - **`App\Livewire\Admin\Relatorios\RelatorioComissoes`** (`/painel/relatorios/comissoes`, `can:financeiro.visualizar` — só dono/super_admin no seeder): filtro por período (default: mês atual) e por barbeiro, totais (geral/pendente/pago), exportação CSV (`streamDownload`, testado de verdade — conteúdo das linhas, não só "o botão existe"), e **fechamento**: marcar uma comissão individual como paga, ou todas as pendentes do período filtrado de uma vez (`marcarTodasComoPagas`) — é a interpretação dada pra "fechamento" da seção 12, Fase 5 (o documento não detalha o mecanismo, então virou uma decisão de implementação, não uma ADR à parte).
  - **`App\Livewire\Barbeiro\MinhasComissoes`** (`/painel/minhas-comissoes`, `can:comissoes.visualizar_propria` — role `barbeiro`): mesma visão, mas somente leitura e restrita à própria `Barbeiro` (resolvida por `barbeiros.user_id = auth()->id()`). Se o usuário logado como barbeiro não tiver um registro `Barbeiro` vinculado (`user_id` nunca setado), mostra aviso em vez de quebrar.
  - **Exportação real em PDF/Excel não foi implementada** — só CSV, que Excel/Sheets abrem sem problema e não exige nenhuma dependência nova. Adicionar PDF (`barryvdh/laravel-dompdf`) ou XLSX nativo (`maatwebsite/excel`) fica pra quando houver um motivo concreto pra isso (ex.: pedido de formato específico pra contabilidade) — não uma escolha "porque o documento menciona os dois formatos".

Com isso, todo o roadmap da seção 12 (Fases 1 a 5) tem pelo menos uma implementação funcional e testada. O que resta é polimento e itens que dependem de coisas fora deste ambiente (credenciais MP de verdade, hardware Point, provedor de WhatsApp) — listados abaixo.

- **Agenda própria do barbeiro** (`App\Livewire\Barbeiro\MinhaAgenda`, `/painel/minha-agenda`, `can:agenda.visualizar_propria`): fecha a lacuna que já existia — a permission estava no seeder desde a Fase 1, sem UI. Somente leitura, de propósito (o papel do barbeiro na seção 3 do documento é "vê" a agenda, não "gerencia" — diferente do `CalendarioAgenda` de dono/atendente, que tem transições de status).

- **Notificações por e-mail** (parte da Fase 6 — WhatsApp continua fora, ver abaixo): `App\Notifications\AgendamentoConfirmado` (disparada no wizard público sem pagamento e no webhook MP aprovado) e `App\Notifications\AgendamentoLembrete` (via `App\Console\Commands\EnviarLembretesAgendamento`, agendado a cada 15min em `routes/console.php`, dispara ~2h antes do horário, numa janela de 20min, marcando `agendamentos.lembrete_enviado_em` pra nunca duplicar). Cliente só recebe se tiver `email` cadastrado — o wizard público não pede e-mail (só telefone), então isso só dispara pra clientes que já têm e-mail no cadastro (via CRUD admin). Locale da mensagem resolvido por `cliente.idioma` com fallback pra `barbearia.idioma_padrao`.

**Um bug real e sério pego nesta etapa, que motivou revisitar a ADR-0001**: o global scope do `BelongsToBarbearia` sempre foi *documentado* como fail-closed, mas a implementação original era fail-*open* — sem tenant bindado, a query rodava sem filtro nenhum, vazando registros de **todas** as barbearias, em vez de não devolver nada. Isso nunca deu problema até agora porque nada até esta etapa acessava modelos tenant-scoped fora do ciclo de uma request HTTP normal (onde `ResolveTenant` sempre bindа o tenant). As notificações por e-mail mudaram isso: `ProcessarWebhookMercadoPagoAction` e `EnviarLembretesAgendamento` acessam `Cliente` (via relação) fora desse ciclo. Corrigido — ver a seção "Correção" na própria `docs/adr/0001-multi-tenancy-single-database.md`, que documenta o bug, a correção (`whereRaw('1 = 0')` quando não há bind) e os três pontos de código que precisaram de tratamento explícito.

**Um segundo bug, mais sutil, encontrado só com um smoke test manual de verdade** (`queue:work` rodando de fato — nenhum teste automatizado pegava isso, porque `Notification::fake()` intercepta antes de qualquer serialização real, e o `QUEUE_CONNECTION=sync` dos testes nunca serializa nada): se o `Agendamento` passado pro construtor de uma notificação `ShouldQueue` já tinha alguma relação carregada (`cliente`, `barbeiro`, `servicos` — comum, já que quem dispara a notificação normalmente acabou de tocar nelas), o Laravel inclui essa relação no payload serializado do job e tenta recarregá-la sozinho ao desserializar (`loadMissing()`) — isso roda **antes** de `toMail()`, e portanto antes de qualquer bind de tenant feito lá dentro. Sem tenant nesse instante, o scope fail-closed devolve `null`, e a relação fica cacheada assim pra sempre nessa instância — o e-mail quebra ao renderizar. Corrigido chamando `$agendamento->unsetRelations()` no construtor de cada notificação (garante que `toMail()` sempre carregue tudo do zero, no momento em que o bind já foi feito) — ver `docs/adr/0001` e `tests/Feature/Notificacoes/NotificacaoSerializacaoTest.php`, que faz um round-trip de `serialize()`/`unserialize()` de verdade pra guardar contra regressão (verificado manualmente: comentar o fix faz esse teste falhar do jeito certo).

Não implementado ainda: split real via Order API (modelo (a) da seção 7.1 — hoje só o modelo (b), lógico), Mercado Pago Point físico (ver Fase 4 acima), notificações via WhatsApp (precisa provedor externo — Twilio/Wati — com credenciais que não existem neste ambiente; só o canal `mail` foi implementado), 2FA (scaffolding pronto, UI não construída), PIN rápido de atendente pra autorizar descontos/cancelamentos (seção 9, não construído), exportação em PDF/Excel (ver acima). **Nenhum teste bateu contra a API real do Mercado Pago** — não há credenciais de sandbox configuradas neste ambiente; toda a cobertura usa `MercadoPagoService` mockado. Antes de ir pra produção, validar o fluxo completo (connect → checkout → webhook) contra o sandbox de verdade da MP.

---

**Nota sobre trabalho concorrente**: durante esta sessão, uma quantidade significativa de arquivos de view (`resources/views/components/layouts/*`, praticamente todos os `resources/views/livewire/**/*.blade.php`) foi reescrita por fora desta conversa — um redesign visual completo (paleta slate/brand, sidebar de navegação, componentes `x-ui.*` como `<x-ui.button>`, `<x-ui.card>`, `<x-ui.input>`, `<x-ui.badge>`, `<x-ui.status-agendamento>`, `<x-ui.avatar>`, `<x-ui.empty-state>`, etc.). Essas mudanças não foram revertidas nem tocadas por este trabalho — o componente `x-ui` em si (provavelmente novos arquivos em `resources/views/components/ui/`) não foi auditado aqui. Vale conferir se esses componentes existem de fato e se o app ainda builda/renderiza antes de considerar a UI pronta.
