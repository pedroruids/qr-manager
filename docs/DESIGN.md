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

## Registo de alterações

Alterações ao sistema ficam aqui, com data e razão.

| Data | Alteração | Porquê |
|---|---|---|
| 2026-08-15 | Sistema inicial: Inter, indigo `#4F46E5`, zinc, raio `0.5rem`, densidade compacta | Decidido no arranque do projeto, antes de qualquer ecrã |
| 2026-08-15 | Cores de estado passam a cobrir estado de dados (activo/inactivo), com rótulo em texto obrigatório | Saiu do mockup `lista-qrs`: sem isto o estado de um QR ficava indistinguível na tabela |
| 2026-08-15 | Zero escreve-se `0` em `zinc-400` | Distinguir "ainda sem leituras" de erro sem inventar um símbolo |
| 2026-08-15 | Badges de estado em `rounded-full`, única excepção ao raio do sistema | Convenção do `flux:badge`; contrariá-la custa mais do que vale |
