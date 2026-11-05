# Fluxos integrados

## Criação da barbearia (onboarding)

1. **Cadastro em 4 etapas** (`/register`): sua conta → sua barbearia (nome, endereço público com prévia do link) → localização e idioma → revisão. Cada avanço valida só a etapa atual; a revisão permite editar qualquer etapa anterior; o envio final revalida tudo e volta para a etapa do primeiro campo com erro. Não é possível pular etapas adiante (`etapa` é `#[Locked]`). A URL pública é gerada do nome no navegador e acompanha o nome até ser editada à mão (`updatingNomeBarbearia`: no Livewire 4 os hooks `updated*` só rodam depois de aplicar todos os campos da requisição).
2. **Primeiros passos** (`admin.primeiros-passos`, permissão `barbearia.gerenciar`): conta nova cai aqui. Sete etapas, calculadas a partir dos dados reais da filial atual (`PrimeirosPassosService`), sem flag gravada:
   | # | Etapa | Concluída quando | Tipo |
   | --- | --- | --- | --- |
   | 1 | Dados da barbearia | telefone e endereço preenchidos | essencial |
   | 2 | Equipe | existe profissional ativo (atalho “Eu também atendo” para o dono) | essencial |
   | 3 | Horários | algum profissional ativo tem escala | essencial |
   | 4 | Serviços | algum serviço ativo tem profissional ativo | essencial |
   | 5 | Pagamentos online | Mercado Pago conectado ou transferência ativa | opcional |
   | 6 | WhatsApp | sessão conectada | opcional |
   | 7 | Divulgar link | primeiro agendamento registrado | final |
3. **Pronta para reservas** segue o mesmo critério do `AgendamentoWizard`: um profissional ativo, com reserva online, escala e serviço ativo. O guia termina quando as essenciais estão feitas **e** a página está pronta — não espera o primeiro agendamento.
4. Enquanto não termina: o próprio guia (componente `PrimeirosPassos` com `embutido=true`) aparece no topo do painel com todas as etapas e ações — concluídas ficam compactas, pendentes mostram o que fazer; item com progresso no topo do menu e faixa contextual (`GuiaPrimeirosPassos`) nas telas de cada etapa, que se atualiza ao salvar (evento `primeiros-passos-atualizado`) e aponta a etapa seguinte. Depois, o guia fica em Configurações.
5. Página pública sem agenda aberta mostra aviso em vez de “Começar”; filiais sem profissional online não aparecem.

## Comportamento

- **Clientes → Agenda/PDV:** ações na lista e após salvar carregam o cliente existente no próximo formulário. A busca de clientes permanece na URL.
- **Agenda:** novo agendamento pelo painel; clicar em horário livre seleciona profissional e sugere horário, sujeito à disponibilidade dos serviços. Salvar retorna ao dia escolhido. Os próximos atendimentos do painel abrem o respectivo card.
- **Serviços → Equipe:** criação e edição permitem vincular profissionais. Os vínculos existentes mantêm comissão e duração personalizadas. A lista identifica serviços sem profissional ativo. Cadastros auxiliares abrem em outra aba; “Atualizar opções” recupera as opções preservando os campos.
- **Produtos ↔ Estoque:** editar pelo detalhe do estoque retorna ao mesmo produto após salvar ou cancelar. Busca por nome ou código de barras disponível no cadastro.
- **Equipe:** horários e bloqueios conectam-se à equipe e à agenda; bloqueios mostram links das reservas afetadas. Um bloqueio mantém as reservas existentes.
- **Navegação:** caminho da tela, títulos, indicadores do painel com links e acesso ao estoque no menu. PDV oferece retorno ao painel, agenda e clientes conforme as permissões.
- **Troca de filial:** retorna ao painel e informa a unidade selecionada, descartando URLs de registros da unidade anterior.
- **Profissionais:** salvar mostra aviso com o próximo passo; a lista sinaliza “Sem horário”, “Sem serviço” e “Não aceita reserva online”, com atalho para corrigir. Editar um serviço preserva vínculos de profissionais removidos temporariamente (ex.: dono que desligou o próprio atendimento).
- **Mercado Pago:** a conexão abre na mesma aba e o retorno volta para Pagamentos, onde sucesso e erro são exibidos.
- **Menu e caminho:** Filiais aparece só com `filiais.gerenciar`; “Ver minha página” abre a página pública; o caminho da tela omite o nível superior quando o usuário não tem acesso a ele.
- **Formulários:** campos compartilhados inferem identificação e validação do `wire:model`; mensagens ficam associadas aos campos. O PDV renova a contagem de inatividade com cliques e digitação e limpa temporizadores ao sair.

## Contratos de navegação

| Destino | Parâmetros |
| --- | --- |
| `admin.agenda` | `novo=1`, `cliente=ID`, `data=AAAA-MM-DD`, `#agendamento-ID` |
| `admin.clientes` | `busca=nome-ou-telefone` |
| `pdv` | `cliente=ID` ou `agendamento=ID` (do dia e em status compatível) |
| `admin.produtos` | `editar=ID&origem=estoque`, `novo=1`, `busca=nome-ou-codigo` |
| `admin.produtos.estoque` | `detalhe=ID` |
| `admin.barbeiros` | `novo=1`, `editar=ID` |
| `admin.servicos` | `novo=1`, `editar=ID` |
| `admin.primeiros-passos` | `#etapa-CHAVE` (`dados`, `equipe`, `horarios`, `servicos`, `pagamentos`, `whatsapp`, `divulgar`) |

Os registros são consultados no escopo da barbearia e filial atuais. As ações e atalhos respeitam as permissões já utilizadas pelo sistema.

## Validação em 04/11/2026

`php artisan test --compact`: **567 passaram, 1 pulado; 1.781 assertions** (inclui `RegisterEtapasTest` e `PrimeirosPassosTest`).

`vendor/bin/pint --dirty`, `git diff --check`, `php artisan view:cache` e `npm run build`: concluídos.

Playwright (Chromium visível, SQLite separado no scratchpad da sessão) verificou: validação por etapa no cadastro, prévia do link, edição a partir da revisão, redirecionamento ao guia, página pública fechada → aberta, cadastro de profissional pelo deep-link com atualização da faixa sem recarregar, serviço vinculado, guia “pronta”, “Eu também atendo”, card e progresso no menu, ausência de overflow em 390 px e nenhum erro JavaScript. Integrações externas (Stripe, Mercado Pago, WuzAPI) não foram chamadas.

Verificação Playwright anterior dos fluxos integrados (mesma data):

1. Login e atalho de novo agendamento no painel.
2. Cliente existente → agendamento salvo no dia escolhido.
3. Cliente existente → PDV com dados preenchidos.
4. Reinício do PDV pelo evento de inatividade.
5. Estoque → edição de produto → retorno ao detalhe do estoque.
6. Cadastro de serviço vinculado ao profissional.
7. Cadastro de cliente em viewport de 390 × 844, sem overflow da página.
8. Ausência de erros JavaScript nesses caminhos.

Os dados criados no navegador pertencem exclusivamente às bases temporárias de validação.
