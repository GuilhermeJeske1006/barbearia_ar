Revisão do Mercado Pago — 16/09/2026

Validação exclusivamente local, conforme solicitado. Nenhuma cobrança, conexão OAuth real, devolução ou mensagem externa foi executada. As chamadas HTTP do SDK e do OAuth foram simuladas nos testes.

**Resultado**

Os 77 testes existentes do recorte inicial passaram, mas não cobriam falhas encontradas na revisão. Foram reproduzidos, antes da correção, retorno HTTP 403 com os parâmetros do gateway, aprovação com valor divergente, regressão de atendimento concluído após reenvio e ausência de estorno da comissão.

Após as correções: suíte completa com **434 testes, 433 aprovados, 1 ignorado e 1.158 asserções**. O ignorado é preexistente, referente ao onboarding pago com Stripe desativado no produto. Build, Pint e `git diff --check` aprovados.

**Fluxo verificado**

| Etapa | Comportamento verificado/corrigido |
| --- | --- |
| Conectar conta | State e permissão conferidos; falha HTTP do OAuth informa erro e preserva a conexão anterior. Resposta sem token ou usuário não substitui credenciais. |
| Renovar credenciais | Renovação antes do vencimento; cálculo da margem de sete dias não modifica o objeto da data original. Chamadas OAuth possuem timeout. |
| Criar checkout | Token por requisição, valores enviados pelo backend, recebedor da barbearia e validade de 30 minutos. Falha na API não apaga o registro anterior antes de obter nova preferência. |
| Receber webhook | Segredo obrigatório; assinatura validada pelo SDK instalado. O ID da query, inclusive `data_id` normalizado pelo PHP, precisa corresponder ao ID processado. Corpo divergente ou malformado é rejeitado. Resposta válida usa HTTP 200. |
| Consultar pagamento | Novas preferências identificam a barbearia na URL de notificação e usam seu token OAuth. O token da plataforma permanece como compatibilidade para preferências antigas. O identificador da barbearia não é tratado como prova de autenticidade: origem, recebedor e vínculo com a reserva são conferidos. |
| Aprovar | Exige checkout conhecido, ID de pagamento correspondente, recebedor, moeda e valor esperado. Query de retorno nunca confirma pagamento. |
| Reenviar | Não duplica pagamento/comissão, não muda a primeira data de recebimento e não regride atendimento em andamento/concluído. Evento pendente atrasado não apaga aprovação. |
| Recusar | Libera a reserva pendente; recusa de uma tentativa antiga não cancela a nova tentativa. |
| Tentar novamente | Confere disponibilidade dentro de transação, usa bloqueio por barbeiro, limita tentativas e preserva o valor original, incluindo produtos do PDV. Falha no gateway desfaz a reabertura da reserva. |
| Expirar | Checkout novo de uma reserva antiga recebe seu próprio prazo; o comando não sobrescreve uma aprovação que aconteceu entre a leitura e a atualização. |
| Pagamento tardio | Registra o pagamento, mas não reativa reserva cancelada. Exibe orientação para combinar novo horário/devolução e registra necessidade de conciliação. |
| Estorno total/chargeback | Estorna comissão, remove o pagamento da apuração por `pago_em` e cancela reserva futura associada. Preserva histórico de atendimento já concluído e consumo já realizado. Não solicita devoluções automaticamente à API. |
| Retornar ao site | Aceita os parâmetros documentados adicionados pelo gateway, preservando a assinatura da rota. Alterar o ID da reserva ou acrescentar parâmetros não permitidos continua bloqueado. |

O teste integrado `MercadoPagoServiceTest::test_fluxo_checkout_webhook_assinado_retorno_e_reenvio` usa o SDK real com transporte HTTP simulado: cria preferência, recebe webhook assinado pela rota HTTP, executa o job, consulta pagamento, registra comissão, reenvia o evento e abre o retorno com parâmetros do gateway. Confere também o token usado nas requisições.

**Configuração e limites**

- O ambiente local contém valores para as quatro configurações MP consultadas e está com `MP_SANDBOX=false`; a fila está configurada como `database`. A presença desses valores não comprova validade das credenciais. Nenhum segredo foi exibido ou alterado.
- Sem `MP_WEBHOOK_SECRET`, o endpoint agora retorna 503 e não processa eventos. Configuração incorreta precisa ser corrigida antes de receber pagamentos.
- A consulta com token da plataforma é mantida somente para preferências antigas sem identificador da barbearia. Homologar o acesso a pagamentos legados antes de publicar.
- O worker precisa executar os jobs de pagamento. Foram configuradas cinco tentativas com intervalos progressivos; não foi iniciado worker sobre a fila existente.
- Concorrência real entre processos deve ser validada no banco usado em produção; SQLite em memória não comprova comportamento dos bloqueios no ambiente final.
- Estornos parciais, contestações ainda em análise, conciliação administrativa e envio confiável de notificações após falha de fila exigem cobertura própria. O tratamento de estorno implementado aqui cobre estados finais `refunded` e `charged_back`, não um rateio automático de devolução parcial.
- Checkout hospedado, login real das contas de teste e entrega externa do webhook ficam para homologação posterior, por decisão do usuário. Nenhuma configuração foi publicada.

**Referências consultadas**

O Mercado Pago acrescenta dados de transação às URLs de retorno; a correção mantém esses dados fora da decisão de aprovação. [URLs de retorno](https://www.mercadopago.com.br/developers/en/docs/checkout-pro-preferences/configure-back-urls).

A documentação orienta validar assinatura e confirmar recebimento com HTTP 200/201. Foram conferidos também o código do validador e as opções por requisição do SDK PHP instalado. [Webhooks](https://www.mercadopago.com.br/developers/en/docs/checkout-pro-preferences/additional-content/notifications/webhooks?scope=prod).

A validade da preferência é enviada pelos campos `expires`, `expiration_date_from` e `expiration_date_to`. [Vigência da preferência](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/additional-settings/term-of-preference).
