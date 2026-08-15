# qr-manager

Códigos QR dinâmicos. O código impresso aponta para um slug curto deste sistema;
o slug redirecciona para o destino, e o destino é editável sem reimprimir nada.

Para o gestor de marketing de uma PME que imprime flyers: a campanha muda, o
papel fica.

- Export em **PNG** e **SVG**
- Contagem de leituras por dia
- **API** para criar e gerir códigos a partir de ferramentas externas

## Stack

Laravel 13 · Livewire 4 · Flux UI 2 · Tailwind 4 · Pest 5 · SQLite · PHP 8.4

## Arrancar

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

## Verificar

```bash
composer run test          # pint + phpstan + pest
./vendor/bin/pest          # só os testes
```

## Documentação

- `docs/PRODUTO.md` — o problema, as fronteiras, os fluxos
- `docs/DESIGN.md` — sistema visual
- `docs/ARCHITECTURE.md` — modelo de dados e módulos
- `docs/DECISIONS.md` — porquê das decisões estruturantes
- `PROGRESSO.md` — o que está feito e o que falta
