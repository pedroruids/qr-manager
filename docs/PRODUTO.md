# qr-manager

## O problema

Quem imprime material com código QR gera-o hoje num site grátis
(qr-code-generator.com, Canva) apontando **directamente** para o URL de destino.
O código sai impresso em milhares de flyers. Quando a campanha muda, o URL de
destino morre — e o papel já impresso passa a apontar para um 404. Não há forma
de corrigir sem reimprimir, nem forma de saber quantas pessoas leram o código.

## Para quem

O **gestor de marketing de uma PME que imprime flyers**. Encomenda tiragens de
alguns milhares, faz várias campanhas por ano, e não tem equipa técnica nem
orçamento para uma ferramenta de marketing paga.

## A hipótese

Acreditamos que separar o código impresso do destino — o QR aponta para um slug
curto nosso, o slug redirecciona para o destino, o destino é editável — resolve
o problema do material impresso que fica obsoleto.

Saberemos que estávamos certos se um utilizador **editar o destino de um QR já
descarregado**. É esse o gesto que nenhuma ferramenta grátis permite; se ninguém
o fizer, o produto não vale mais que um gerador estático.

## O que NÃO é

A secção mais valiosa deste documento. Em produto próprio não há cliente a
fechar o âmbito — ele cresce sozinho, uma boa ideia de cada vez. Estas são as
fronteiras do dia 1.

- **Não é um encurtador de links geral** (tipo Bitly). O link curto existe ao
  serviço do QR; não é produto autónomo com dashboard de links próprio.
- **Não é uma ferramenta de design.** Sem logótipo no meio do código, sem
  molduras, sem cores personalizadas, sem arte. Preto e branco, formato correcto.
- **Não é um gestor de campanhas nem um CRM.** Não guarda contactos, não envia
  emails, não constrói landing pages.
- **Não é multi-tenant nem SaaS com faturação.** Sem planos, sem Stripe, sem
  equipas nem convites.
- **Não gera QR que não sejam URL.** Nada de vCard, wifi, SMS, telefone ou
  evento de calendário.
- **Não é uma ferramenta de impressão.** Sem folhas de etiquetas, sem PDF pronto
  para gráfica, sem tamanhos de impressão.

**Fronteira dos dados:** o analytics pára em *total de leituras + gráfico por
dia*. Sem país, sem dispositivo, sem referrer, sem cidade, sem exportação, sem
funis nem atribuição de campanha.

## Primeira versão utilizável

O utilizador entra. Cria um QR: nome e URL de destino. O sistema gera um slug
curto e mostra o código. Descarrega PNG ou SVG e manda para a gráfica. Quem
apanha o flyer lê o código, é redireccionado, e a leitura fica contada. Mais
tarde volta, edita o URL de destino, e os flyers já impressos passam a apontar
para o sítio novo. Vê o total de leituras e o gráfico por dia.

A **API entra na v1**: uma ferramenta externa faz o mesmo por token — criar,
listar, editar destino, obter PNG/SVG, ler a contagem.

## Fluxos principais

### F1 — Criar QR
**Quem:** gestor de marketing. **Quer:** um QR para o flyer da campanha.
**Passos:** nome ("Flyer Setembro") → URL de destino → guardar → o sistema gera
o slug (`/abc123`) e renderiza a pré-visualização.
**Pode correr mal:** URL inválido ou sem esquema; colisão de slug; destino a
apontar para o próprio redirect (loop).

### F2 — Descarregar ficheiro
**Quem:** o mesmo. **Quer:** o ficheiro para mandar ao gráfico.
**Passos:** abre o QR → escolhe PNG (com tamanho) ou SVG → descarrega.
**Pode correr mal:** PNG pequeno demais e ilegível depois de impresso; a gráfica
pede vectorial e recebe raster.

### F3 — Leitura (público, anónimo)
**Quem:** quem apanha o flyer. **Quer:** chegar ao destino.
**Passos:** `GET /{slug}` → regista a leitura → redirect 302 para o destino.
**Pode correr mal:** QR desactivado ou apagado (precisa de página de erro
decente, não de um 500); o registo da leitura a atrasar o redirect; bots e
pré-visualizadores de link a inflar a contagem.

### F4 — Editar destino
**Quem:** gestor de marketing, com os flyers já impressos.
**Passos:** abre o QR → muda o URL de destino → grava. O slug **não** muda.
**Pode correr mal:** deixar o slug editável e matar todo o material impresso;
novo URL mal escrito e sem validação.

### F5 — Ver leituras
**Quem:** o mesmo. **Quer:** saber se o flyer resultou.
**Passos:** lista de QRs com o total → abre um → gráfico por dia.
**Pode correr mal:** zero leituras indistinguível de tracking partido; fuso
horário a partir a agregação diária.

### F6 — Integração por API
**Quem:** ferramenta externa (ou o dev que a mantém). **Quer:** criar QRs em massa.
**Passos:** gera token → `POST /api/qr-codes` com nome e destino → recebe o slug
e os URLs dos ficheiros → `PATCH` para mudar o destino → `GET` para a contagem.
**Pode correr mal:** token exposto; ausência de rate limit; duplicados por falta
de idempotência.

## Ecrãs

1. **Login / registo** — autenticação (vem do starter kit)
2. **Lista de QRs** — nome, slug, destino, total de leituras, estado
3. **Criar QR** — nome + URL de destino
4. **Detalhe do QR** — pré-visualização, download PNG/SVG, leituras
5. **Editar QR** — nome, destino, activo/inactivo (slug em leitura apenas)
6. **Tokens de API** — criar, listar, revogar
7. **Erro do redirect** — página pública para slug inexistente ou inactivo

## Entidades

- **User** — do starter kit
- **QrCode** — `user_id`, `nome`, `slug` (único, imutável), `destino`, `activo`
- **Scan** — `qr_code_id`, `created_at`, agente (para filtrar bots)
- **ApiToken** — tokens de acesso à API
