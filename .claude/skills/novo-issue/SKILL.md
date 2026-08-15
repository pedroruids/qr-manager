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

4. **Verificar se precisa de design.** Se envolve interface que ainda não existe, o issue depende de um mockup — assinalar isso e sugerir `/desenhar-ecra` primeiro.

5. **Apresentar o rascunho** e esperar confirmação.

6. **Criar o issue:**

```bash
gh issue create --title "<título>" --label "<tipo>" --body "<corpo>"
```

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
<ficheiros/módulos prováveis, entidades envolvidas, dependências de outros issues>
```

## Regras

- **Máximo 5 critérios de aceitação.** Mais do que isso significa que o issue devia estar dividido.
- Cada critério verificável por quem não participou na conversa. "A experiência deve ser fluida" não serve; "a listagem carrega em menos de 2s com 1000 registos" serve.
- Escrever "Fora de âmbito" mesmo quando parece óbvio — é o que impede o âmbito de crescer durante a implementação. Em produto próprio, onde não há cliente a fechar o âmbito, isto importa mais, não menos.
- **Se não conseguires escrever critérios verificáveis, o issue não está pronto.** Diz isso em vez de o criar.

## Nota

Os critérios de aceitação são a fonte a partir da qual os testes vão ser escritos (skill `escrever-testes`). Critérios vagos produzem testes que não verificam nada.
