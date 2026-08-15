---
name: desenhar-ecra
description: Desenha um ecrã como mockup HTML+Tailwind, dentro do sistema de design do projeto. Usar na fase de design, antes de implementar em Blade.
argument-hint: [nome-do-ecra]
disable-model-invocation: true
---

Desenha um ecrã como mockup estático. O output é um ficheiro HTML que se abre no browser, não código de produção.

## Antes de desenhar

Ler, por esta ordem:

1. `docs/DESIGN.md` — **as restrições. Não são sugestões.**
2. `docs/PRODUTO.md` — para quem é o produto
3. O fluxo relevante, se existir, e o issue associado
4. `resources/views/components/` — os componentes que já existem

Se o `docs/DESIGN.md` não existir, **para e diz que a Fase D do playbook (sistema visual) não foi feita.** Desenhar ecrãs sem sistema definido produz um conjunto incoerente, e o custo aparece só ao décimo ecrã.

## Perguntar antes de começar

- Quem chega a este ecrã, vindo de onde, e o que quer fazer aqui?
- Qual é a **única** ação primária?
- Que dados são mostrados, e quais são opcionais?

## Desenhar

Gerar `docs/mockups/<nome>.html`:

- HTML único e autónomo, com **Tailwind 4** via build de browser:

```html
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style type="text/tailwindcss">
  @theme {
    --color-brand-600: #4f46e5;
    --font-sans: "Inter", sans-serif;
    --radius-card: 0.75rem;
  }
</style>
```

Copiar o bloco `@theme` do `resources/css/app.css` do projeto, para que o mockup use exatamente os mesmos tokens que a aplicação. **Não usar `https://cdn.tailwindcss.com` nem `tailwind.config` inline** — é sintaxe do Tailwind 3 e produziria classes que não existem na aplicação.

- Dados de exemplo realistas — **nomes e valores plausíveis, nunca "Lorem ipsum" nem "Teste 1"**. Dados falsos irrealistas escondem problemas de layout que só aparecem com conteúdo real.
- Marcar os componentes que correspondem a componentes Blade existentes, em comentário HTML

**Os quatro estados, obrigatoriamente**, um a seguir ao outro no mesmo ficheiro, com um cabeçalho a separá-los:

1. Vazio (primeira utilização)
2. A carregar (skeleton)
3. Erro
4. Cheio (com muitos dados — testar onde o layout parte)

## Depois

- Dizer que ficheiro foi criado e sugerir abri-lo no browser
- Listar as decisões de composição que tomaste e porquê
- Assinalar qualquer sítio onde tiveste de sair do sistema de design, e porquê — isso é candidato a componente novo ou a alteração do `DESIGN.md`

## Não fazer

- Não introduzir cores, tamanhos de fonte ou raios fora do sistema
- Não escrever Blade, Livewire ou componentes reais nesta skill — isto é design
- Não usar bibliotecas de componentes externas
