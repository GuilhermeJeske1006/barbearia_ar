# 0004 — Livewire 4 em modo classe clássica, não single-file component

**Status:** Aceito

## Contexto

O documento de arquitetura original especificava Livewire 3, com a estrutura `app/Livewire/{Grupo}/{Componente}.php` + view Blade correspondente em `resources/views/livewire/`. No momento do scaffold, a versão atual disponível via Composer é Livewire 4, cujo gerador (`make:livewire`) por padrão cria **single-file components** (SFC): um único arquivo `.blade.php` combinando classe PHP anônima (`new class extends Component {}`) e template, sob `resources/views/components/`.

SFCs não têm um nome de classe referenciável — são `new class extends Component`, anônima. Isso quebra o roteamento direto usado no fluxo público (`Route::get('/', AgendamentoWizard::class)`), que depende de uma classe nomeada e autoloadable.

## Decisão

Todos os componentes foram gerados com a flag `--class` (`php artisan make:livewire NomeDoComponente --class`), preservando a estrutura clássica: classe em `app/Livewire/`, view em `resources/views/livewire/`. Isso mantém a estrutura de pastas da seção 8 do documento de arquitetura praticamente inalterada, e mantém o roteamento direto por classe funcionando.

## Consequências

- Perde-se a conveniência de colocation (lógica + template no mesmo arquivo) que os SFCs do Livewire 4 oferecem — trade-off aceito para preservar a estrutura de pastas já documentada e o roteamento por classe.
- Novos componentes criados no projeto devem continuar usando `--class` para manter consistência; se algum componente for criado sem a flag (SFC), ele não pode ser alvo direto de `Route::get()` e precisa ser incluído via `<livewire:... />` dentro de uma página classe já roteada.
- Se o time decidir adotar SFCs no futuro (ex.: para componentes pequenos, não-roteáveis, como partials de UI), isso pode coexistir sem problema — a decisão aqui é só sobre os componentes que são alvo direto de rota.
