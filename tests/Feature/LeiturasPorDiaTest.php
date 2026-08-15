<?php

use App\Models\QrCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\LeiturasPorDia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

// =============================================================================
// Issues #11 (leituras por dia no detalhe) e #20 (componente x-bar-chart)
// =============================================================================

/**
 * O fuso em que se corta o dia. Vem sempre da configuração — um deslocamento
 * escrito à mão no teste passaria a mentir no dia em que a lei mudasse.
 */
function fusoDeLeitura(): string
{
    return (string) config('app.fuso_do_utilizador');
}

/**
 * Um instante dado pelo relógio de quem lê o gráfico.
 */
function relogioLocal(string $momento): CarbonImmutable
{
    return CarbonImmutable::parse($momento, fusoDeLeitura());
}

/**
 * Uma leitura gravada num instante concreto.
 *
 * O instante entra em UTC de propósito: é assim que a base de dados o guarda
 * (`docs/DECISIONS.md`, «Guardar em UTC, cortar o dia em Lisboa»), e é do
 * instante — não do texto — que o dia civil tem de sair.
 */
function leituraEm(QrCode $qrCode, CarbonImmutable $momento, int $quantas = 1): void
{
    Scan::factory()->for($qrCode)->count($quantas)->em($momento->utc())->create();
}

/**
 * A série do serviço, arrumada por dia civil: `['2026-07-15' => 3, ...]`.
 *
 * @return array<string, int>
 */
function serieDe(QrCode $qrCode): array
{
    $arrumada = [];

    foreach (app(LeiturasPorDia::class)->paraQrCode($qrCode) as $ponto) {
        $arrumada[$ponto['data']->toDateString()] = $ponto['valor'];
    }

    return $arrumada;
}

/**
 * Quantas consultas a base de dados levou para fazer o que lá dentro se pediu.
 */
function consultasDe(Closure $accao): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $accao();
    } finally {
        DB::disableQueryLog();
    }

    return count(DB::getQueryLog());
}

/**
 * O texto que um humano vê: fora as marcas, fora os atributos.
 */
function textoNoEcra(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
}

/**
 * Uma série de pares data/valor a terminar no dia indicado, um valor por dia.
 *
 * @param  list<int>  $valores
 * @return list<array{data: CarbonImmutable, valor: int}>
 */
function serieDeValores(array $valores, string $ultimoDia = '2026-08-15'): array
{
    $fim = CarbonImmutable::parse($ultimoDia, fusoDeLeitura());
    $quantos = count($valores);

    $serie = [];

    foreach (array_values($valores) as $indice => $valor) {
        $serie[] = [
            'data' => $fim->subDays($quantos - 1 - $indice),
            'valor' => $valor,
        ];
    }

    return $serie;
}

/**
 * As barras do gráfico, na ordem em que aparecem: a marca de abertura de cada
 * elemento com uma altura em percentagem.
 *
 * @return list<array{marca: string, altura: float}>
 */
function barrasDe(string $html): array
{
    preg_match_all('/<[^>]*\bstyle\s*=\s*"[^"]*height\s*:\s*([0-9.]+)%[^"]*"[^>]*>/u', $html, $encontradas);

    return array_map(
        fn (string $marca, string $altura): array => ['marca' => $marca, 'altura' => (float) $altura],
        $encontradas[0],
        $encontradas[1]
    );
}

/**
 * As alturas das barras, na ordem em que aparecem.
 *
 * @return list<float>
 */
function alturasDe(string $html): array
{
    return array_map(fn (array $barra): float => $barra['altura'], barrasDe($html));
}

/**
 * O `aria-label` do primeiro elemento com `role="img"`.
 */
function rotuloDoGrafico(string $html): string
{
    preg_match('/<[^>]*\brole\s*=\s*"img"[^>]*>/u', $html, $marca);

    expect($marca)->not->toBeEmpty('O gráfico não tem nenhum elemento com role="img".');

    preg_match('/\baria-label\s*=\s*"([^"]+)"/u', $marca[0], $rotulo);

    return $rotulo[1] ?? throw new RuntimeException('O elemento com role="img" não tem aria-label.');
}

/**
 * O detalhe de um QR, visto pelo dono.
 */
