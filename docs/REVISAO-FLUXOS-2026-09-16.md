Revisão de fluxos e segurança — 16/09/2026

Revisão posterior específica: [Mercado Pago](REVISAO-MERCADO-PAGO-2026-09-16.md), incluindo bloqueio por barbeiro na criação/reabertura de reservas e nova cobertura de pagamentos. Os números abaixo registram o resultado da primeira revisão.

A revisão encontrou e corrigiu falhas de autorização, proteção de dados e validação. Os resultados abaixo se referem ao código local e a um banco SQLite temporário; não representam homologação de produção nem garantia de ausência de outras vulnerabilidades.

**Correções implementadas**

| Área | Problema encontrado | Correção |
| --- | --- | --- |
| Livewire | Usuário desativado, superadministrador rebaixado ou assinatura vencida podiam continuar usando uma página já aberta sem reaplicar os middlewares específicos. | Middlewares persistentes e testes HTTP com snapshots reais. |
| Reservas públicas | `clienteIds` podia ser alterado pelo navegador; a geração de link de cancelamento não conferia o proprietário. | IDs bloqueados e conferência do cliente e da barbearia antes de assinar o link. |
| Consulta de pagamento por QR | O ID consultado podia ser substituído e promover uma reserva alheia a reserva confirmada no componente. | Propriedade bloqueada contra alteração pelo cliente. |
| Cadastro | Identificadores Stripe eram propriedades públicas alteráveis, embora o fluxo pago esteja desativado. | Identificadores bloqueados contra alteração pelo cliente. |
| Assinatura | Cancelamento acessível a usuários autenticados sem permissão de gestão. | Autorização no servidor e botão condicionado à permissão. |
| Comissões | Permissão de leitura permitia marcar comissões como pagas. | Quitação individual e em lote exigem `financeiro.gerenciar`. |
| OAuth Mercado Pago | Callback não revalidava usuário ativo e permissão de gestão. | Contexto do tenant, usuário ativo e autorização conferidos novamente. |
| Filiais | Middleware aceitava referência a filial de outra barbearia e mantinha contexto anterior. | Limpeza de contexto e consulta limitada ao tenant, preservando exclusão lógica. Troca de filial exige filial ativa. |
| Suspensão | Status administrativo `suspensa` não bloqueava o acesso normal. | Bloqueio do painel e rotas públicas; tela de assinatura permanece acessível para regularização. |
| Login | Rota local `/_debug-login/{id}` autenticava qualquer usuário sem senha. | Rota removida. |
| Agendamento | Confirmação direta não revalidava campos de etapas anteriores, serviço ativo e compatibilidade do barbeiro. | Validação de data, horário, serviços, barbeiro ativo/online, vínculo com os serviços e recusa de horários passados. |
| CSV | Nomes e descrições iniciados por operadores de fórmula eram exportados sem neutralização. | Campos de texto neutralizados antes da serialização CSV. |
| PDF | Download sem `Content-Type: application/pdf`. | Cabeçalho corrigido, com teste do download Livewire e assinatura binária `%PDF-`. |
| Testes | Dois testes de CSV dependiam implicitamente do idioma global. | Idioma português definido explicitamente. Fixtures de permissão/serviço corrigidas para representar os vínculos reais. |

**Evidências**

- Base inicial: 384 testes, 380 aprovados, 2 falhas, 1 erro e 1 ignorado.
- Resultado final: 410 testes, 409 aprovados, 1 ignorado; 1.076 asserções.
- O teste ignorado é preexistente e corresponde ao onboarding pago com Stripe, temporariamente desativado no produto.
- `npm run build`: aprovado. Aviso opcional sobre `fontaine` e fallback de fontes.
- `composer audit --format=json` e `npm audit --json`: nenhuma vulnerabilidade conhecida reportada.
- `vendor/bin/pint --dirty --test` e `git diff --check`: aprovados.
- Chromium visível: landing, login, cadastro, página pública, painel, agenda, PDV, clientes, serviços, produtos, usuários, configurações e relatórios abriram com HTTP 200, sem erros de JavaScript na navegação testada.
- Cadastro completo de barbearia com login automático e criação/edição de cliente pelo navegador: aprovados, sem erros de JavaScript.
- Fluxo móvel completo: escolher serviço/barbeiro/horário, informar dados, confirmar reserva, baixar calendário ICS e cancelar pelo link assinado. Sem erros de JavaScript. Agenda sem overflow horizontal em 390 × 844.
- Banco dos testes de navegador: `/tmp/barberya-review.sqlite`, separado do banco existente; notificações enfileiradas, sem iniciar worker.

**Melhorias recomendadas e limites da revisão**

1. Prioridade alta: testar concorrência no mesmo banco utilizado em produção. A disponibilidade usa bloqueio sobre agendamentos existentes; isso exige revisão da reserva de um horário ainda vazio e da reabertura após pagamento recusado. Recomenda-se serializar por barbeiro dentro da transação e testar duas confirmações simultâneas, incluindo retentativa de pagamento. Não foi validada concorrência real nesta revisão com SQLite.
2. Prioridade alta: homologar Mercado Pago, Stripe e WhatsApp em sandbox, incluindo reentrega de webhook, expiração de credenciais, estorno, timeout e execução das filas. Os testes atuais usam simulações; não houve cobrança nem mensagem externa nesta revisão.
3. Normalizar telefone na gravação e indexar a busca. Hoje “Minhas reservas” percorre os clientes da barbearia e normaliza em memória. Também ampliar a cobertura para o mesmo telefone em várias filiais: a listagem ainda usa o contexto da primeira filial encontrada.
4. Oferecer recuperação de senha na interface. O backend Fortify habilita recuperação, mas a tela de login não expõe esse fluxo. Adicionar verificação de e-mail e autenticação em dois fatores para funções administrativas.
5. Adicionar limites de tentativas ao cadastro e à retomada de pagamentos. Validar também a configuração de proxies: `trustProxies('*')` depende de a infraestrutura impedir acesso direto por origens não confiáveis.
6. Criar CI com testes, build e auditoria de dependências; preservar os cenários HTTP de autorização para evitar regressões que testes isolados de componente não detectam.
7. Melhorar estados vazios do onboarding com uma sequência visível: cadastrar serviço, barbeiro e horários, depois compartilhar o link público. Exibir mensagens específicas quando o serviço é desativado ou o horário deixa de estar disponível.

As propriedades de segurança foram revisadas conforme a [documentação de propriedades bloqueadas do Livewire 4](https://livewire.laravel.com/docs/4.x/attribute-locked). O código instalado de middleware persistente também foi consultado para validar o comportamento das requisições subsequentes.
