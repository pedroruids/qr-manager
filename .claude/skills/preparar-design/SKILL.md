---
name: preparar-design
description: Monta o briefing completo para desenhar um ecrã numa sessão de design separada — sistema visual, tokens, componentes existentes e critérios do issue, prontos a colar. Usar antes de abrir a sessão de design.
argument-hint: [nome-do-ecra]
disable-model-invocation: true
---

Monta o briefing que vai para a sessão de design. Não desenhas nada aqui.

O protocolo está em `docs/DESIGN.md`, secção "Protocolo de entrega do design".
Esta skill produz a **ida**; a `validar-design` confere a **volta**.

## Porquê

Uma sessão de design sem o sistema à frente inventa o sistema. Sai bonita e sai
fora — e isso só se nota ao décimo ecrã, quando corrigir já é refazer. O briefing
existe para que a sessão não tenha de adivinhar nada.

## Passos

### 1. Identificar o ecrã

Do argumento. Se não vier, perguntar qual é — e não avançar com um nome vago
("o ecrã dos QRs" não serve; `lista-qrs` serve).

Confirmar que o ecrã está na lista de `docs/PRODUTO.md`. Se não estiver, parar e
dizer: um ecrã que não vem dos fluxos não devia estar a ser desenhado.

### 2. Encontrar o issue

```bash
gh issue list --search "<ecra>" --state open --limit 10
```

Sem issue, **parar**. Os critérios de aceitação são metade do briefing; sem eles
a sessão desenha para o que imagina, não para o que foi decidido.

### 3. Recolher as quatro peças

Por esta ordem, que é a ordem em que vão para o briefing:

1. `docs/DESIGN.md` — **inteiro**, não resumido. Resumir é escolher o que a
   sessão pode ignorar, e essa escolha não é tua.
2. O bloco `@theme` de `resources/css/app.css` — **à letra**, com todos os
   tokens. Uma aproximação produz um mockup que não corresponde à aplicação.
3. A lista de `resources/views/components/` — o que já existe não se redesenha.
4. O issue: título, corpo e critérios de aceitação.

```bash
ls resources/views/components/
gh issue view <numero>
```

### 4. Acrescentar o que o ecrã tem de específico

Duas ou três linhas, tuas, a partir de `docs/PRODUTO.md`:

- Quem chega a este ecrã e vindo de onde
- O fluxo (F1–F6) a que pertence e **o que pode correr mal** nesse fluxo — é daí
  que saem os estados de erro que valem a pena desenhar
- As fronteiras que tocam este ecrã ("não é ferramenta de design", etc.)

### 5. Escrever o briefing

Ficheiro em `docs/briefings/<ecra>.md`, e mostrar o conteúdo para copiar:

```markdown
# Briefing de design — <ecra>

## O produto
<duas frases, de docs/PRODUTO.md>

## Quem chega aqui
<utilizador concreto, vindo de onde, a querer o quê>

## O que este ecrã tem de resolver
<do issue: problema e comportamento desejado>

## Critérios de aceitação
<copiados do issue, sem alterar>

## O que pode correr mal
<do fluxo respectivo, em docs/PRODUTO.md>

## Fronteiras
<o que este ecrã não faz — de docs/PRODUTO.md>

## Sistema de design
<docs/DESIGN.md inteiro>

## Tokens (@theme, à letra de resources/css/app.css)
```css
<bloco copiado>
```

## Componentes que já existem
<lista de resources/views/components/ e dos flux: usados noutros ecrãs>

## O que tem de vir de volta
- HTML autónomo, ficheiro único, para `docs/mockups/<ecra>.html`
- Tailwind 4 por `<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4">`
- O bloco `@theme` acima, copiado à letra
- Os quatro estados no mesmo ficheiro: vazio, a carregar, erro, cheio
- Dados de exemplo plausíveis, em português de Portugal
- Comentários HTML a marcar o que cada bloco vai ser na implementação
  (`<!-- flux:button variant=primary -->`)
- **Lista explícita dos componentes novos que o ecrã exige**
```

### 6. Entregar

Dizer que ficheiro foi criado, e que o passo seguinte é abrir a sessão de design
com este conteúdo. Lembrar que a volta se valida com `/validar-design <ecra>`.

## Regras

- **Não resumir o `DESIGN.md`.** Vai inteiro.
- **Não desenhar nada nesta skill.** Nem uma sugestão de layout — o briefing que
  já traz solução deixa de ser briefing.
- **Não inventar critérios** que não estejam no issue. Se faltarem, o issue não
  está pronto: dizer isso em vez de os preencher.
- Se o ecrã depender de outro ainda por desenhar, assinalar a dependência.
