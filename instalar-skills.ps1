# Instalador das skills de trabalho — gerado automaticamente
# Corre:  powershell -ExecutionPolicy Bypass -File .\instalar-skills.ps1

$ErrorActionPreference = 'Stop'
$dest = Join-Path $env:USERPROFILE '.claude\skills'
New-Item -ItemType Directory -Force -Path $dest | Out-Null
Write-Host "Destino: $dest" -ForegroundColor Cyan
Write-Host ""

# ---------- /escrever-testes ----------
$c = @'
---
name: escrever-testes
description: Escreve testes Pest a partir dos critérios de aceitação de um issue, deliberadamente sem olhar para a implementação. Usar em sessão separada da que implementou o código.
argument-hint: [numero-do-issue]
disable-model-invocation: true
---

Escreve testes que verificam o **requisito**, não a implementação.

## Regra central

**Não leias o código da implementação antes de escrever os testes.**

Se o fizeres, escreves testes que passam no código que existe — e não testes que verificam o que era pedido. Passam sempre e não provam nada. É o modo de falha mais perigoso deste processo, porque não dá sinal: o CI fica verde, a cobertura sobe, e a rede de segurança é uma ilusão.

Idealmente esta skill corre numa sessão que não implementou nada.

## Passos

1. **Ler o issue.** Se `$ARGUMENTS` trouxer um número, usa-o; caso contrário pergunta qual é o issue.

```bash
gh issue view <numero>
```

Extrair os critérios de aceitação.

2. **Ler o contexto do projeto** — `CLAUDE.md` e `docs/ARCHITECTURE.md`. Se o Laravel Boost estiver disponível, usar as suas ferramentas para ler o schema e os modelos Eloquent: precisas da estrutura de dados, não da lógica das classes.

3. **Ler apenas assinaturas públicas** — rotas, métodos de controlador, nomes de modelos. O suficiente para escrever chamadas válidas, **não** a lógica interna.

4. **Escrever os testes em Pest.** Para cada critério de aceitação:
   - o caso nominal
   - pelo menos um caso limite (vazio, nulo, zero, máximo, sem permissão)
   - o caso de erro, quando aplicável

Preferir testes de funcionalidade (HTTP, através das rotas reais) a testes unitários de classes internas — verificam o comportamento que o critério descreve, e sobrevivem a refactors.

```php
it('não deixa um utilizador ver projetos de outro', function () {
    $outro = User::factory()->has(Project::factory())->create();

    $this->actingAs(User::factory()->create())
        ->get("/projects/{$outro->projects->first()->id}")
        ->assertForbidden();
});
```

5. **Correr:**

```bash
./vendor/bin/pest --filter=<nome>
```

Interpretar as falhas:
- Falha porque a funcionalidade não existe → esperado, se estás a escrever antes
- Falha porque a implementação não cumpre o critério → **encontraste um bug real**
- Passa tudo à primeira → suspeita. Verifica se o teste testa mesmo alguma coisa

6. **Reportar** que critérios ficaram cobertos, quais não foi possível cobrir e porquê.

## Não fazer

- Não ajustar o teste para passar. Se o teste está certo e falha, o problema é do código.
- Não testar detalhes internos (métodos privados, estrutura de classes) — torna os testes frágeis a qualquer refactor.
- Não perseguir percentagem de cobertura. Perseguir cobertura dos critérios.

'@
$d = Join-Path $dest 'escrever-testes'
New-Item -ItemType Directory -Force -Path $d | Out-Null
[System.IO.File]::WriteAllText((Join-Path $d 'SKILL.md'), $c, (New-Object System.Text.UTF8Encoding $false))
Write-Host '  instalada  /escrever-testes' -ForegroundColor Green

# ---------- /novo-issue ----------
$c = @'
---
name: novo-issue
description: Transforma uma ideia ou problema num issue do GitHub estruturado, com critérios de aceitação verificáveis. Usar antes de começar a escrever código para algo novo.
disable-model-invocation: true
---

Cria um issue no GitHub pronto a implementar. Não implementes nada nesta skill.

## Passos

1. **Perguntar até perceber.** Uma pergunta de cada vez. Precisas de saber:
   - Que problema resolve, e para quem — não que funcionalidade se vai construir
   - Como se sabe objetivamente que está feito
   - O que fica deliberadamente de fora

   Se a resposta for vaga, insiste. Ler `docs/PRODUTO.md` para enquadramento.

