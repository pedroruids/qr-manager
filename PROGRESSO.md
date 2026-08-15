# Progresso

Estado do projeto. Actualizar à medida que as fases fecham.

## v1 — fechada a 2026-08-15 ✅

Os dezasseis issues da primeira versão estão fechados. `main` verde, 495 testes,
1979 asserções.

O que existe, ponta a ponta: um utilizador autenticado cria um código, leva o
PNG ou o SVG para a gráfica, muda o destino quando a campanha muda, e vê quantas
leituras teve por dia. Quem aponta a câmara ao papel é redireccionado sem passar
por sessão nenhuma. Uma ferramenta externa faz o mesmo por token.

**Falta antes de isto servir a alguém real:** deploy e domínio curto. Ver abaixo.

## Fase A — Produto e fronteiras ✅

- Problema, público e hipótese definidos
- Seis fronteiras do "o que NÃO é" escritas
- Primeira versão utilizável delimitada, com a API dentro do âmbito
- Registado em `docs/PRODUTO.md`

## Fase B — Fluxos, ecrãs e entidades ✅

- Seis fluxos principais, cada um com o que pode correr mal
- Sete ecrãs e quatro entidades extraídos e confirmados
- Registado em `docs/PRODUTO.md`

## Fase C — Fundação técnica ✅

- Laravel 13 + Livewire 4 + Flux UI 2, SQLite, PHP 8.4
- Pest 5 a correr (`./vendor/bin/pest`), com teste de arranque
- Pint e Larastan (nível 7) a passar
- Laravel Boost instalado; `CLAUDE.md` gerado com as guidelines do projeto
- CI no GitHub Actions verde: Pint, Larastan, build de assets, Pest
- Repositório `pedroruids/qr-manager` público, `main` protegida por ruleset
  (PR obrigatório, CI obrigatório, sem force push, sem apagar a branch)
- Hooks do Claude activos (`.claude/settings.json`)
- Doze issues da primeira versão abertos
- Sistema visual decidido e tokens no `resources/css/app.css`:
  Inter, indigo `#4F46E5`, zinc, raio `0.5rem`, densidade compacta

## Fase D — Sistema visual aplicado ✅

- `x-copy-field` — issue #17. Mostra uma versão curta e copia o valor completo
- `x-empty-state` — issue #18. Variante de página e variante compacta
- `x-qr-preview` — issue #19. Componente de classe, pede o SVG ao gerador
- `x-bar-chart` — issue #20. Sem JavaScript e sem dependências novas

⬜ **Por fazer:** a página `/design` que mostra todos os componentes e estados num
sítio só. Não bloqueou nada da v1, mas é o que evita que o sétimo ecrã reinvente
o que o terceiro já resolveu.

## Fase E — Ecrãs ✅

Todos com mockup em `docs/mockups/` antes de implementar, como o protocolo do
`docs/DESIGN.md` manda.

1. `lista-qrs` — issue #6
2. `criar-qr` — issue #7
3. `detalhe-qr` — issues #9 e #11
4. `editar-qr` — issue #10
5. `tokens-api` — issue #12
6. `erro-redirect` — issue #5

O estado de erro do mockup do redirect (a página 500) ficou por implementar: era
a página de erro global da aplicação com texto escrito para o redirect. Fica
para quando houver um issue que a peça.

## Fase F — Modelo de dados e módulos ✅

- `QrCode` — issue #2. Slug imutável, garantido no modelo e não no formulário
- `Scan` — issue #3. Só `created_at`, mais a `data_local` que o issue #11 trouxe
- Redirect público — issues #4 e #5. Fora do grupo `web`, sem sessão
- Geração PNG/SVG — issue #8. Nível de correcção Q, PNG desenhado em GD
- Leituras por dia — issue #11. Agregação por `data_local`, uma consulta
- API — issues #12 e #13. Sanctum, limite por token e por IP

O porquê de cada uma destas está em `docs/DECISIONS.md`.

## Por configurar

- **Deploy** — adiado, ver `docs/DECISIONS.md`. Provavelmente Ploi.
- **Domínio curto do redirect** — decisão de produto por tomar antes de existirem
  QRs reais; o slug vai ser lido em papel, carácter a carácter. É o que falta
  para o `APP_URL` deixar de ser `localhost` e os códigos gerados servirem para
  imprimir.

## Fora de âmbito, de propósito

Registado aqui para não voltar a ser discutido a cada ideia nova. As fronteiras
completas estão em `docs/PRODUTO.md`.

- Analytics além de leituras por dia: país, dispositivo, referrer, exportação
- Personalização do código: logótipo, cores, molduras
- Histórico de destinos anteriores, agendamento da mudança de destino
- Scopes por token, expiração automática, rotação
- Webhooks, versionamento da API, documentação OpenAPI
- Equipas, papéis, partilha de códigos entre utilizadores
