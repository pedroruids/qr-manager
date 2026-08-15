# Arquitetura

Escrito na Fase F, depois de os ecrãs existirem. Modelar em abstrato produz
tabelas elegantes que não suportam o que o ecrã precisa de mostrar.

## Modelo de dados
As entidades, os seus campos e as relações entre elas.
Diagrama ou lista — o que for mais legível.

| Entidade | Descrição | Relações |
|---|---|---|
| `User` | Do starter kit | tem muitos `QrCode` |
| `QrCode` | `user_id`, `nome`, `slug` (único, imutável), `destino`, `activo`, timestamps | pertence a `User` |

**Índices em `qr_codes`:** `slug` único (é por aí que o redirect público procura)
e `(user_id, created_at)` para a listagem, que ordena do mais recente para o mais
antigo.

## Módulos e fronteiras
Como o código está organizado, e o que não deve depender de quê.

## Packages escolhidos
| Package | Para quê | Porque este |
|---|---|---|
| | | |

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

## Filas e trabalho assíncrono
O que corre em background e porquê.