2. **Verificar o tamanho.** Mais de um dia de trabalho → propor divisão antes de avançar.

3. **Verificar duplicação:**

```bash
gh issue list --search "<palavras-chave>" --state all --limit 20
```

4. **Verificar se precisa de design.** Se envolve interface que ainda não existe, o issue depende de um mockup — assinalar isso e sugerir `/preparar-design` primeiro.

5. **Apresentar o rascunho** e esperar confirmação.

6. **Criar o issue:**

```bash
gh issue create --title "<título>" --label "<tipo>" --body "<corpo>"
```

Se já houver ondas planeadas (`docs/ONDAS.md` e milestones no repositório), passa tudo **no mesmo comando** — o `gh issue create` aceita a milestone e as dependências de uma vez, e evita ter de descobrir o número do issue a seguir (o `create` só imprime o URL):

```bash
gh issue create --title "<título>" --label "<tipo>" --body "<corpo>" \
  --milestone "Onda 2: <nome>" \
  --blocked-by <m>
```

`--blocked-by <m>` lê-se "este issue está bloqueado por `<m>`" — `<m>` vem primeiro. A inversa é `--blocking`. Estas duas flags exigem `gh` v2.94.0 ou superior; o `--milestone` funciona em qualquer versão recente.

Se não for evidente em que onda entra, **pergunta** em vez de adivinhar — meter trabalho na onda errada desfaz o agrupamento de validação, que é a razão de as ondas existirem.

Corpo:

```markdown
## Problema
<a dor concreta, em linguagem de utilizador — não a solução>

## O que vai ser feito
<o comportamento desejado>

## Feito quando
- [ ] <critério verificável>
- [ ] <critério verificável>

## Fora de âmbito
<o que este issue explicitamente não faz>

## Design
<link para docs/mockups/<ecra>.html, ou "não aplicável">

## Contexto técnico
<entidades envolvidas, decisões já tomadas>

## Ficheiros prováveis
<lista dos ficheiros/pastas que este trabalho vai tocar>

## Depende de
<#N, ou "nada">
```

**A secção "Ficheiros prováveis" não é decorativa.** É o que permite decidir se dois issues podem correr em paralelo — só podem se os conjuntos forem disjuntos. Um issue sem ela obriga a abrir o código para descobrir, ou a sequenciar por precaução.

## Regras

- **Máximo 5 critérios de aceitação.** Mais do que isso significa que o issue devia estar dividido.
- Cada critério verificável por quem não participou na conversa. "A experiência deve ser fluida" não serve; "a listagem carrega em menos de 2s com 1000 registos" serve.
- Escrever "Fora de âmbito" mesmo quando parece óbvio — é o que impede o âmbito de crescer durante a implementação. Em produto próprio, onde não há cliente a fechar o âmbito, isto importa mais, não menos.
- **Se não conseguires escrever critérios verificáveis, o issue não está pronto.** Diz isso em vez de o criar.

## Nota

Os critérios de aceitação são a fonte a partir da qual os testes vão ser escritos (skill `escrever-testes`). Critérios vagos produzem testes que não verificam nada.

'@
$d = Join-Path $dest 'novo-issue'
New-Item -ItemType Directory -Force -Path $d | Out-Null
[System.IO.File]::WriteAllText((Join-Path $d 'SKILL.md'), $c, (New-Object System.Text.UTF8Encoding $false))
Write-Host '  instalada  /novo-issue' -ForegroundColor Green

# ---------- /planear-ondas ----------
$c = @'
---
name: planear-ondas
description: Organiza os issues abertos em ondas de trabalho, com portões de validação, análise de paralelismo e agrupamento por sessão. Usar depois de abrir o backlog, ou quando o plano deixou de refletir a realidade.
disable-model-invocation: true
---

Organizas o backlog em **ondas**. Não implementas nada.

## O que é uma onda

**Uma onda é tudo o que avança sem precisar de uma decisão nova do utilizador.** Termina num portão de validação.

Não é um sprint. Um sprint é uma caixa de tempo — duas semanas, aconteça o que acontecer. Uma onda é uma caixa de *autonomia*: acaba quando o trabalho volta a precisar dos olhos de alguém. Num projeto com um só aprovador, a fronteira útil é a pessoa, não o calendário.

