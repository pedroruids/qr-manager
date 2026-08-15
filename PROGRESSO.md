# Progresso

Estado do arranque do projeto. Actualizar à medida que as fases fecham.

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

## Fase D — Sistema visual aplicado ⬜

- Ajustar os componentes base ao sistema decidido em `docs/DESIGN.md`
- Criar os componentes próprios previstos: `x-qr-preview`, `x-empty-state`,
  `x-copy-field`
- Criar a página `/design` que mostra todos os componentes e estados num sítio só

## Fase E — Ecrãs ⬜

Um de cada vez, com `/desenhar-ecra`, mockup em `docs/mockups/` antes de implementar:

1. `lista-qrs` — issue #6
2. `criar-qr` — issue #7
3. `detalhe-qr` — issues #9 e #11
4. `editar-qr` — issue #10
5. `tokens-api` — issue #12
6. `erro-redirect` — issue #5

## Fase F — Modelo de dados ⬜

Depois dos ecrãs, porque são os ecrãs que revelam que campos são mesmo precisos:

- `QrCode` — issue #2
- `Scan` — issue #3
- Redirect público — issue #4
- Geração PNG/SVG — issue #8
- API — issues #12 e #13

## Por configurar

- **Deploy** — adiado, ver `docs/DECISIONS.md`. Provavelmente Ploi.
- **Domínio curto do redirect** — decisão de produto por tomar antes de existirem
  QRs reais; o slug vai ser lido em papel.
