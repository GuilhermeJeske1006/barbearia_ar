# Validação de notificações, agenda e venda direta — 21/10/2026

## Resultado

O fluxo foi validado em ambiente isolado com as skills **Playwright** e **UI/UX Design Review**. A revisão encontrou falhas funcionais e de apresentação, que foram corrigidas e retestadas.

- Suíte completa: **479 testes aprovados, 1 ignorado, 1.376 assertions**.
- Navegador: **10 cenários no Chromium e os mesmos 10 no WebKit**, com telas de computador e simulação móvel.
- Responsividade e usabilidade: **7 verificações** de largura, teclado, idioma e tema.
- Links de e-mail: **4 verificações**, dono e barbeiro nos dois motores.
- Build de produção, Pint e `git diff --check`: concluídos.
- Nenhuma alteração, reserva de teste ou mensagem foi enviada a produção.

O teste ignorado já existia: `RegisterTest::test_finalizar_cadastro_falha_se_assinatura_nao_estiver_ativa`, relativo à etapa de assinatura desativada no cadastro. Não pertence ao fluxo de reservas.

## Fluxo conferido

| Etapa | Resultado observado |
|---|---|
| Cliente escolhe serviço, barbeiro, data e horário | Seleção no celular funciona e mantém o profissional escolhido. |
| Cliente informa seus dados e revisa | A revisão mostra os dados antes de confirmar. |
| Cliente confirma com pagamento no local | Reserva fica `confirmado`; não foi introduzida aprovação manual. |
| Dono está com o painel aberto | O sino recebe a reserva sem precisar navegar dia por dia. |
| Dono marca o aviso como lido | A reserva continua em **Reservas ativas**, com cliente, data, horário, barbeiro e status. |
| Dono abre o lembrete | A agenda abre na data da reserva. |
| Barbeiro acessa o sistema | Recebe sua reserva e abre a própria agenda na data correta; a agenda administrativa retorna 403 para esse perfil. |
| Venda direta | Consulta → iniciar venda com barbeiro → serviço → cliente → pagamento → **Venda concluída**. |
| Comissão | Venda de teste de 50 gera pagamento único de 50 e comissão pendente de 25 para o profissional responsável. |
| Cancelamento | Confirmação explícita, saída do destaque e permanência no **Histórico de avisos**. |

Os lembretes também incluem reservas online ativas anteriores à implantação, mesmo sem notificação persistida. Conclusão, cancelamento e falta retiram a reserva do destaque. Alterações na data e no horário são refletidas no lembrete; ler o aviso não muda o status da reserva.

## Problemas encontrados e corrigidos

| Prioridade | Problema | Correção e evidência |
|---|---|---|
| Alta | Sino provocava erro 500 quando não havia filial selecionada. | Consulta retorna vazia sem contexto de filial; teste de regressão e suíte completa aprovados. |
| Alta | No celular, seletores de data ocultos para leitores de tela escapavam da área rolável, ampliando a página e prejudicando o clique em **Continuar**. | Labels posicionadas relativamente e barra de continuação com camada explícita; reserva completada por clique nos dois motores. |
| Alta | Notificações ficavam parcialmente fora da tela móvel. | Painel contido nas margens da tela no celular; limites geométricos conferidos em 320, 375, 390, 768 e 1366 px. |
| Média | Contador de reservas ativas era tratado como contador de não lidas. | Contagens separadas. **Marcar todas como lidas** desaparece quando não há avisos não lidos; o lembrete ativo permanece. |
| Média | Detalhes do lembrete poderiam ficar desatualizados após alterar a reserva. | Destaques usam os dados atuais do agendamento; teste confere nova data, hora e link. |
| Média | Confirmação de cancelamento falava em excluir registro. | Texto específico informa cancelamento e preservação do histórico; falta também tem texto próprio. |
| Média | Após mudar o status na agenda, o sino precisava esperar a próxima consulta periódica. | A agenda dispara atualização do sino ao transicionar ou concluir o pagamento. |
| Média | Teclado, foco e contraste das notificações precisavam de ajustes. | Escape fecha e devolve foco ao sino, área de toque de 44 px, descrição acessível das contagens e contraste reforçado nos estados claro/escuro. |

## E-mail, transações e isolamento

Testes de integração em `tests/Feature/Notificacoes/NovaReservaFluxoTest.php` verificam:

- Destinatários corretos: dono e profissional responsável; outro tenant não recebe o aviso.
- E-mail renderizado com cliente, dia, horário e link para a agenda adequada ao perfil.
- Enfileiramento somente depois do commit da reserva.
- Rollback sem reserva, aviso ou e-mail remanescente.
- Falha simulada de e-mail preservando a reserva confirmada e os avisos no banco.
- Transporte e conteúdo conferidos sem envio externo.

A fila local foi processada com `queue:work --stop-when-empty --tries=1`: os dois e-mails da nova reserva e a confirmação ao cliente terminaram sem falhas. Os links privados foram testados em contextos sem login: exigem autenticação e preservam a data após login do dono ou barbeiro.

Os testes existentes também cobrem conflito de horário, pagamento online/transferência, cancelamento, permissões, filiais e isolamento da agenda/comissões do barbeiro.

## Revisão de entendimento e acessibilidade

A separação entre **Reservas ativas** e **Histórico de avisos**, o status visível e a explicação de que ler não remove a reserva tornam a ação esperada explícita. A venda direta informa que os horários são consulta e oferece **Iniciar venda agora**, sem sugerir que selecionar um horário futuro inicia um atendimento presencial.

Foram conferidos limites da tela, ausência de rolagem horizontal da página nas larguras testadas, Enter/Escape, retorno de foco, área de toque do sino, indicação de abertura, descrição acessível das contagens, traduções PT/ES e tema escuro. Esta foi uma revisão dirigida a esses componentes, sem certificação integral de acessibilidade ou pesquisa com usuários externos.

## Evidências

- [Cenários Chromium](evidencias-notificacoes-2026-10-21/chromium.json)
- [Cenários WebKit](evidencias-notificacoes-2026-10-21/webkit.json)
- [Links dos e-mails](evidencias-notificacoes-2026-10-21/links-email.json)
- [Responsividade, teclado e idiomas](evidencias-notificacoes-2026-10-21/responsividade.json)
- [Notificações em 320 px](evidencias-notificacoes-2026-10-21/notificacoes-320.png)
- [Tema escuro no celular](evidencias-notificacoes-2026-10-21/notificacoes-escuro.png)
- [Pagamento no computador](evidencias-notificacoes-2026-10-21/pagamento-desktop.png)

Scripts de navegador e fixtures foram escritos em `/tmp`, conforme a skill Playwright. Traces do contexto do dono: `/tmp/barberya-flow-trace.zip` e `/tmp/barberya-webkit-flow-trace.zip`.

## Limites da validação

O servidor usado foi `http://127.0.0.1:8094`, com SQLite temporário em `/tmp/barberya-flow.sqlite`, contas fictícias e transporte de e-mail local. A suíte PHP usa SQLite em memória.

WebKit aproxima o motor do Safari; não substitui um teste em iPhone físico. O envio real por SMTP, a entrega na caixa de entrada e as credenciais/fila da hospedagem de produção ainda precisam ser validados após a publicação. O E2E finaliza pagamento manual em dinheiro; gateways reais não foram cobrados. Não foi feito deploy nesta tarefa.

Comandos de verificação: `php artisan test --compact`, `vendor/bin/pint --dirty`, `npm run build` e `git diff --check`. Navegador executado pelo `run.js` da skill Playwright.
