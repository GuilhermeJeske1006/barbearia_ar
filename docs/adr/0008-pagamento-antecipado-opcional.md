# 0008 — Exigência de pagamento antecipado é opt-in e nunca bloqueia o cliente

**Status:** Aceito

## Contexto

O documento de arquitetura (seção 6.1, passo 6) já previa que "se a barbearia exige pagamento antecipado (configurável)" o wizard público cobra antes de confirmar. Faltava decidir dois pontos práticos: (1) onde mora esse flag, e (2) o que acontece quando a barbearia **ligou** a exigência mas **não terminou** de conectar a conta Mercado Pago (OAuth Connect, ver seção 7.1/docs/adr/0003) — situação inevitável entre "criei minha conta na plataforma" e "conectei o Mercado Pago".

## Decisão

- Campo `barbearias.exige_pagamento_antecipado` (boolean, default `false`), configurável em `/painel/mercadopago`.
- `AgendamentoWizard::confirmar()` só entra no fluxo de pagamento quando **as duas condições** são verdadeiras: `exige_pagamento_antecipado === true` **e** `barbearia->conectadaAoMercadoPago()` (tem `mp_access_token`). Faltando qualquer uma, o agendamento é confirmado direto, sem cobrança — exatamente como se a exigência estivesse desligada.

## Consequências

- Uma barbearia pode ligar "exigir pagamento" a qualquer momento sem se preocupar em travar a própria agenda pública caso a conexão MP caia, expire, ou nunca tenha sido finalizada — o pior cenário é o cliente reservar sem pagar antecipado (equivalente ao comportamento padrão), nunca um erro ou uma tela quebrada.
- Do lado do dono, isso significa que "exigir pagamento" ligado não é uma garantia forte de que todo agendamento terá cobrança — é preciso também manter a conexão MP ativa. Vale mostrar isso claramente na UI (`ConfigMercadoPago` já avisa quando não conectada) e, no futuro, considerar um aviso mais explícito quando as duas coisas estão dessincronizadas (exigência ligada + MP desconectada).
- Reflexo direto: o teste `test_barbearia_nao_conectada_ao_mp_ignora_exigencia_e_confirma_direto` em `AgendamentoComPagamentoTest` fixa esse comportamento — se algum dia ele for considerado errado (ex.: preferir bloquear o agendamento em vez de deixar passar sem cobrança), é ali que a mudança de política precisa começar.