Consequência prática: uma onda pode durar dois dias ou duas semanas. O que a define é o portão.

## Ler primeiro

```bash
gh issue list --state open --limit 100 \
  --json number,title,state,labels,body,milestone,blockedBy,blocking
```

Inclui sempre `blockedBy` e `blocking` — se replaneares sem os ler, ficas cego às dependências que já existem e voltas a propor o que já está ligado.

Mais `docs/PRODUTO.md` (fluxos e entidades), `docs/ARCHITECTURE.md` se existir, e `docs/DESIGN.md`.

## Como ordenar — três critérios, por esta ordem

### 1. Custo de reversão

**O que é caro de desfazer vai primeiro.** O modelo de dados, o esquema de URLs públicas, o modelo de autenticação e permissões.

Um erro no schema descoberto na onda 3 obriga a refazer tudo o que assenta nele. O mesmo erro descoberto na onda 1 custa uma migração. Este critério ganha aos outros dois quando houver conflito.

### 2. Dependência técnica

O que tem de existir antes. Expressa-se nativamente:

```bash
gh issue edit <n> --add-blocked-by <m>
```

**Atenção à direção:** isto lê-se "`<n>` está bloqueado por `<m>`" — ou seja, **`<m>` vem primeiro**. A inversa é `--add-blocking`. Trocar as duas produz um grafo de ondas ao contrário, sem erro visível em lado nenhum.

Aceita número (`200`), `#200` ou URL completo, e vários de uma vez. **Não** aceita `owner/repo#200` — para outro repositório, usa o URL.

Limite: 50 issues por tipo de relação. Se um issue está bloqueado por três outros, provavelmente está mal dimensionado — ou é um épico disfarçado.

### 3. Momento de validação

**Agrupa na mesma onda o que precisa dos mesmos olhos.** Se cinco ecrãs precisam de revisão visual, revê-los de uma vez custa ao utilizador uma sessão de dez minutos; espalhados por cinco ondas, custa cinco interrupções e cinco recontextualizações.

Este é o critério que mais tempo poupa a quem aprova, e o que mais gente ignora.

## Paralelismo

**Dois issues só correm em paralelo se os conjuntos de ficheiros forem disjuntos.**

Cada issue tem uma secção **"Ficheiros prováveis"** — é para isto que ela existe. Se faltar num issue, abre o código para a preencher antes de decidir, ou sequencia por precaução. Se houver interseção, sequencia. Sem exceções — dois agentes no mesmo ficheiro dão conflito de merge no melhor caso, e decisões arquiteturais incompatíveis no pior.

Mantém-se: um agente por issue, um issue por branch, máximo duas branches em curso por pessoa.

> **O paralelismo é quase sempre falsa economia num projeto a solo.** O gargalo não é o tempo de máquina — é a capacidade de revisão de quem aprova. Trabalho paralelo não acelera nada; só cria uma fila maior à espera da mesma pessoa. Paraleliza apenas quando as peças genuinamente não precisam de ser vistas em conjunto.

## Custo de tokens — três regras

**1. Issues do mesmo módulo vão na mesma sessão, não em sessões paralelas.**

Cada sessão nova relê o projeto do zero. Três sessões sobre o mesmo módulo pagam três vezes o mesmo contexto. É contra-intuitivo: **paralelizar pode custar mais tokens do que sequenciar**, mesmo poupando tempo de relógio.

Agrupa por proximidade no código, não por semelhança de tema.

**2. Validar cedo é a maior poupança que existe.**

Retrabalho é o gasto mais caro de todos — reimplementar custa mais do que implementar, porque paga o contexto outra vez e ainda tem de desfazer. Um portão bem posto poupa mais tokens do que qualquer otimização de sessão.

**3. Uma onda deve caber numa sessão de trabalho.**

Se não cabe, está grande demais — divide. Sessões que crescem demais degradam a qualidade do output e obrigam a compactar, o que perde contexto e provoca erros que depois custam a corrigir.

## Produzir

Para cada onda:

