# Estoque por código de barras e câmera

## Uso

1. Em Produtos, crie ou edite o produto. Digite o código, use um leitor USB/Bluetooth ou toque em **Câmera**: o código lido preenche o campo **Código de barras** do próprio cadastro. O Enter do leitor não salva o formulário.
2. Para produtos sem código, busque pelo nome na lista do estoque e toque no nome para abrir os detalhes. Em Controle de estoque, escaneie e pressione **Consultar código**. A consulta mostra o saldo da filial ativa, sem movimentá-lo.
3. Abra os detalhes do produto ou use **+ Repor** no cartão. Use **Receber mercadoria**, informe a quantidade de embalagens recebidas e confirme.
4. Nos detalhes, use **Registrar saída** para retirar embalagens inteiras. A justificativa é obrigatória.
5. Use **Conferir quantidade** para informar a quantidade física de embalagens fechadas, inclusive zero. Se houver movimentação durante a contagem, feche e reinicie a conferência. Uma conferência sem diferença também fica no histórico (quantidade 0, com o motivo).
6. No PDV, inicie uma venda, escaneie e pressione **Adicionar à venda** (ou o Enter do leitor). Cada leitura adiciona uma unidade, limpa o campo e devolve o foco para a próxima leitura. Num agendamento vinculado ainda não pago, as unidades já reservadas por ele contam como disponíveis. O carrinho segue o fluxo existente de venda e estoque.

A leitura pausa ao reconhecer um código e não altera o estoque sozinha. O botão de confirmação evita duplicação por quadros consecutivos da câmera. Um produto não cadastrado não é criado automaticamente. Cadastre-o em Produtos.

Códigos são texto, preservando zeros à esquerda, e são únicos por barbearia e filial. Um mesmo código pode existir em filiais diferentes, com saldos independentes. Não existe consulta externa automática de nome ou preço. Cada apresentação comercial deve ser cadastrada separadamente. Códigos de caixas não fazem conversão automática em unidades.

## Pomadas e loções abertas

No cadastro, escolha a unidade de consumo interno (ml ou g) e o conteúdo de uma embalagem. O saldo de estoque continua representando embalagens fechadas.

Na consulta do estoque, **Abrir uma embalagem para uso interno** solicita confirmação, retira uma unidade fechada e credita seu conteúdo no saldo aberto. As duas movimentações são atômicas e ficam no histórico, com unidades distintas.

Exemplo: duas loções fechadas de 500 ml passam a uma loção fechada e 500 ml de uso interno. Uma venda retira apenas a embalagem fechada; um serviço consome apenas o saldo aberto.

Em Serviços, configure o consumo por atendimento, inclusive valores como 5,25 ml (vírgula ou ponto). O débito usa o fluxo existente de conclusão do serviço. Sem saldo aberto suficiente, o consumo falha e os débitos da operação são desfeitos: no PDV com pagamento manual, nenhuma venda, agendamento ou baixa fica registrada, e o caixa vê a mensagem para abrir uma embalagem antes de finalizar. A exceção é o pagamento aprovado pelo Mercado Pago: como o dinheiro já foi capturado, o pagamento é registrado mesmo sem saldo aberto, o consumo não é debitado e um aviso fica no log para conciliação. Não há abertura automática de embalagens. Consumos e perdas avulsas do conteúdo aberto podem ser registrados na consulta, com quantidade e justificativa.

Quantidades fracionadas têm duas casas decimais. A unidade não pode mudar quando já houver saldo aberto, histórico fracionado ou vínculo a serviços. O histórico diferencia `un`, `ml` e `g`; não some quantidades de unidades distintas. A conferência física por contagem nesta versão é das embalagens fechadas.

## Câmera e conectividade

- Acesso em HTTPS (localhost é uma exceção para desenvolvimento).
- Permissão de câmera no navegador; preferência pela câmera traseira.
- Decodificação local com ZXing, carregado sob demanda. As imagens não são enviadas nem armazenadas.
- Leitura de códigos comerciais 1D, incluindo EAN e UPC; QR Codes não fazem parte deste fluxo.
- A câmera fecha ao reconhecer o código, fechar o leitor, ocultar a página ou navegar pelo Livewire.
- Digitação manual disponível em caso de permissão negada ou leitura difícil.
- Internet necessária para consultar e confirmar operações. Após falha de comunicação, confira o resultado antes de repetir uma ação.

## Atualização

Instale as dependências com `npm ci`, execute `php artisan migrate --force` no ambiente de destino e compile com `npm run build`. As duas migrations preservam produtos sem código. Em 20/10/2026, `migrate:status` com o `.env` local (que aponta para o banco `production`) já mostrava as duas migrations aplicadas (lote 9).

A reversão da migration de consumo fracionado converte as movimentações para números inteiros e remove os saldos abertos. Preserve um backup antes de reverter um ambiente que já utiliza ml/g.

## Validação

- Suíte PHPUnit: cadastro, duplicidade, edição, isolamento entre filiais, consulta sem baixa, saldo insuficiente, inventário desatualizado, entrada, venda, abertura de embalagem e consumo decimal de serviços.
- Build de produção e formatação PHP.
- Navegador Chromium com viewport móvel: EAN-13 decodificado de stream de câmera simulado, pausa automática, consulta, entrada, caixa, permissão negada, cadastro em ml, abertura de embalagem e saída de 5,25 ml.
- Ainda é necessária homologação em Android e iPhone físicos para foco, reflexos, iluminação, desempenho e permissões específicas do aparelho. O teste simulado não comprova esses aspectos.

Lotes, validade, fornecedores, conversão de caixas e operação offline são expansões futuras. Esta entrega mantém os fluxos existentes de pagamento e cancelamento; não cria uma nova operação financeira de reembolso ou devolução de mercadoria.

## Organização da tela

A aba **Produtos em estoque** reúne busca por nome, consulta pela câmera e filtros de disponibilidade. **Estoque baixo** mostra produtos com saldo positivo menor ou igual ao mínimo; **Esgotados** mostra saldo zero. Os contadores consideram todos os produtos controlados da filial, enquanto o resultado combina busca e filtro.

Os cartões mostram saldo fechado, mínimo e, quando aplicável, saldo aberto. Tocar no cartão abre os detalhes com as ações do produto. **+ Repor** abre diretamente o formulário de entrada; após confirmar, os detalhes mostram o saldo atualizado e a mensagem de sucesso; ao cancelar, volta para a lista.

O histórico fica na aba **Movimentações**, com filtros de produto e tipo. Nos detalhes, **Ver movimentações deste produto** abre o histórico já filtrado. Aba, busca e filtros ficam na URL (`?aba=historico&produto=…`), então recarregar ou compartilhar o link mantém o contexto. O consumo avulso em ml/g fica na seção expansível de uso interno.

Todos os textos da tela, do leitor e das mensagens de erro seguem o idioma escolhido no painel (pt/es), em `lang/{pt,es}/estoque.php`.
