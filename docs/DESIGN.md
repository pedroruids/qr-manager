# Sistema de design

Este ficheiro define as restrições visuais do produto. **Todo o ecrã novo é
composto dentro delas.** Não se introduzem cores, tamanhos ou componentes novos
sem uma decisão explícita registada aqui.

Lido pelo Claude em qualquer sessão de design ou de implementação de interface.

---

## As quatro decisões

Estas são deliberadas e humanas. É aqui que vive a identidade do produto.

**Tipografia:** **Inter** em tudo — interface e títulos. Uma só família.
Escala: 12 / 14 / 16 / 20 / 24 / 32 / 48
Slugs, tokens de API e outros valores copiáveis vão em `font-mono` (a mono do
sistema), porque têm de ser lidos carácter a carácter.

**Cor de marca:** **indigo `#4F46E5`** (`brand-600`) — ações primárias e estados
ativos. Nada mais. Escuro para hover: `#4338CA` (`brand-700`).
**Neutra:** **zinc**

**Forma:** raio de cantos **`0.5rem`** (8px) · bordas 1px `zinc-200`
O código QR em si é sempre quadrado e sem raio — é o único elemento com cantos
retos, e é assim de propósito.

**Densidade:** **compacta** — isto é uma aplicação de trabalho; a lista de QRs
tem de mostrar muitas linhas sem scroll.
Unidade base de espaçamento: **4px**

---

## Modo escuro

O produto tem os dois temas. O starter kit traz o alternador nas definições e o
`app.css` tem o `@custom-variant dark`. **Todo o ecrã se desenha nos dois** — um
ecrã só claro não está desenhado.

Não é uma paleta nova: é a mesma, invertida por regra fixa. Decorar esta tabela
poupa a decisão caso a caso, que é onde a incoerência entra.

| Papel | Claro | Escuro |
|---|---|---|
| Fundo da página | `zinc-50` | `zinc-950` |
| Superfície (cartão, cabeçalho, barra lateral) | `white` | `zinc-900` |
| Superfície secundária (cabeçalho de tabela, faixa neutra) | `zinc-50` | `zinc-900` |
| Preenchimento (skeleton, avatar, chip) | `zinc-100` / `zinc-200` | `zinc-800` |
| Borda | `zinc-200` | `zinc-800` |
| Borda de controlo (input, botão secundário) | `zinc-300` | `zinc-700` |
| Borda apagada (ponto de estado inactivo) | `zinc-400` | `zinc-600` |
| Interruptor desligado | `zinc-300` | `zinc-700` |
| Chip de ícone em estado vazio | `brand-50` | `brand-900` |
| Texto | `zinc-900` | `zinc-100` |
| Texto secundário | `zinc-500` / `zinc-600` | `zinc-400` |
| Texto apagado (zero, "nunca usado") | `zinc-400` | `zinc-500` |
| Ação primária | `brand-600`, texto branco | `brand-600`, texto branco |
| Ligação e ênfase | `brand-600` / `brand-700` | `brand-300` |
| Erro: texto | `red-700` | `red-300` |
| Erro: ícone e borda | `red-600` / `red-200` | `red-400` / `red-900` |

Três regras que não se negoceiam:

1. **O código QR mantém sempre fundo branco**, nos dois temas. Um QR sobre fundo
   escuro pode não ser lido, e a moldura branca é a *quiet zone* — é função, não
   estilo.
2. **A ação primária não muda de cor.** `brand-600` com texto branco tem
   contraste suficiente sobre `zinc-900`; trocar por um tom claro obrigaria a
   trocar também a cor do texto, e passariam a existir dois botões primários
   diferentes no mesmo produto.
3. **Nada de `dark:` avulso.** Se um par claro/escuro não estiver nesta tabela,
   acrescenta-se aqui primeiro.

---

## Cores

| Uso | Token | Quando |
|---|---|---|
| Primária | `brand-600` | Ação principal do ecrã — **uma por ecrã** |
| Texto | `zinc-900` | Corpo |
| Texto secundário | `zinc-500` | Metadados, legendas |
| Fundo | `white` / `zinc-50` | Página e superfícies |
| Borda | `zinc-200` | Separadores, contornos |
| Sucesso / Aviso / Erro | `emerald-600` / `amber-600` / `red-600` | Feedback de estado **e** estado de dados |

Regra: **cor comunica, não decora.** Se um elemento não muda de significado com a cor, é neutro.

**Estado de dados** (um QR activo ou inactivo) pode usar as cores de estado, com
duas condições: a cor entra como ponto ou contorno pequeno, nunca como fundo da
linha; e o rótulo em texto está sempre presente. Nunca só cor.

**Valor ausente é informação, não erro.** Zero leituras escreve-se `0` em
`zinc-400` — dito, mas sem puxar o olho. Não se esconde nem se substitui por "—".