```markdown
## Onda N: <nome curto>

**Fica possível no fim:** <o que o produto passa a fazer, em uma frase>

**Issues:** #a, #b, #c

**Ordem:** #a primeiro (bloqueia os outros) · depois #b e #c em paralelo
**Título da milestone:** `Onda N: <nome curto>` — dois pontos, para a correspondência exata não falhar
**Porquê paralelo:** #b toca em `app/Http/Controllers/`, #c em `resources/views/` — disjuntos

**Sessões sugeridas:** 2
  - Sessão 1: #a, #b (mesmo módulo, contexto partilhado)
  - Sessão 2: #c

**Portão de validação:**
  O que revês: <concreto — "o schema das 4 tabelas e as relações">
  Como: <"ler as migrações e correr php artisan migrate:status">
  Tempo: <~10 min>
  Se estiver errado: <o que se perde — "uma migração" vs "três ondas de trabalho">
```

Termina com os riscos: que onda está mais dependente de um pressuposto por confirmar, e o que acontece se esse pressuposto cair.

## Gravar

Depois de o utilizador aprovar o plano:

**1. Criar as milestones** — uma por onda:

```bash
gh api repos/{owner}/{repo}/milestones \
  -f title="Onda 1: Fundação de dados" \
  -f description="<o que fica possível no fim>"
```

O `{owner}/{repo}` é substituído pelo `gh` — escreve-o com chavetas, à letra. Não precisa de `-X POST`: passar parâmetros já implica POST.

**Usa dois pontos no título, não travessão.** O `gh issue edit --milestone` procura o título por correspondência exata; se numa invocação escreveres `—` e noutra `-`, falha com `not found` e a causa é invisível.

**2. Atribuir os issues** — vários de uma vez:

```bash
gh issue edit 3 5 7 --milestone "Onda 1: Fundação de dados"
```

A milestone tem de existir antes. O `--milestone` funciona em qualquer versão recente do `gh`.

**3. Ligar as dependências:**

```bash
gh issue edit <n> --add-blocked-by <m>
```

Isto exige `gh` **v2.94.0 ou superior** e permissão de *triage* no repositório. Confirma com `gh --version`.

Se o `gh` for anterior, **não caias para "registar no corpo do issue"** — a dependência real continua a ser criável pela API:

```bash
ID=$(gh api repos/{owner}/{repo}/issues/<m> --jq .id)
gh api -X POST repos/{owner}/{repo}/issues/<n>/dependencies/blocked_by -F issue_id=$ID
```

Repara que a API usa o **id interno** do issue, não o número.

**4. Escrever `docs/ONDAS.md`** com o plano completo — é a versão legível, que sobrevive a mudanças no GitHub e explica o *porquê* da ordem, que as milestones não guardam.

## Regras

- **Não inventes issues.** Organizas o que existe. Se faltar trabalho óbvio, assinala e sugere `/novo-issue`.
- **Não faças mais de 4 ou 5 ondas.** Mais do que isso é planeamento a fingir: as ondas do fim vão mudar antes de lá chegares.
- **A primeira onda é a mais importante e a mais curta.** É a que valida os pressupostos caros.
- **Se um issue não cabe em nenhuma onda sem bloquear tudo**, o problema é o issue. Divide-o.

'@
$d = Join-Path $dest 'planear-ondas'
New-Item -ItemType Directory -Force -Path $d | Out-Null
[System.IO.File]::WriteAllText((Join-Path $d 'SKILL.md'), $c, (New-Object System.Text.UTF8Encoding $false))
Write-Host '  instalada  /planear-ondas' -ForegroundColor Green

# ---------- /preparar-design ----------
$c = @'
---
name: preparar-design
description: Monta o briefing completo de um ecrã para levar para a sessão de design — sistema visual, tokens, componentes existentes e critérios de aceitação. Usar antes de desenhar um ecrã novo.
argument-hint: [nome-do-ecra]
disable-model-invocation: true
---

Montas o briefing que vai ser colado numa sessão de design separada. Não desenhas nada aqui.

O nome do ecrã vem em `$ARGUMENTS`. Se vier vazio, pergunta qual é.

## Antes de montar

Verifica que o sistema visual existe:

```bash
test -f docs/DESIGN.md && echo ok
grep -c '<\.\.\.>' docs/DESIGN.md || true
```

