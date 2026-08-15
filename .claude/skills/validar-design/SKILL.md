---
name: validar-design
description: Confere um mockup recebido contra o sistema de design — tokens fora do sistema, componentes que não existem, estados em falta, regras de composição quebradas. Usar antes de implementar o ecrã em Blade.
argument-hint: [nome-do-ecra]
disable-model-invocation: true
---

Confere `docs/mockups/<ecra>.html` contra `docs/DESIGN.md`. Não implementas nada
aqui, e não reescreves o mockup sem autorização.

O protocolo está em `docs/DESIGN.md`, secção "Protocolo de entrega do design".
Esta skill confere a **volta**; a `preparar-design` monta a **ida**.

## Porquê

O mockup vem de uma sessão que não tem o projeto à frente. Aceitar sem conferir
é deixar entrar tokens que não existem e componentes que ninguém vai construir —
e descobrir isso a meio da implementação, quando já custa.

## Antes

Ler `docs/DESIGN.md` e o bloco `@theme` de `resources/css/app.css`. Sem isto não
há critério nenhum contra o qual conferir.

## As sete verificações

Correr todas. Reportar todas, mesmo as que passam.

### 1. Tokens à letra

O bloco `@theme` do mockup tem de ser **igual** ao de `resources/css/app.css`.
Não parecido — igual. Comparar valor a valor e listar as diferenças.

### 2. Cores fora do sistema

Procurar cores que não venham dos tokens:

```bash
grep -oE '(bg|text|border|fill|stroke)-[a-z]+-[0-9]{2,3}' docs/mockups/<ecra>.html | sort -u
```

Tudo o que não for `brand-*`, `zinc-*`, `white`, ou as cores de estado
autorizadas (`emerald-600`, `amber-600`, `red-600` e os seus tons de borda) é
achado. Valores em hex ou `rgb()` soltos são achado sempre.

### 3. Raios e tamanhos

- Raio: só `rounded-card`. Excepção única e já registada: `rounded-full` nos
  badges de estado. Qualquer `rounded-lg`, `rounded-md` ou `rounded-[...]` é achado.
- Tipografia: só a escala do `DESIGN.md`. `text-[15px]` e afins são achado.
- Valores arbitrários em geral (`w-[237px]`, `p-[13px]`) são achado.

### 4. Os quatro estados

Vazio, a carregar, erro e cheio, no mesmo ficheiro, com cabeçalho a separá-los.
Falta algum → **o mockup não está pronto**, e é o achado mais grave que existe.

Verificar também que o estado "cheio" é mesmo cheio: nomes longos, valores no
limite, listas com muitas linhas. Um "cheio" com três linhas curtas não testou
nada.

### 5. Componentes

- Cada bloco marcado em comentário com o que vai ser na implementação
- Existe a **lista explícita de componentes novos** no fim do ficheiro
- Cada componente novo tem issue aberto com `área: design`:

```bash
gh issue list --label "área: design" --state open --limit 30
```

Componente novo sem issue → criar issue, ou dizer que falta. Componente que já
existe em `resources/views/components/` mas foi redesenhado do zero → achado.

### 6. Regras de composição

- Uma única ação primária por ecrã (`bg-brand-600` em botão, uma vez)
- Sem sombras fora de elementos flutuantes
- Texto longo trunca numa linha, com o valor completo em `title`
- Números alinhados à direita em tabelas, com `tabular-nums`

### 7. Acessibilidade — mínimos

- Ícone sozinho como ação leva `aria-label`
- Informação nunca só por cor: badge de estado tem sempre o rótulo em texto
- Gráficos e imagens geradas têm `role="img"` e `aria-label` com o resumo
- Campo em erro tem `aria-invalid` e `aria-describedby` a apontar para a mensagem

## O relatório

Uma linha por achado, agrupada por gravidade. Sem elogios, sem rodeios:

```
BLOQUEIA
- linha 142: falta o estado "a carregar"
- linha 88: bg-sky-500 não existe no sistema (usar brand-600)

CORRIGIR ANTES DE IMPLEMENTAR
- linha 210: rounded-lg em vez de rounded-card
- x-copy-field aparece no mockup e não tem issue

REGISTAR NO DESIGN.MD OU MUDAR
- linha 55: faixa de aviso com bg-amber-50, fundo de cor que o sistema não prevê

PASSA
- tokens iguais aos do app.css
- quatro estados presentes
```

Terminar com um veredicto de uma linha: **pronto a implementar** ou **volta à
sessão de design**, e porquê.

## Regras

- **Não corrigir o mockup em silêncio.** Reportar; corrigir só se for pedido.
- Um achado que é decisão nova de design (um fundo de cor que não existe, um
  componente que faz sentido) não é erro: é entrada para o `docs/DESIGN.md`.
  Distinguir as duas coisas — confundi-las mata boas ideias ou deixa entrar más.
- Se o mockup passar tudo, dizer isso em duas linhas e parar. Não inventar
  achados para justificar a passagem.