**Não há fundos de cor.** Faixas de aviso e blocos informativos dentro de um ecrã
são neutros: `zinc-50`, ícone em `zinc-500`, texto em `zinc-700`. As cores de
estado entram como ícone, ponto ou borda — nunca como superfície. Excepção única:
`flux:callout` de erro, que leva borda `red-200` e mantém o fundo branco.

---

## Componentes base

A base vem do **Flux UI 2** — `flux:button`, `flux:input`, `flux:select`,
`flux:badge`, `flux:table`, `flux:modal`, `flux:callout`. **Usa-se o Flux
primeiro**; só se cria componente próprio quando o Flux não cobre o caso.

Componentes próprios vivem em `resources/views/components/`. Existem:

- `x-qr-preview` — o código renderizado, com moldura e fundo branco. Recebe um
  `QrCode` e pede o SVG ao `GeradorQrCode`; é componente de classe por isso
- `x-empty-state` — título, descrição, slot `icone` e slot `acao` opcionais.
  `compacto` para dentro de um cartão, e `valor` quando o vazio é uma contagem
  a zero
- `x-copy-field` — valor mono com botão de copiar (slug, token). `texto` mostra
  uma versão curta; o que vai para a área de transferência é sempre o `valor`
  completo

Previsto e ainda por construir: `x-bar-chart` (leituras por dia).

**Criar componente novo exige justificação.** Se algo aparece em dois ecrãs, é
componente. Se aparece num, é composição.

**Excepção ao raio:** `rounded-full` só em três coisas — os badges de estado
(convenção do `flux:badge`, e lutar contra ela custa mais do que vale), os pontos
indicadores de estado, e o interruptor (`flux:switch`), cuja forma é o próprio
significado. **Avatares e chips usam `rounded-card`**, como tudo o resto.

---

## Regras de composição

1. **Uma ação primária por ecrã.** As restantes são secundárias ou ghost.
2. **Largura máxima de texto:** ~70 caracteres. Conteúdo largo em `max-w-3xl`.
3. **Espaçamento vertical** entre secções: sempre o mesmo valor. Não afinar caso a caso.
4. **Alinhamento à esquerda** por defeito. Números alinhados à direita em tabelas.
5. **Nada de sombras** exceto em elementos flutuantes (modal, dropdown).
6. **Texto que pode ser longo trunca numa linha**, com o valor completo em `title`.
   As larguras saem da grelha da tabela (`table-fixed` com percentagens), não de
   valores soltos em pixéis.
7. **Botão a trabalhar e botão desligado não são a mesma coisa.**
   A trabalhar: `bg-brand-600` sólido, com spinner e `aria-busy` — está a fazer
   algo. Desligado: `bg-zinc-200` com texto `zinc-500` — não faz nada, e lê-se
   como neutro. **Nunca a cor de marca com opacidade:** marca esbatida é a mesma
   cor a dizer duas coisas diferentes.
8. **Uma ação por ecrã, não uma por bloco.** Num estado vazio, a ação primária é
   a do estado vazio, e a do cabeçalho passa a secundária. Duas primárias com o
   mesmo texto é a mesma decisão pedida duas vezes.

---

## Estados obrigatórios

Todo o ecrã que mostra dados tem de definir os quatro:

- **Vazio** — primeira utilização. Explica o que aparecerá aqui e dá a ação para começar.
- **A carregar** — skeleton, não spinner, quando a estrutura é conhecida.
- **Erro** — o que falhou, em linguagem humana, e o que fazer a seguir.
- **Cheio** — com muitos dados. Onde a paginação ou o scroll entram.

Um ecrã sem estado vazio definido não está desenhado.

---

## Acessibilidade — mínimos

- Contraste de texto ≥ 4.5:1 (≥ 3:1 para texto grande)
- Todos os controlos alcançáveis por teclado, com foco visível
- Ícone sozinho como ação leva sempre `aria-label`
- A informação nunca é transmitida só por cor

---


## Protocolo de entrega do design

Os ecrãs são desenhados **numa sessão de design separada** (conversa com
artifacts, onde se vê o resultado a render) e entregues aqui como ficheiro.
O sistema visual — tokens e componentes — **não** se desenha lá: fixa-se neste
projeto primeiro, e só depois se desenham ecrãs dentro dele.

Motivo da separação: desenhar onde se vê fecha o ciclo de iteração em segundos;
e uma sessão que já tem a implementação na cabeça desenha para o que é fácil de
construir, não para o que é bom.

### O que levar para a sessão de design

Sem isto, a sessão inventa o sistema em vez de o seguir — e o resultado só se
nota ao décimo ecrã, quando corrigir já é refazer.

1. Este ficheiro, `docs/DESIGN.md`, inteiro
2. O bloco `@theme` de `resources/css/app.css`, à letra
3. A lista de componentes que já existem em `resources/views/components/`
4. O issue do ecrã, com os critérios de aceitação

A skill `/preparar-design <ecra>` monta este briefing pronto a colar e guarda-o
em `docs/briefings/<ecra>.md`.

### O que trazer de volta