function ecraDoDetalhe(QrCode $qrCode): string
{
    /** @var User $dono */
    $dono = $qrCode->user;

    $resposta = test()->actingAs($dono)->get(route('codigos.detalhe', $qrCode));

    $resposta->assertOk();

    return (string) $resposta->getContent();
}

// =============================================================================
// Issue #11 — a série
// =============================================================================

// -----------------------------------------------------------------------------
// Critério: «dias sem leituras aparecem no gráfico com zero, não desaparecem».
// -----------------------------------------------------------------------------

it('devolve sempre um ponto por cada dia da janela, mesmo sem leitura nenhuma', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    $serie = app(LeiturasPorDia::class)->paraQrCode($qrCode);

    expect($serie)->toHaveCount(LeiturasPorDia::DIAS)
        ->and(array_column($serie, 'valor'))->toBe(array_fill(0, LeiturasPorDia::DIAS, 0));
});

it('cobre dias seguidos, sem saltos e sem repetições, a acabar no dia de hoje', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    $dias = array_keys(serieDe($qrCode));

    $esperados = [];
    $hoje = relogioLocal('2026-08-15 12:00:00')->toDateString();

    for ($recuo = LeiturasPorDia::DIAS - 1; $recuo >= 0; $recuo--) {
        $esperados[] = CarbonImmutable::parse($hoje)->subDays($recuo)->toDateString();
    }

    expect($dias)->toBe($esperados);
});

it('não deixa cair os dias a zero entre dois dias com leituras', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    leituraEm($qrCode, relogioLocal('2026-08-10 09:00:00'), 2);
    leituraEm($qrCode, relogioLocal('2026-08-13 09:00:00'), 5);

    $serie = serieDe($qrCode);

    expect($serie)->toHaveCount(LeiturasPorDia::DIAS)
        ->and($serie['2026-08-10'])->toBe(2)
        ->and($serie['2026-08-11'])->toBe(0)
        ->and($serie['2026-08-12'])->toBe(0)
        ->and($serie['2026-08-13'])->toBe(5)
        ->and(array_sum($serie))->toBe(7);
});

// -----------------------------------------------------------------------------
// Critério: «agregação por dia feita no fuso horário da aplicação, com um teste
// que cobre a leitura perto da meia-noite».
//
// `docs/DECISIONS.md`: o instante guarda-se em UTC, o dia corta-se no fuso de
// quem lê. No Verão, Lisboa está uma hora à frente de UTC — é aí que um corte
// em UTC arruma no dia errado.
// -----------------------------------------------------------------------------

it('arruma a leitura de meio minuto antes da meia-noite no dia que acaba', function () {
    $this->travelTo(relogioLocal('2026-07-20 12:00:00'));

    $qrCode = QrCode::factory()->create();

    leituraEm($qrCode, relogioLocal('2026-07-15 23:59:30'));

    $serie = serieDe($qrCode);

    expect($serie['2026-07-15'])->toBe(1)
        ->and($serie['2026-07-16'])->toBe(0);
});

it('arruma a leitura de meio minuto depois da meia-noite no dia que começa', function () {
    $this->travelTo(relogioLocal('2026-07-20 12:00:00'));

    $qrCode = QrCode::factory()->create();

    $momento = relogioLocal('2026-07-16 00:00:30');

    // O instante ainda é do dia 15 em UTC. Quem lê o gráfico está em Lisboa e,
    // no Verão, já virou o dia — se a conta se fizesse em UTC, esta leitura
    // aparecia no dia anterior.
    expect($momento->utc()->toDateString())->toBe('2026-07-15');

    leituraEm($qrCode, $momento);

    $serie = serieDe($qrCode);

    expect($serie['2026-07-16'])->toBe(1)
        ->and($serie['2026-07-15'])->toBe(0);
});

it('separa duas leituras a um minuto de distância que caem em dias diferentes', function () {
    $this->travelTo(relogioLocal('2026-07-20 12:00:00'));

    $qrCode = QrCode::factory()->create();

    $antes = relogioLocal('2026-07-15 23:59:30');
    $depois = relogioLocal('2026-07-16 00:00:30');

    // As duas acontecem no mesmo dia em UTC: é isso que torna o teste capaz de
    // apanhar um corte feito no fuso errado.
    expect($antes->utc()->toDateString())->toBe($depois->utc()->toDateString());

    leituraEm($qrCode, $antes);
    leituraEm($qrCode, $depois);

    $serie = serieDe($qrCode);

    expect($serie['2026-07-15'])->toBe(1)
        ->and($serie['2026-07-16'])->toBe(1);
});

