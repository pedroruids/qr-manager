# Briefing base — o sistema, para qualquer sessão de design

Cola isto no início de uma sessão de design, seguido do issue do ecrã.

O protocolo está em `docs/DESIGN.md`, secção "Protocolo de entrega do design".
Este ficheiro é a parte que não muda de ecrã para ecrã: o sistema visual, os
tokens à letra e os componentes que já existem. **Falta sempre a quarta parte —
o issue do ecrã, com os critérios de aceitação.**

---

## 1. O produto, em cinco linhas

**qr-manager** — códigos QR dinâmicos. O código impresso codifica um endereço
curto deste sistema (`/{slug}`); o slug redirecciona para o destino, e o destino
é editável sem reimprimir nada. Para o gestor de marketing de uma PME que
imprime flyers: a campanha muda, o papel fica.

Quem usa a aplicação está autenticado e a trabalhar. Quem lê o código é um
anónimo com o telemóvel na mão, que nunca ouviu falar do produto.

---

## 2. As quatro decisões do sistema

**Tipografia:** Inter em tudo. Escala 12 / 14 / 16 / 20 / 24 / 32 / 48.
Slugs, tokens de API e outros valores copiáveis vão em `font-mono` — são lidos
carácter a carácter.

**Cor de marca:** indigo `#4F46E5` (`brand-600`), só para acções primárias e
estados activos. Hover `#4338CA` (`brand-700`). **Neutra:** zinc.

**Forma:** raio `0.5rem`, bordas 1px `zinc-200`. O código QR é sempre quadrado e
sem raio — é o único elemento com cantos rectos, e é de propósito.

**Densidade:** compacta. É uma aplicação de trabalho; a lista tem de mostrar
muitas linhas sem scroll. Unidade base de espaçamento: 4px.

---

## 3. Tokens — copiados à letra de `resources/css/app.css`

Não é uma aproximação. É o bloco que a aplicação usa.

```css
@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';

    --color-brand-50: #eef2ff;
    --color-brand-100: #e0e7ff;
    --color-brand-200: #c7d2fe;
    --color-brand-300: #a5b4fc;
    --color-brand-400: #818cf8;
    --color-brand-500: #6366f1;
    --color-brand-600: #4f46e5;
    --color-brand-700: #4338ca;
    --color-brand-800: #3730a3;
    --color-brand-900: #312e81;

    --radius-card: 0.5rem;

    --color-zinc-50: #fafafa;
    --color-zinc-100: #f5f5f5;
    --color-zinc-200: #e5e5e5;
    --color-zinc-300: #d4d4d4;
    --color-zinc-400: #a3a3a3;
    --color-zinc-500: #737373;
    --color-zinc-600: #525252;
    --color-zinc-700: #404040;
    --color-zinc-800: #262626;
    --color-zinc-900: #171717;
    --color-zinc-950: #0a0a0a;

    --color-accent: var(--color-brand-600);
    --color-accent-content: var(--color-brand-600);
    --color-accent-foreground: var(--color-white);
}
```

---

## 4. Modo escuro — tabela fixa, não decidida caso a caso

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

1. **O código QR mantém fundo branco nos dois temas.** Um QR sobre fundo escuro
   pode não ser lido, e a moldura branca é a *quiet zone* — é função, não estilo.
2. **A acção primária não muda de cor.**
3. **Nada de `dark:` avulso.** Par que não esteja na tabela acrescenta-se à
   tabela primeiro.

---

## 5. Componentes que já existem

Do **Flux UI 2** — usa-se primeiro, e só se cria componente próprio quando o
Flux não cobre o caso: `flux:button`, `flux:input`, `flux:select`, `flux:switch`,
`flux:badge`, `flux:table`, `flux:modal`, `flux:callout`, `flux:link`,
`flux:heading`, `flux:text`, `flux:sidebar.*`, `flux:toast`.

Próprios, em `resources/views/components/`:

| Componente | Interface | Para quê |
|---|---|---|
| `x-copy-field` | `valor`, `texto` (versão curta a mostrar), `rotulo` (aria-label do botão) | Valor mono com botão de copiar. Mostra o curto, copia o `valor` completo |
| `x-empty-state` | `titulo`, `descricao`, `compacto`, `valor`, slots `icone` e `acao` | Estado vazio. Sem slot de acção não desenha botão nenhum |
| `x-qr-preview` | `qr-code` (modelo `QrCode`) | O código renderizado. Fundo branco nos dois temas, sem raio, 1:1 |
| `x-bar-chart` | `serie` (lista de `data`/`valor`), `rotulo` | Barras por dia. Sem JavaScript. Dias a zero com linha de base |