Um **HTML autónomo**, não uma imagem nem uma descrição. É isto que preserva a
propriedade que torna o método bom: o artefacto de design já é código, e não há
tradução a perder-se entre o desenhado e o implementado.

- Ficheiro único, guardado em `docs/mockups/<ecra>.html` e commitado
- Tailwind 4 via `<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>`
- O bloco `@theme` **copiado à letra** do `app.css` — não uma aproximação
- **Os quatro estados** no mesmo ficheiro, separados por cabeçalho: vazio, a
  carregar, erro, cheio
- Dados de exemplo plausíveis. Nunca "Lorem ipsum" nem "Teste 1" — dados falsos
  irrealistas escondem os problemas de layout que só aparecem com conteúdo real
- Uma lista explícita de **componentes novos que o ecrã exige**

Essa última lista é a que se perde sempre e a que mais custa depois. Um ecrã que
precisa de um `x-qr-preview` que ainda não existe não é um trabalho, são dois.
Cada componente novo vira issue com a label `área: design`.

### O Flux não existe no mockup

O Flux UI 2 renderiza através de Blade. Numa página HTML solta não há
`flux:button`. O mockup **aproxima** o Flux com HTML e Tailwind; a implementação
troca a aproximação pelo componente real.

Por isso o mockup deve marcar, em comentário HTML, o que cada bloco vai ser:

```html
<!-- flux:button variant=primary -->
<button class="rounded-[--radius-card] bg-brand-600 px-3 py-1.5 text-sm font-medium text-white">
  Criar QR
</button>
```

| No mockup | Na implementação |
|---|---|
| `<button>` estilizado | `flux:button` |
| `<input>` com label e hint | `flux:input` |
| `<table>` | `flux:table` |
| `<span>` arredondado | `flux:badge` |
| Caixa de aviso | `flux:callout` |
| QR, estado vazio, campo copiável | `x-qr-preview`, `x-empty-state`, `x-copy-field` |

Sem estas marcas, quem implementa reconstrói a decisão a partir do aspeto — e é
aí que o implementado começa a afastar-se do desenhado.

### Validação, antes de implementar

`/validar-design <ecra>` confere o mockup recebido contra este ficheiro:
tokens fora do sistema, componentes que não existem, estados em falta, regras de
composição quebradas. Só depois de passar é que o ecrã entra em implementação.

O mockup aprovado é a referência — **não é o componente final.** A
implementação em Blade faz-se a sério, com os componentes reais.

---

## Registo de alterações

Alterações ao sistema ficam aqui, com data e razão.

| Data | Alteração | Porquê |
|---|---|---|
| 2026-08-15 | Sistema inicial: Inter, indigo `#4F46E5`, zinc, raio `0.5rem`, densidade compacta | Decidido no arranque do projeto, antes de qualquer ecrã |
| 2026-08-15 | Cores de estado passam a cobrir estado de dados (activo/inactivo), com rótulo em texto obrigatório | Saiu do mockup `lista-qrs`: sem isto o estado de um QR ficava indistinguível na tabela |
| 2026-08-15 | Zero escreve-se `0` em `zinc-400` | Distinguir "ainda sem leituras" de erro sem inventar um símbolo |
| 2026-08-15 | Badges de estado em `rounded-full`, única excepção ao raio do sistema | Convenção do `flux:badge`; contrariá-la custa mais do que vale |
| 2026-08-15 | Ecrãs passam a ser desenhados em sessão separada, entregues como HTML com os quatro estados e a lista de componentes novos | Ver o ecrã a render fecha o ciclo de iteração; a entrega em código evita reintroduzir handoff |
| 2026-08-15 | Sem fundos de cor: faixas de aviso são neutras (`zinc-50`, ícone `zinc-500`) | Saiu do mockup `detalhe-qr`, onde o aviso de código inactivo usava `amber-50`. Cor entra como ícone ou borda, não como superfície |
| 2026-08-15 | Botão a trabalhar vs. desligado: `brand-600` sólido com spinner vs. `zinc-200`/`zinc-500`. Nunca marca com opacidade | Saiu da validação dos seis mockups, que tinham `brand-600/70` e `/40` — dois valores para a mesma ideia, e marca esbatida a significar duas coisas |
| 2026-08-15 | Em estado vazio, a ação do cabeçalho passa a secundária | Duas primárias com o mesmo texto no mesmo ecrã (lista e tokens) é pedir a mesma decisão duas vezes |
| 2026-08-15 | Modo escuro entra no sistema, com tabela fixa de pares claro/escuro; o QR mantém fundo branco e a ação primária não muda de cor | A aplicação sempre teve `.dark` e alternador; os seis ecrãs estavam desenhados só para claro. Regra fixa evita decidir caso a caso |
| 2026-08-15 | `rounded-full` limitado a badges e pontos de estado; avatares passam a `rounded-card` | A excepção estava a alastrar a tudo o que era redondo |
| 2026-08-15 | Página de erro do redirect sem assinatura de marca | É vista por quem não é cliente e não veio cá ter por vontade própria; o nome do produto não lhe resolve nada |