// -----------------------------------------------------------------------------
// Caso limite: os dias em que os relógios mudam. O de Outubro tem 25 horas e o
// de Março 23 — e a hora que se repete em Outubro pertence ao mesmo dia civil.
// -----------------------------------------------------------------------------

it('conta no dia certo as leituras do dia em que os relógios recuam', function () {
    $this->travelTo(relogioLocal('2026-10-30 12:00:00'));

    $qrCode = QrCode::factory()->create();

    // Meia hora depois da meia-noite local, ainda de Verão: em UTC é dia 24.
    leituraEm($qrCode, relogioLocal('2026-10-25 00:30:00'));

    // A hora que acontece duas vezes: 01h30 antes e 01h30 depois do recuo. As
    // duas são do dia 25.
    leituraEm($qrCode, CarbonImmutable::parse('2026-10-25 00:30:00', 'UTC'));
    leituraEm($qrCode, CarbonImmutable::parse('2026-10-25 01:30:00', 'UTC'));

    // E o fim do dia, já em hora de Inverno.
    leituraEm($qrCode, relogioLocal('2026-10-25 23:30:00'));

    // A primeira do dia seguinte não se mistura.
    leituraEm($qrCode, relogioLocal('2026-10-26 00:30:00'));

    $serie = serieDe($qrCode);

    expect($serie['2026-10-24'])->toBe(0)
        ->and($serie['2026-10-25'])->toBe(4)
        ->and($serie['2026-10-26'])->toBe(1);
});

it('conta no dia certo as leituras do dia em que os relógios avançam', function () {
    $this->travelTo(relogioLocal('2026-04-02 12:00:00'));

    $qrCode = QrCode::factory()->create();

    // Última leitura do dia 28, ainda em hora de Inverno.
    leituraEm($qrCode, relogioLocal('2026-03-28 23:30:00'));

    // Dia 29: antes do salto (00h30 WET) e depois do salto (03h30 WEST).
    leituraEm($qrCode, relogioLocal('2026-03-29 00:30:00'));
    leituraEm($qrCode, relogioLocal('2026-03-29 03:30:00'));
    leituraEm($qrCode, relogioLocal('2026-03-29 23:30:00'));

    $serie = serieDe($qrCode);

    expect($serie['2026-03-28'])->toBe(1)
        ->and($serie['2026-03-29'])->toBe(3);
});

// -----------------------------------------------------------------------------
// Casos limite: leituras de outro código e leituras fora da janela.
// -----------------------------------------------------------------------------

it('não conta as leituras de outro código', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $meu = QrCode::factory()->create();
    $alheio = QrCode::factory()->create();

    leituraEm($meu, relogioLocal('2026-08-14 10:00:00'), 3);
    leituraEm($alheio, relogioLocal('2026-08-14 10:00:00'), 9);

    expect(array_sum(serieDe($meu)))->toBe(3)
        ->and(serieDe($meu)['2026-08-14'])->toBe(3)
        ->and(array_sum(serieDe($alheio)))->toBe(9);
});

it('deixa de fora do gráfico as leituras anteriores à janela', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    leituraEm($qrCode, relogioLocal('2026-06-01 10:00:00'), 12);
    leituraEm($qrCode, relogioLocal('2026-08-14 10:00:00'), 3);

    $serie = serieDe($qrCode);

    expect($serie)->toHaveCount(LeiturasPorDia::DIAS)
        ->and(array_sum($serie))->toBe(3)
        ->and($qrCode->scans()->count())->toBe(15);
});

it('não deixa entrar no primeiro dia da janela o que aconteceu na véspera', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    $primeiroDia = array_key_first(serieDe($qrCode));
    $vespera = CarbonImmutable::parse($primeiroDia, fusoDeLeitura())->subDay();

    leituraEm($qrCode, $vespera->setTime(23, 30));
    leituraEm($qrCode, $vespera->addDay()->setTime(0, 30), 2);

    $serie = serieDe($qrCode);

    expect($serie[$primeiroDia])->toBe(2)
        ->and(array_sum($serie))->toBe(2)
        ->and($qrCode->scans()->count())->toBe(3);
});

