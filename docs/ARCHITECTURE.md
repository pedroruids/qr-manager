# Arquitetura

Escrito na Fase F, depois de os ecrãs existirem. Modelar em abstrato produz
tabelas elegantes que não suportam o que o ecrã precisa de mostrar.

## Modelo de dados
As entidades, os seus campos e as relações entre elas.
Diagrama ou lista — o que for mais legível.

| Entidade | Descrição | Relações |
|---|---|---|
| `User` | Do starter kit | tem muitos `QrCode` |
| `QrCode` | `user_id`, `nome`, `slug` (único, imutável), `destino`, `activo`, timestamps | pertence a `User`, tem muitas `Scan` |
| `Scan` | `qr_code_id`, `user_agent` (opcional), `created_at`, `data_local` | pertence a `QrCode` |
| `ApiToken` | O `personal_access_tokens` do Sanctum, mais `ultimos_caracteres` e `revogado_em` | pertence a `User` |

**Índices em `qr_codes`:** `slug` único (é por aí que o redirect público procura)
e `(user_id, created_at)` para a listagem, que ordena do mais recente para o mais
antigo.

**Índices em `scans`:** `(qr_code_id, created_at)` para o intervalo em tempo real,
e `(qr_code_id, data_local)` para a contagem por dia, que é por onde o gráfico do
detalhe consulta.

A `data_local` é o dia civil a que a leitura pertence no fuso do utilizador,
decidido no momento em que se grava. O `created_at` continua em UTC. Ver
`docs/DECISIONS.md`.

Apagar um `QrCode` apaga as suas leituras: a chave estrangeira tem
`cascadeOnDelete`. Um `Scan` órfão não responde a pergunta nenhuma.

## Módulos e fronteiras
Como o código está organizado, e o que não deve depender de quê.

**`app/Services/GeradorQrCode`** transforma um `QrCode` em ficheiro. É o único
sítio que sabe o que fica codificado — o URL curto do slug, nunca o destino — e
o único que conhece o nível de correcção de erros. Ecrãs e API pedem-lhe o SVG
ou o PNG; não montam nenhum dos dois.

**A API vive em `routes/api.php`**, autenticada por token do Sanctum e limitada
por token. Devolve sempre JSON — nunca uma view — através do `QrCodeResource`,
que já traz o URL curto e os endereços dos ficheiros montados: quem chama a API
está a automatizar e não deve ter de compor endereços nossos a partir do slug.
Todas as consultas partem de `$request->user()->qrCodes()`, nunca do modelo.

O download é o mesmo `DescarregarQrCodeController` da interface, apanhado por
duas rotas: os formatos, os limites de tamanho e o nome do ficheiro são os
mesmos, e não há razão para os escrever duas vezes.

**O redirect público é uma ilha.** `routes/publico.php` →
`RedirectPublicoController` → `errors/codigo-inactivo.blade.php`. Não passa pelo
grupo `web`, não abre sessão, não conhece o utilizador autenticado e não usa o
layout nem os componentes Flux da aplicação. A aplicação, do lado de dentro, não
depende dele: partilham o modelo `QrCode` e mais nada.

## Packages escolhidos
| Package | Para quê | Porque este |
|---|---|---|
| `bacon/bacon-qr-code` | Codificar o URL curto em matriz e em SVG | Já vinha instalado com o 2FA do starter kit; não se acrescentou nada para isto |
| `khanamiryan/qrcode-detector-decoder` (dev) | Descodificar o PNG gerado, nos testes | Um teste que confirma que o código é legível tem de o ler mesmo. Só em `require-dev`: nunca corre em produção |
| `laravel/sanctum` | Tokens de API | O Fortify não emite tokens. As linhas que se poupam são de segurança — geração, hash, comparação em tempo constante. Ver `docs/DECISIONS.md` |

## Integrações externas
Serviços, APIs, webhooks. O que acontece quando ficam indisponíveis.

## Fora do Laravel padrão
Tudo o que se afasta das convenções do framework, e a razão.
Se esta secção estiver vazia, ótimo.

- **`QrCode` recusa alterações ao `slug`** — o `updating` do modelo lança
  `LogicException` se o slug estiver sujo. Não é validação de formulário: é uma
  garantia do modelo, porque o slug está impresso em papel que já saiu daqui e
  qualquer caminho que o altere (interface, API, comando, tinker) destrói
  material físico.
- **O `destino` é validado no mutator**, não só no request. Mesma razão: um
  destino inválido gravado por uma via que esqueceu a validação transforma o
  redirect num erro para quem lê o código na rua.
- **Alfabeto do slug sem `0`, `O`, `1`, `l` e `i`** — quem lê um slug de um flyer
  lê-o carácter a carácter, e estes confundem-se.
- **Rotas fora do `bootstrap/app.php` habitual** — `routes/publico.php` é
  registado no `then` do `withRouting`, sem middleware, para que o apanha-tudo
  `{slug}` fique depois de todas as outras rotas e não abra sessão. Ver
  `docs/DECISIONS.md`.
- **A página do redirect falhado não usa Flux nem o layout da aplicação** — é
  HTML e Tailwind directos em `resources/views/errors/codigo-inactivo.blade.php`.
  Quem a vê é um anónimo com o telemóvel na mão, sem sessão e sem navegação onde
  entrar.
- **`Scan` só tem `created_at`** (`UPDATED_AT = null`) — uma leitura é um facto
  acontecido, grava-se uma vez e nunca muda. Uma coluna `updated_at` seria uma
  coluna que ninguém escreve e que sugere uma edição que não existe.

## Filas e trabalho assíncrono
O que corre em background e porquê.