Se o `docs/DESIGN.md` não existir, ou ainda tiver marcadores `<...>` por
substituir, **para**. A Fase D não está feita, e desenhar ecrãs sem sistema
definido produz um conjunto incoerente — o custo só aparece ao décimo ecrã,
quando corrigir já é refazer.

## Reunir

1. **O sistema:** `docs/DESIGN.md`, inteiro.

2. **Os tokens reais**, à letra:

```bash
sed -n '/@theme/,/^}/p' resources/css/app.css
```

3. **Os componentes que já existem:**

```bash
ls resources/views/components/ 2>/dev/null
```

Mais os componentes da biblioteca base em uso (Flux UI, se for o caso) que o
`docs/DESIGN.md` liste.

4. **O issue do ecrã**, com os critérios de aceitação:

```bash
gh issue list --search "<nome-do-ecra>" --state open
gh issue view <numero>
```

5. **Os fluxos onde o ecrã aparece**, de `docs/PRODUTO.md` — quem chega aqui,
vindo de onde, e o que quer fazer.

## Produzir

Escreve um bloco único, pronto a colar, com esta estrutura:

```markdown
# Briefing de design — <ecrã>

## Contexto
<quem usa o produto, e o que quer fazer neste ecrã — 2 a 3 frases>

## O que este ecrã tem de permitir
<os critérios de aceitação do issue #N>

## Sistema de design — restrições, não sugestões
<docs/DESIGN.md na íntegra>

## Tokens — copiar à letra para o @theme do mockup
```css
<o bloco @theme do app.css>
```

## Componentes existentes
<lista, com nota sobre quais são da biblioteca base e quais são próprios>

## O que preciso de volta
- Ficheiro HTML único e autónomo
- Tailwind 4 via `<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>`
- O bloco `@theme` acima, copiado à letra
- **Os quatro estados** separados por cabeçalho: vazio, a carregar, erro, cheio
- Dados de exemplo plausíveis — nunca "Lorem ipsum" nem "Teste 1"
- Comentários HTML a marcar que componente cada bloco vai ser na implementação
- Uma lista no fim: **componentes novos que este ecrã exige**
```

## Terminar

Diz ao utilizador:

- Que o briefing está pronto a colar na sessão de design
- Que o ficheiro devolvido vai para `docs/mockups/<ecra>.html`
- Que ao voltar deve correr `/validar-design <ecra>` **antes** de implementar

Se a biblioteca base não renderizar em HTML solto — o Flux UI, por exemplo, só
existe em Blade — inclui isso no briefing explicitamente: o mockup **aproxima**
os componentes com HTML e Tailwind, e marca em comentário o que cada bloco vai
ser. Sem essas marcas, quem implementa reconstrói a decisão a partir do aspeto,
e é aí que o implementado começa a afastar-se do desenhado.

'@
$d = Join-Path $dest 'preparar-design'
New-Item -ItemType Directory -Force -Path $d | Out-Null
[System.IO.File]::WriteAllText((Join-Path $d 'SKILL.md'), $c, (New-Object System.Text.UTF8Encoding $false))
Write-Host '  instalada  /preparar-design' -ForegroundColor Green

# ---------- /validar-design ----------
$c = @'
---
name: validar-design
description: Valida um mockup recebido da sessão de design contra o sistema visual do projeto, e prepara as notas de implementação. Usar ao trazer um ecrã de volta, antes de o implementar.
argument-hint: [nome-do-ecra]
disable-model-invocation: true
---

Recebes um mockup desenhado noutra sessão e verificas se ele cabe no sistema. **És porteiro, não autor** — não redesenhas o ecrã; assinalas o que está fora e devolves para correção.

O nome do ecrã vem em `$ARGUMENTS`. Se vier vazio, pergunta qual é.

## Ler primeiro

1. `docs/DESIGN.md` — as restrições
2. O bloco `@theme` de `resources/css/app.css` — os tokens reais
3. `ls resources/views/components/` — o que existe
4. O mockup: `docs/mockups/<ecra>.html`
5. O issue do ecrã, para os critérios de aceitação

Se o mockup não estiver em `docs/mockups/`, pede o ficheiro e grava-o lá antes de continuar.

## Verificar

### 1. Tokens

Extrai o `@theme` do mockup e compara com o do `app.css`.