it('conta a leitura de hoje no último ponto da série', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    leituraEm($qrCode, relogioLocal('2026-08-15 00:00:30'), 4);

    $serie = serieDe($qrCode);

    expect(array_key_last($serie))->toBe('2026-08-15')
        ->and($serie['2026-08-15'])->toBe(4);
});

// -----------------------------------------------------------------------------
// Critério: «a consulta não faz N+1 nem carrega todos os scans para memória».
// -----------------------------------------------------------------------------

it('faz o mesmo número de consultas com trinta ou com trezentas leituras', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $poucas = QrCode::factory()->create();
    $muitas = QrCode::factory()->create();

    foreach (range(0, LeiturasPorDia::DIAS - 1) as $recuo) {
        $momento = relogioLocal('2026-08-15 10:00:00')->subDays($recuo);

        leituraEm($poucas, $momento);
        leituraEm($muitas, $momento, 10);
    }

    $servico = app(LeiturasPorDia::class);

    $comPoucas = consultasDe(fn () => $servico->paraQrCode($poucas));
    $comMuitas = consultasDe(fn () => $servico->paraQrCode($muitas));

    expect($comMuitas)->toBe($comPoucas)
        // Uma consulta agregada chega. O tecto está em duas para não prender a
        // implementação a um número, mas trinta seriam um N+1 por dia.
        ->toBeLessThanOrEqual(2)
        ->toBeGreaterThan(0);
});

it('não cresce em consultas quando a janela se enche de dias diferentes', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $umDia = QrCode::factory()->create();
    $todosOsDias = QrCode::factory()->create();

    leituraEm($umDia, relogioLocal('2026-08-14 10:00:00'));

    foreach (range(0, LeiturasPorDia::DIAS - 1) as $recuo) {
        leituraEm($todosOsDias, relogioLocal('2026-08-15 10:00:00')->subDays($recuo));
    }

    $servico = app(LeiturasPorDia::class);

    expect(consultasDe(fn () => $servico->paraQrCode($todosOsDias)))
        ->toBe(consultasDe(fn () => $servico->paraQrCode($umDia)));
});

it('não traz para memória uma leitura de cada vez', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $qrCode = QrCode::factory()->create();

    foreach (range(0, LeiturasPorDia::DIAS - 1) as $recuo) {
        leituraEm($qrCode, relogioLocal('2026-08-15 10:00:00')->subDays($recuo), 10);
    }

    $hidratadas = 0;
    Scan::retrieved(function () use (&$hidratadas): void {
        $hidratadas++;
    });

    app(LeiturasPorDia::class)->paraQrCode($qrCode);

    // Trezentas leituras, trinta dias: o que sai da base de dados é a contagem
    // de cada dia, nunca as leituras uma a uma.
    expect($hidratadas)->toBeLessThanOrEqual(LeiturasPorDia::DIAS)
        ->and($qrCode->scans()->count())->toBe(10 * LeiturasPorDia::DIAS);
});

// =============================================================================
// Issue #11 — o ecrã de detalhe
// =============================================================================

// -----------------------------------------------------------------------------
// Critério: «total corresponde ao número de `Scan` do QR» — o de sempre, não o
// dos trinta dias.
// -----------------------------------------------------------------------------

it('mostra como total todas as leituras do código, incluindo as anteriores à janela', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz de montra',
        'destino' => 'https://loja.exemplo.pt/campanha',
    ]);

    // Setenta e seis dentro da janela, espalhadas para que nenhum dia sozinho
    // dê um número que se confunda com o total.
    foreach (range(0, 18) as $recuo) {
        leituraEm($qrCode, relogioLocal('2026-08-15 10:00:00')->subDays($recuo), 4);
    }

    // E sete de antes, que continuam a ser leituras do código.
    leituraEm($qrCode, relogioLocal('2026-06-30 10:00:00'), 7);

    expect($qrCode->scans()->count())->toBe(83);

    $texto = textoNoEcra(ecraDoDetalhe($qrCode));

    expect($texto)->toContain('83')
        // 76 é o total dos trinta dias. Se fosse esse o número no ecrã, o total
        // deixava de ser o número de leituras do código.
        ->not->toContain('76');
});

