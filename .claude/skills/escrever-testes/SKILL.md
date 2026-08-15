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