---

## 6. Regras de composição

1. **Uma acção primária por ecrã.** As restantes são secundárias ou ghost.
2. **Largura máxima de texto** ~70 caracteres; conteúdo largo em `max-w-3xl`.
3. **Espaçamento vertical entre secções sempre igual.** Não afinar caso a caso.
4. **Alinhamento à esquerda** por defeito; números à direita em tabelas.
5. **Sem sombras**, excepto em elementos flutuantes.
6. **Texto longo trunca numa linha**, com o valor completo no `title`. As
   larguras saem da grelha (`table-fixed` com percentagens), não de pixéis soltos.
7. **Botão a trabalhar ≠ botão desligado.** A trabalhar: `brand-600` sólido com
   spinner e `aria-busy`. Desligado: `zinc-200` com texto `zinc-500`. Nunca
   marca com opacidade.
8. **Uma acção por ecrã, não uma por bloco.** Num estado vazio, a primária é a do
   estado vazio, e a do cabeçalho passa a secundária.

**Sem fundos de cor.** Faixas de aviso são neutras: `zinc-50`, ícone `zinc-500`,
texto `zinc-700`. As cores de estado entram como ícone, ponto ou borda — nunca
como superfície.

**Zero é informação, não erro.** Escreve-se `0` em `zinc-400`. Não se esconde nem
se substitui por travessão.

**Estado nunca é só cor.** O ponto acompanha sempre o rótulo em texto.

---

## 7. Os quatro estados, obrigatórios

Todo o ecrã que mostra dados define os quatro, no mesmo ficheiro, separados por
cabeçalho: **vazio**, **a carregar** (skeleton, não spinner), **erro**, **cheio**.

Um ecrã sem estado vazio definido não está desenhado. Se o ecrã não mostrar dados
— como a página pública de código inactivo — declara-se o desvio no próprio
mockup e usam-se os quatro estados que esse ecrã realmente tem.

---

## 8. Acessibilidade — mínimos

- Contraste ≥ 4.5:1 (≥ 3:1 para texto grande)
- Tudo alcançável por teclado, com foco visível
- Ícone sozinho como acção leva `aria-label`
- Informação nunca transmitida só por cor

---

## 9. O que trazer de volta

Um **HTML autónomo**, não uma imagem nem uma descrição — o artefacto de design já
é código, e não há tradução a perder-se.

- Ficheiro único em `docs/mockups/<ecra>.html`
- Tailwind 4 por `<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>`
- O bloco `@theme` acima, copiado à letra
- Os quatro estados, separados por cabeçalho
- Dados de exemplo plausíveis. Nunca "Lorem ipsum" nem "Teste 1" — dados falsos
  irrealistas escondem os problemas de layout que só aparecem com conteúdo real
- Comentário HTML a marcar o que cada bloco vai ser na implementação
  (`<!-- flux:button variant=primary -->`), porque o Flux não existe no mockup
- **Uma lista explícita dos componentes novos que o ecrã exige.** É a que se
  perde sempre e a que mais custa depois: um ecrã que precisa de um componente
  que ainda não existe não é um trabalho, são dois

### O que **não** pode existir no ficheiro

"Autónomo" descreve o resultado e não chega como instrução. O que não pode
existir:

- **Propriedades editáveis ou estado** — nada de props, selectores ou
  alternadores. Os estados aparecem todos ao mesmo tempo, empilhados e separados
  por cabeçalho.
- **Ciclos e interpolações** — `{{ }}`, `<sc-for>`, `<sc-if>` ou equivalente. As
  repetições escrevem-se à mão, linha a linha. Sete linhas em vez de um ciclo de
  sete: mais verboso de propósito, porque assim o ficheiro diz o que se vê.
- **Coisas geradas em execução** — um código QR desenhado por JavaScript não é um
  código QR, é ruído com a forma de um.

**O teste:** com o JavaScript desligado, o ficheiro mostra tudo.

**Entrega em bloco de código, nunca pelo botão de descarregar.** O botão guarda
*a página que se viu*, não o código que a fez: as plataformas de artifacts
reembrulham tudo com o runtime necessário para a mostrar.

Antes de entrar em `docs/mockups/`:

```bash
grep -c "{{\|<sc-\|__bundler\|DCLogic" ficheiro.html
```

Zero. Qualquer outro número quer dizer que veio template.

---

Depois: `/validar-design <ecra>` confere o mockup contra o `docs/DESIGN.md` antes
de entrar em implementação.