it('mostra o total a subir quando chega uma leitura nova', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz de montra',
        'destino' => 'https://loja.exemplo.pt/campanha',
    ]);

    leituraEm($qrCode, relogioLocal('2026-08-14 10:00:00'), 41);

    expect(textoNoEcra(ecraDoDetalhe($qrCode)))->toContain('41');

    leituraEm($qrCode, relogioLocal('2026-08-15 10:00:00'), 2);

    expect(textoNoEcra(ecraDoDetalhe($qrCode)))->toContain('43');
});

// -----------------------------------------------------------------------------
// Critério: «estado vazio distingue "ainda sem leituras" de erro».
// -----------------------------------------------------------------------------

it('diz que ainda não há leituras num código que nunca foi lido', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz de montra',
        'destino' => 'https://loja.exemplo.pt/campanha',
    ]);

    $texto = textoNoEcra(ecraDoDetalhe($qrCode));

    expect($texto)->toContain('Ainda sem leituras')
        // Zero leituras é uma resposta, não uma avaria.
        ->not->toContain('Não foi possível carregar');
});

it('avisa de avaria, e não de vazio, quando a consulta das leituras falha', function () {
    $this->travelTo(relogioLocal('2026-08-15 12:00:00'));

    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz de montra',
        'destino' => 'https://loja.exemplo.pt/campanha',
    ]);

    // Com leituras gravadas, o ecrã tem mesmo de ir buscar a série: é aí que a
    // falha acontece, e não num código que nunca foi lido.
    leituraEm($qrCode, relogioLocal('2026-08-14 10:00:00'), 3);

    $this->mock(LeiturasPorDia::class)
        ->shouldReceive('paraQrCode')
        ->andThrow(new RuntimeException('a base de dados não respondeu'));

    $resposta = $this->actingAs($dono)->get(route('codigos.detalhe', $qrCode));

    // O erro fica contido no bloco que falhou: o código continua à vista e o
    // download continua a funcionar (`docs/mockups/detalhe-qr.html`, estado 3).
    $resposta->assertOk();

    $texto = textoNoEcra((string) $resposta->getContent());

    expect($texto)->toContain('Não foi possível carregar')
        ->not->toContain('Ainda sem leituras');
});

// =============================================================================
// Issue #20 — o componente x-bar-chart
// =============================================================================

// -----------------------------------------------------------------------------
// Critério: «aceita uma série de pares data/valor e a escala é calculada a
// partir do máximo».
// -----------------------------------------------------------------------------

it('desenha uma barra por cada ponto da série', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 3))]
    );

    expect(barrasDe($html))->toHaveCount(30);
});

it('dá a barra mais alta ao maior valor e escala as outras por ele', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores([5, 10, 20])]
    );

    $alturas = alturasDe($html);

    expect($alturas)->toHaveCount(3)
        ->and($alturas[2])->toBe(100.0)
        ->and($alturas[1])->toBeGreaterThan(45.0)->toBeLessThan(55.0)
        ->and($alturas[0])->toBeGreaterThan(20.0)->toBeLessThan(30.0);
});

it('nunca passa dos cem por cento, seja qual for o valor', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores([1, 999999, 7])]
    );

    foreach (alturasDe($html) as $altura) {
        expect($altura)->toBeLessThanOrEqual(100.0)->toBeGreaterThanOrEqual(0.0);
    }
});

it('desenha igual duas séries com a mesma forma e escalas diferentes', function () {
    $pequena = Blade::render('<x-bar-chart :serie="$serie" />', ['serie' => serieDeValores([1, 2, 4, 3])]);
    $grande = Blade::render('<x-bar-chart :serie="$serie" />', ['serie' => serieDeValores([100, 200, 400, 300])]);

    // A escala sai do máximo da própria série: quatro leituras num dia com
    // máximo de quatro desenham-se como quatrocentas num dia com máximo de
    // quatrocentas.
    expect(alturasDe($grande))->toBe(alturasDe($pequena));
});

// -----------------------------------------------------------------------------
// Critério: «dias a zero desenham uma linha de base visível em `zinc-200`, não
// desaparecem».
// -----------------------------------------------------------------------------

it('deixa uma linha de base visível nos dias sem leituras', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores([0, 8, 0, 4])]
    );

    $barras = barrasDe($html);

    expect($barras)->toHaveCount(4);

    foreach ([0, 2] as $indice) {
        expect($barras[$indice]['altura'])->toBeGreaterThan(0.0)
            ->toBeLessThan(10.0)
            ->and($barras[$indice]['marca'])->toContain('zinc-200');
    }
});

