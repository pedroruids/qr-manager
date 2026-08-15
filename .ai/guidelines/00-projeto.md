# Contexto do projeto

<!--
Este ficheiro é TEU e vai para o git.

O Laravel Boost inclui automaticamente tudo o que estiver em `.ai/guidelines/`
no CLAUDE.md que gera. Ou seja: escreves aqui, o Boost regenera o CLAUDE.md, e
o teu conteúdo continua lá. Nunca editar o CLAUDE.md à mão.

Manter curto — isto é lido em todas as sessões. Detalhe longo vive em docs/ e
é referenciado, não colado.
-->

## O que é

**qr-manager** — códigos QR dinâmicos. O QR codifica um slug curto do próprio
sistema (`/{slug}`), que redirecciona para o destino; o destino é editável sem
reimprimir o código. Para o gestor de marketing de uma PME que imprime flyers.

Detalhe em `docs/PRODUTO.md`.

## Comandos

- Testes: `./vendor/bin/pest`
- Um teste: `./vendor/bin/pest --filter=<nome>`
- Formatação: `./vendor/bin/pint`
- Análise estática: `./vendor/bin/phpstan analyse`
- Suite completa (o que o CI corre): `composer run test`
- Dev: `composer run dev`

## Documentos de referência

Ler quando o trabalho os tocar — não estão colados aqui de propósito:

- `docs/PRODUTO.md` — o problema, as fronteiras, os fluxos
- `docs/DESIGN.md` — sistema visual (obrigatório antes de mexer em views)
- `docs/ARCHITECTURE.md` — modelo de dados, módulos, packages
- `docs/DECISIONS.md` — porquê das decisões estruturantes
- `REVIEW.md` — critérios de revisão

## Convenções deste projeto

- Stack: Laravel 13 + Livewire 4 + Flux UI 2 + Tailwind 4. Sem `tailwind.config.js` —
  os tokens vivem no `@theme` de `resources/css/app.css`.
- Componentes de interface em `resources/views/components/`.
- SQLite em desenvolvimento e no CI.
- Testes em Pest 5, estilo funcional (`it(...)`), em português.
- **O slug de um QR é imutável.** Uma vez criado, nunca muda nem é reutilizado —
  há papel impresso lá fora a apontar para ele.
- O redirect público (`GET /{slug}`) não exige autenticação, não abre sessão e
  não pode ficar mais lento por causa do registo da leitura.
- API sob `/api`, autenticada por token; os recursos devolvem JSON, nunca views.

## Nunca fazer

- Alterar migrações já aplicadas em produção — criar uma nova
- Introduzir cores, tamanhos ou raios fora do `docs/DESIGN.md`
- Escrever testes na mesma sessão em que se implementou o código
- Commitar diretamente na `main`
- Tornar o slug editável, seja na interface, seja na API