```bash
sed -n '/@theme/,/}/p' docs/mockups/<ecra>.html
sed -n '/@theme/,/^}/p' resources/css/app.css
```

**Bloqueia** se houver valores diferentes. Um mockup com tokens próprios produz
um ecrã que parece certo isolado e destoa no conjunto.

Procura também cores e medidas escritas à mão que deviam ser tokens:
`#hex` soltos, `text-[13px]`, `rounded-[6px]`.

### 2. Componentes

Lista os blocos do mockup e mapeia cada um para:

- um componente da biblioteca base que já existe
- um componente próprio que já existe
- **um componente novo** → vira issue com a label `área: design`

Se o mockup não trouxer comentários a marcar isto, fá-lo tu e **assinala a
omissão** — sem essas marcas, quem implementa reconstrói a decisão a partir do
aspeto.

### 3. Os quatro estados

Confirma que existem, e que não são decorativos:

- **Vazio** — explica o que aparecerá aqui e dá a ação para começar?
- **A carregar** — skeleton quando a estrutura é conhecida, não spinner?
- **Erro** — diz o que falhou em linguagem humana e o que fazer a seguir?
- **Cheio** — mostra onde entra paginação ou scroll? O layout aguenta o texto mais longo?

**Bloqueia** se faltar algum. Um ecrã sem estado vazio definido não está desenhado.

### 4. Regras de composição

Contra o `docs/DESIGN.md`: uma única ação primária, alinhamentos, espaçamento
vertical constante, sombras só em elementos flutuantes, truncagem de texto longo.

### 5. Critérios de aceitação

O ecrã permite tudo o que o issue exige? Faz alguma coisa que estava declarada
fora de âmbito?

### 6. Acessibilidade

Contraste, foco visível, `aria-label` em ações só com ícone, e informação nunca
transmitida apenas por cor.

## Devolver

```
## <ecrã> — validação

**Veredicto:** aprovado | aprovado com correções | devolver

### Bloqueia
<ficheiro:linha — o que está errado, e a consequência>

### Devia corrigir
<...>

### Componentes novos necessários
| Componente | Onde aparece | Porquê não chega o que existe |

### Notas de implementação
<mapa bloco → componente real, e o que exige lógica de servidor>
```

## Depois de aprovado

- Abre os issues dos componentes novos, com `área: design`
- Confirma que o mockup está commitado em `docs/mockups/`
- Se a validação revelou uma regra que faltava no sistema — uma decisão que o
  mockup teve de tomar e o `DESIGN.md` não cobria — **acrescenta-a ao
  `DESIGN.md`, com data e razão, no registo de alterações.** É assim que o
  sistema cresce: a partir de casos reais, não de previsão.

## Não fazer

- Não redesenhes o ecrã. Se estiver errado, devolve com o motivo.
- Não aceites tokens ou componentes fora do sistema por serem "melhores" neste
  ecrã. Se forem mesmo melhores, muda-se o sistema primeiro — decisão explícita,
  registada, aplicada a todos os ecrãs.
- Não inventes problemas para parecer útil. Uma validação que encontra sempre
  alguma coisa deixa de ser levada a sério.

'@
$d = Join-Path $dest 'validar-design'
New-Item -ItemType Directory -Force -Path $d | Out-Null
[System.IO.File]::WriteAllText((Join-Path $d 'SKILL.md'), $c, (New-Object System.Text.UTF8Encoding $false))
Write-Host '  instalada  /validar-design' -ForegroundColor Green


# ---------- limpar cópias antigas no projeto ----------
$proj = Join-Path $PSScriptRoot '.claude\skills'
if (Test-Path $proj) {
    foreach ($old in @('novo-issue','escrever-testes','desenhar-ecra','preparar-design','validar-design','planear-ondas')) {
        $t = Join-Path $proj $old
        if (Test-Path $t) {
            Remove-Item $t -Recurse -Force
            Write-Host "  removida do projeto (usa-se agora a de utilizador)  /$old" -ForegroundColor DarkYellow
        }
    }
}

Write-Host ""
Write-Host "Skills em $dest :" -ForegroundColor Cyan
Get-ChildItem $dest | Select-Object -ExpandProperty Name | ForEach-Object { Write-Host "  /$_" }
Write-Host ""
Write-Host "Reinicia o Claude Code para as carregar." -ForegroundColor Yellow