it('não pinta de cinzento os dias que tiveram leituras', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores([0, 8])]
    );

    $barras = barrasDe($html);

    expect($barras[1]['marca'])->not->toContain('zinc-200')
        ->and($barras[1]['altura'])->toBe(100.0);
});

it('continua a desenhar trinta barras numa série toda a zero', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 0))]
    );

    $barras = barrasDe($html);

    expect($barras)->toHaveCount(30);

    foreach ($barras as $barra) {
        expect($barra['altura'])->toBeGreaterThan(0.0)->toBeLessThan(10.0);
    }
});

// -----------------------------------------------------------------------------
// Critério: «tem `role="img"` com um `aria-label` que resume mínimo, máximo e
// total».
// -----------------------------------------------------------------------------

it('anuncia-se como imagem e resume mínimo, máximo e total', function () {
    $valores = array_fill(0, 30, 4);
    $valores[7] = 71;

    // mínimo 4, máximo 71, total 187 — três números que não se confundem.
    expect(array_sum($valores))->toBe(187);

    $rotulo = rotuloDoGrafico(Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores($valores)]
    ));

    expect($rotulo)->toMatch('/\b4\b\D+\b71\b\D+\b187\b/u');
});

it('anuncia o zero como mínimo quando houve dias sem leituras', function () {
    $valores = array_fill(0, 30, 5);
    $valores[3] = 0;
    $valores[9] = 62;

    // mínimo 0, máximo 62, total 202.
    expect(array_sum($valores))->toBe(202);

    $rotulo = rotuloDoGrafico(Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores($valores)]
    ));

    expect($rotulo)->toMatch('/\b0\b\D+\b62\b\D+\b202\b/u');
});

// -----------------------------------------------------------------------------
// Critério: «rótulos de data no eixo: início, meio e fim».
// -----------------------------------------------------------------------------

it('escreve no eixo três datas: a primeira, uma do meio e a última', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 3), '2026-08-15')]
    );

    // Só o que se lê: as alturas vivem em atributos e não entram nesta conta.
    preg_match_all('/\b(\d{1,2})(?:\s+\p{L}+|\/\d{1,2})/u', textoNoEcra($html), $rotulos);

    expect($rotulos[1])->toHaveCount(3)
        // A série vai de 17 de Julho a 15 de Agosto.
        ->and((int) $rotulos[1][0])->toBe(17)
        ->and((int) $rotulos[1][2])->toBe(15)
        // O do meio é um dos dois dias centrais da série.
        ->and((int) $rotulos[1][1])->toBeIn([31, 1]);
});

it('muda os rótulos do eixo quando a série muda de datas', function () {
    $primeira = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 3), '2026-08-15')]
    );

    $segunda = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 3), '2026-12-31')]
    );

    expect(textoNoEcra($segunda))->not->toBe(textoNoEcra($primeira));
});

// -----------------------------------------------------------------------------
// Critério: «sem dependências novas no `package.json` nem no `composer.json`».
// -----------------------------------------------------------------------------

it('não trouxe nenhuma biblioteca de gráficos para o projecto', function (string $ficheiro) {
    /** @var array<string, mixed> $manifesto */
    $manifesto = json_decode((string) file_get_contents(base_path($ficheiro)), true, 512, JSON_THROW_ON_ERROR);

    $dependencias = [];

    foreach (['require', 'require-dev', 'dependencies', 'devDependencies', 'optionalDependencies'] as $seccao) {
        if (is_array($manifesto[$seccao] ?? null)) {
            $dependencias = [...$dependencias, ...array_keys($manifesto[$seccao])];
        }
    }

    expect($dependencias)->not->toBeEmpty();

    foreach ($dependencias as $pacote) {
        expect($pacote)->not->toMatch('/chart|graph|plot|d3|echart|apex|nivo|vega/i');
    }
})->with(['composer.json', 'package.json']);

it('desenha o gráfico sem uma linha de JavaScript', function () {
    $html = Blade::render(
        '<x-bar-chart :serie="$serie" />',
        ['serie' => serieDeValores(array_fill(0, 30, 3))]
    );

    expect($html)->not->toMatch('/<script\b/i')
        ->not->toMatch('/\bon(?:click|load|mouseover)\s*=/i');
});
