# Revisão de UI/UX: campos e modais

Data: 20/10/2026.

## Escopo e método

Inspeção visual com Chromium/Playwright e aplicação Laravel/Livewire real, utilizando uma cópia temporária do banco. Skills aplicadas: UI/UX Design Review, web-design-reviewer e playwright. Foram comparadas capturas anteriores e posteriores dos cadastros de serviços, produtos, clientes, usuários, barbeiros, despesas e filiais.

Larguras verificadas: 320, 375, 768, 1280 e 1920 px; altura de 568 px no menor cenário e de 900 px nos demais. São 35 combinações por rodada. Também foram exercitados o tema escuro e os campos condicionais de consumo em ml.

## Problemas encontrados e ajustes

| Prioridade | Problema | Ajuste |
| --- | --- | --- |
| Alta | Nomes dos produtos consumidos praticamente invisíveis: os controles ocupavam as duas colunas estreitas do modal de serviços. | Modal de serviços com largura máxima de 672 px; uma linha por produto, nome com quebra e controles abaixo no celular. |
| Alta | Valores monetários comprimidos pelo prefixo em telas estreitas. | Campos de preço, duração e estoque passam a uma coluna no celular e duas em telas maiores. |
| Média | Botão de fechar com área aproximada de 9 × 24 px. | Área de 44 × 44 px e foco visível. |
| Média | Telefone e documento tinham apenas um terço da linha no cadastro de clientes. | Duas colunas equivalentes no desktop e uma no celular. |
| Média | Cidade, estado, telefone, valor e data continuavam em duas colunas no celular. | Grades de filiais e despesas passam a uma coluna em telas estreitas. |
| Média | Campos com texto de 14 px no celular e controles com alturas diferentes. | Texto de 16 px no celular, 14 px em telas maiores; campos de linha única com caixa visual de pelo menos 44 px e botões médios com altura mínima de 44 px. |
| Alta | Modal sem nome acessível associado ao título e sem contenção de foco. | Associação do título, foco contido, bloqueio da rolagem do fundo e retorno do foco ao fechar. |
| Média | Selects e áreas de texto sem associação acessível às mensagens de erro. | `aria-invalid` e `aria-describedby`, IDs explícitos respeitados e mensagens com cores para ambos os temas. |

O cabeçalho recebeu separação visual e o conteúdo tem rolagem interna, mantendo o fechamento acessível. A altura máxima acompanha a viewport dinâmica com margem externa de 16 px.

## Validação

- 35 combinações de modal e largura verificadas antes e depois, sem campos ou botões ultrapassando as laterais da viewport nas medições realizadas.
- Inspeção das capturas confirmou a recuperação dos nomes de produtos e do espaço para valores.
- Teclado: foco inicial, Tab/Shift+Tab contidos, Escape, retorno ao acionador e bloqueio de rolagem do fundo aprovados.
- Validação de formulário: erro do nome visível e associado ao campo.
- Produto fracionado: campo de conteúdo aparece ao selecionar ml, texto de ajuda associado e ações alcançáveis pela rolagem.
- 50 testes de cadastro aprovados, com 155 assertions.
- `npm run build` aprovado. Aviso não bloqueante sobre dependência opcional `fontaine` para otimização de fontes.
- Nenhum erro JavaScript registrado nos fluxos de interação exercitados.

## Limites

Esta revisão cobre os modais de cadastro e componentes compartilhados indicados. Não constitui auditoria completa de WCAG, contraste de toda a aplicação, leitores de tela, Safari/Firefox ou teclado virtual em aparelho físico. Os demais modais que usam o componente compartilhado recebem os ajustes estruturais, mas não foram todos percorridos individualmente.
