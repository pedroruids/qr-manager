# Registo de decisões

Porque é que o código está como está. Duas ou três linhas por decisão, escritas
no momento em que a decisão é tomada.

O código mostra sempre o *quê*; nunca mostra o *porquê*, nem as alternativas
descartadas.

**Registar quando:** se escolhe entre alternativas, se descarta uma abordagem
óbvia, ou se faz algo que daqui a seis meses vai parecer estranho.
Não registar o trivial.

---

## 2026-08-15 — Starter kit Livewire, não Inertia

**Contexto:** o arranque exigia escolher entre o starter kit Livewire (Flux UI)
e os de Inertia (React, Vue, Svelte).

**Decisão:** Livewire 4 + Flux UI 2.

**Porquê:** o produto é CRUD sobre quatro entidades mais um gráfico por dia —
não há interactividade que justifique uma SPA. Inertia obrigaria a compilar
assets no CI para que os testes de página passassem, e a manter uma segunda
linguagem no projeto sem contrapartida.

**Consequências:** mais fácil — um só stack, CI sem passo de Node, componentes em
`resources/views/components/`. Mais difícil — se um dia houver um editor visual
com muito estado no cliente, será Alpine em vez de React.

---

## 2026-08-15 — Deploy adiado

**Contexto:** era preciso decidir onde isto corre em produção.

**Decisão:** provavelmente Ploi, mas **por configurar**. Sem servidor, sem
domínio, sem pipeline de deploy nesta fase.

**Porquê:** não existem ainda ecrãs nem modelo de dados; configurar deploy agora
seria trabalho a apodrecer antes de ser usado. O domínio do redirect é uma
decisão de produto (o slug vai ser lido em papel — o domínio tem de ser curto) e
não deve ser tomada à pressa.

**Consequências:** falta escolher e registar o domínio curto do redirect antes de
haver QRs reais. Nenhum código depende disto hoje.

---

## 2026-08-15 — Analytics limitado a leituras por dia

**Contexto:** a fronteira natural de um produto destes é escorregar para
analytics de marketing.

**Decisão:** guardar por leitura apenas o `qr_code_id`, o instante e o agente
(este último só para filtrar bots). Mostrar total e gráfico por dia. Mais nada.

**Porquê:** país, dispositivo, referrer e cidade implicam guardar IP e derivados
— dados pessoais, RGPD, e um produto diferente do que foi decidido. A pergunta
do utilizador é "o flyer resultou?", e isso responde-se com uma contagem.

**Consequências:** mais fácil — sem base de geolocalização, sem retenção de IPs,
sem consentimento. Mais difícil — se um dia se quiser país, é migração nova e
uma conversa de privacidade.

---

## 2026-08-15 — O redirect público vive fora do grupo `web`

**Contexto:** a rota `GET /{slug}` responde a um anónimo que apontou a câmara a
um papel. Registada em `routes/web.php`, levava o grupo `web`: sessão, cookie de
sessão, token CSRF e `ShareErrorsFromSession` — trabalho e estado por request
para responder um cabeçalho `Location`.

**Decisão:** ficheiro próprio `routes/publico.php`, registado sem middleware
nenhum no `then` do `bootstrap/app.php`, portanto depois de todas as outras
rotas.

**Porquê:** duas razões que se reforçam. Não abrir sessão poupa I/O no único
pedido do produto que tem de ser rápido, e evita pôr um cookie a quem nunca
pediu nada. E o `{slug}` é um apanha-tudo: registado antes das rotas da
aplicação, engolia `/login`, `/dashboard` e `/settings/*`. O `then` corre depois
da rota do Fortify e das da aplicação, o que resolve a ordem sem uma lista de
excepções a manter.

**Consequências:** mais fácil — a rota não tem estado, e qualquer cache HTTP à
frente pode vê-la sem cookies pelo meio. Mais difícil — nada que precise de
sessão pode entrar neste ficheiro, e quem lá acrescentar rotas tem de se lembrar
que o apanha-tudo é o último. Alternativa descartada: `withoutMiddleware()` na
rota, que deixava o apanha-tudo no meio do `web.php` e o problema de ordem por
resolver.

---

## 2026-08-15 — 302 no redirect, nunca 301

**Contexto:** o redirect podia ser permanente (301) e poupar pedidos ao
servidor, já que o slug nunca muda.

**Decisão:** 302.

**Porquê:** o slug é imutável, o **destino** não é — é a razão de ser do
produto. Um 301 fica em cache no browser e nos intermediários por tempo
indefinido: quem lesse o código antes de o destino mudar continuaria a ir para o
sítio antigo, sem forma de o corrigir. Trocar o destino deixaria de ter efeito
para as pessoas que mais importam, as que já leram o flyer.

**Consequências:** mais fácil — mudar o destino tem efeito imediato para toda a
gente. Mais difícil — cada leitura chega mesmo ao servidor; a contagem de
leituras depende disso, portanto é uma consequência que também é requisito.

---

## 2026-08-15 — Correcção de erros nível Q nos códigos QR

**Contexto:** o nível de correcção de erros define quanto dano o código
tolera — L ~7%, M ~15%, Q ~25%, H ~30% — e paga-se em módulos: mais correcção,
código mais denso para o mesmo conteúdo.

**Decisão:** **Q**, ~25%.

**Porquê:** o que sai daqui vai para papel, e papel dobra-se, mancha-se, apanha
chuva e é impresso por gráficas com registo mal alinhado. O conteúdo codificado
é curto — um URL de domínio curto mais seis caracteres de slug — por isso Q
mantém o código numa versão baixa e com módulos grandes, que é o que faz um
leitor barato ler à primeira. H custaria mais densidade sem ganho prático a esta
dimensão de conteúdo, e M já falha em códigos com uma dobra a meio.

**Consequências:** o nível está numa constante do `GeradorQrCode`, não numa
opção de interface. Mudá-lo depois de haver material impresso não invalida nada
— cada código continua a ler-se — mas passa a haver duas gerações de ficheiros
com robustez diferente.

---

## 2026-08-15 — PNG desenhado em GD, não com o backend Imagick do bacon

**Contexto:** o `bacon/bacon-qr-code` só traz três backends de imagem: SVG, EPS
e Imagick. O PNG teria de vir do Imagick.

**Decisão:** o SVG usa o backend do bacon; o PNG é desenhado módulo a módulo em
GD, a partir da matriz que o `Encoder` devolve.

**Porquê:** o Imagick é uma extensão nativa que não está instalada aqui nem no
runner do CI, e obrigaria a instalá-la em todo o lado só para produzir uma
grelha de quadrados pretos. O GD já é preciso de qualquer forma — é o que o
descodificador dos testes usa para ler a imagem. O desenho directo dá ainda
controlo sobre a coisa que mais importa num código impresso: cada módulo ocupa
um número inteiro de pixéis, e o resto da divisão vai para a margem em vez de
esticar os módulos. Meio pixel de módulo é uma aresta esbatida, e é aí que um
leitor barato falha.

**Consequências:** mais fácil — sem extensão nativa nova, e o PNG sai
exactamente no tamanho pedido com módulos nítidos. Mais difícil — ~40 linhas de
desenho nossas em vez de uma chamada a uma biblioteca, e qualquer variação
futura (cores, molduras) tem de ser escrita à mão.

---
