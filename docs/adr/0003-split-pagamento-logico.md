# 0003 — Split de comissão: modelo lógico interno (não split real via API)

**Status:** Aceito — reavaliar se barbeiros passarem a exigir recebimento direto na própria conta MP

## Contexto

Cada pagamento processado precisa ser dividido entre a barbearia e o barbeiro (comissão) e, adicionalmente, entre a barbearia e a plataforma (taxa de uso do SaaS). O Mercado Pago oferece duas formas de fazer isso:

- **(a) Split real de 3 pontas** via `Order` API: múltiplos recebedores num único pagamento, cada um recebendo diretamente na própria conta MP. Exige que cada barbeiro conecte sua própria conta MP via OAuth (KYC individual), e mais complexidade de integração/tratamento de erro parcial (um recebedor falha, outro não).
- **(b) Split lógico interno**: o pagamento inteiro cai na conta MP da barbearia (com `marketplace_fee` retendo a taxa da plataforma). O sistema apenas calcula e registra, na tabela `comissoes`, quanto é devido a cada barbeiro. O repasse físico barbearia → barbeiro acontece por fora do sistema (transferência bancária, dinheiro).

## Decisão

MVP implementa exclusivamente (b). `ProcessarWebhookMercadoPagoAction` grava `pagamentos.valor_comissao_barbeiro` / `valor_barbearia` calculados a partir do snapshot de `percentual_comissao_aplicado` em `agendamento_servico`, e `ComissaoService::registrar()` cria o registro em `comissoes` com `status = pendente`. Nenhuma movimentação de dinheiro barbearia→barbeiro passa pelo sistema.

Campos que já preparam o terreno para (a) sem migração de schema: `barbeiros.mp_user_id` / `mp_access_token` / `mp_refresh_token` (conexão MP individual do barbeiro, hoje não usada), `pagamentos.forma_split` (`marketplace_auto` vs `manual`, hoje sempre `manual`), `pagamentos.mp_split_status`.

## Consequências

- KYC/onboarding simplificado: só a barbearia conecta uma conta MP, não cada barbeiro individualmente — reduz atrito de adoção.
- A plataforma não tem visibilidade nem controle sobre se o repasse barbearia→barbeiro de fato aconteceu — é um processo de confiança fora do sistema, mitigado pelo relatório de comissões (seção 5, Fase 5 do roadmap) que a barbearia usa para fazer o fechamento.
- Migrar para (a) no futuro exige: implementar fluxo de OAuth Connect por barbeiro (endpoint hoje inexistente), trocar a chamada de criação de preferência para a `Order API` com múltiplos `collector`, e passar a preencher `forma_split = marketplace_auto`. Os campos de schema já existem; o trabalho é de integração, não de migration.
