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

---

## Componentes base

A base vem do **Flux UI 2** — `flux:button`, `flux:input`, `flux:select`,
`flux:badge`, `flux:table`, `flux:modal`, `flux:callout`. **Usa-se o Flux
primeiro**; só se cria componente próprio quando o Flux não cobre o caso.

Componentes próprios vivem em `resources/views/components/`. Previstos:

- `x-qr-preview` — o código renderizado, com moldura e fundo branco
- `x-empty-state` — ícone, título, descrição, ação
- `x-copy-field` — valor mono com botão de copiar (slug, token)

**Criar componente novo exige justificação.** Se algo aparece em dois ecrãs, é
componente. Se aparece num, é composição.

**Excepção ao raio:** os badges de estado são `rounded-full`, não `--radius-card`.
É a convenção do `flux:badge` e lutar contra ela dá mais custo do que valor.
É a única excepção; tudo o resto usa o raio do sistema.

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

A skill `/preparar-design <ecra>` monta este briefing pronto a colar.

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
