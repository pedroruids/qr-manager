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
