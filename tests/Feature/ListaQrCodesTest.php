<?php

use App\Models\QrCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// =============================================================================
// Issue #6 — Ecrã: lista de QRs
// =============================================================================

/**
 * O HTML do ecrã tal como o utilizador o recebe, incluindo a paginação, que vive
 * na barra de endereço e não numa propriedade do componente.
 *
 * @param  array<string, mixed>  $parametros
 */
function htmlDaLista(User $utilizador, array $parametros = []): string
{
    return (string) test()->actingAs($utilizador)
        ->get(route('codigos.index', $parametros))
        ->getContent();
}

/**
 * O texto que um humano lê, sem marcas e com o espaço em branco normalizado.
 */
function textoDaLista(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
}

/**
 * A linha da tabela onde aquele código aparece. A pergunta «este número é deste
 * código?» só tem resposta dentro da linha; no ecrã inteiro qualquer número
 * responde por acaso.
 */
function linhaDaLista(string $html, QrCode $qrCode): string
{
    preg_match_all('/<tr\b[^>]*>.*?<\/tr>/su', $html, $linhas);

    foreach ($linhas[0] as $linha) {
        if (str_contains($linha, $qrCode->slug)) {
            return $linha;
        }
    }

    throw new RuntimeException("Não há nenhuma linha com o código [{$qrCode->slug}].");
}

/**
 * O texto visível de uma linha, já sem o slug nem o endereço curto — os únicos
 * sítios da linha onde entram dígitos que não são dados do código.
 */
function textoDaLinha(string $html, QrCode $qrCode): string
{
    $linha = str_replace($qrCode->slug, '', linhaDaLista($html, $qrCode));

    return textoDaLista($linha);
}

/**
 * A assinatura de uma ação primária, perguntada ao próprio sistema de design em
 * vez de escrita à mão: o que o Flux acrescenta ao botão quando é primário e não
 * está no botão comum.
 *
 * `docs/DESIGN.md` fixa a ação primária em `brand-600`, que é o que o token
 * `--color-accent` vale; um ecrã que a pinte à mão continua a ser apanhado.
 *
 * @return list<string>
 */
function marcasDeAccaoPrimaria(): array
{
    $classes = static function (string $html): array {
        preg_match('/\bclass="([^"]*)"/u', $html, $partes);

        return preg_split('/\s+/u', $partes[1] ?? '') ?: [];
    };

    $doPrimario = $classes(Blade::render('<flux:button variant="primary">x</flux:button>'));
    $doComum = $classes(Blade::render('<flux:button>x</flux:button>'));

    $exclusivas = array_values(array_filter(
        array_diff($doPrimario, $doComum),
        fn (string $classe): bool => str_starts_with($classe, 'bg-') && ! str_contains($classe, 'hover')
    ));

    expect($exclusivas)->not->toBeEmpty('Não foi possível descobrir como o Flux pinta uma ação primária.');

    return [...$exclusivas, 'bg-brand-600'];
}

/**
 * Os botões e ligações desenhados como ação primária, com o texto de cada um.
 *
 * @return list<string>
 */
function accoesPrimarias(string $html): array
{
    $alternativas = implode('|', array_map(
        fn (string $marca): string => preg_quote($marca, '/'),
        marcasDeAccaoPrimaria()
    ));

    preg_match_all(
        '/<(a|button)\b[^>]*\bclass="[^"]*(?:'.$alternativas.')[^"]*"[^>]*>(.*?)<\/\1>/su',
        $html,
        $encontradas
    );

    return array_map(textoDaLista(...), $encontradas[2]);
}

/**
 * Quantas consultas a listagem faz para um utilizador com aquele número de
 * códigos, cada um com leituras.
 */
function consultasDaLista(int $quantosCodigos): int
{
    $dono = User::factory()->create();

    QrCode::factory()->count($quantosCodigos)->for($dono)->create()
        ->each(fn (QrCode $qrCode) => Scan::factory()->count(2)->for($qrCode)->create());

    test()->actingAs($dono);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test('pages::codigos.index');

    $consultas = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $consultas;
}

// -----------------------------------------------------------------------------
// Quem chega ao ecrã.
// -----------------------------------------------------------------------------

it('deixa um utilizador autenticado abrir a lista', function () {
    $this->actingAs(User::factory()->create())->get(route('codigos.index'))->assertOk();
});

it('manda o anónimo para o login em vez de lhe mostrar a lista', function () {
    QrCode::factory()->create(['nome' => 'Flyer de Setembro']);

    $this->get(route('codigos.index'))->assertRedirect(route('login'));
});

// -----------------------------------------------------------------------------
// Critério: «mostra apenas os QRs do utilizador autenticado (teste que prova o
// isolamento)».
// -----------------------------------------------------------------------------

it('mostra os códigos de quem está autenticado', function () {
    $dono = User::factory()->create();
    $meu = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz da Feira do Livro',
        'destino' => 'https://loja.exemplo.pt/eventos/feira-do-livro',
    ]);

    $html = htmlDaLista($dono);

    expect($html)->toContain($meu->slug)
        ->and(textoDaLista($html))->toContain('Cartaz da Feira do Livro')
        ->and($html)->toContain('loja.exemplo.pt/eventos/feira-do-livro');
});

it('não deixa escapar nada dos códigos de outra pessoa', function () {
    $vizinho = User::factory()->create();
    $doVizinho = QrCode::factory()->for($vizinho)->create([
        'nome' => 'Campanha Confidencial do Vizinho',
        'destino' => 'https://outra-loja.exemplo.pt/segredo-do-vizinho',
    ]);

    $dono = User::factory()->create();
    QrCode::factory()->for($dono)->create(['nome' => 'Flyer de Setembro']);

    $html = htmlDaLista($dono);

    expect(textoDaLista($html))->toContain('Flyer de Setembro')
        ->not->toContain('Campanha Confidencial do Vizinho')
        ->and($html)->not->toContain($doVizinho->slug)
        ->and($html)->not->toContain('segredo-do-vizinho')
        ->and($html)->not->toContain('outra-loja.exemplo.pt');
});

it('não conta para mim as leituras dos códigos de outra pessoa', function () {
    $vizinho = User::factory()->create();
    $doVizinho = QrCode::factory()->for($vizinho)->create(['nome' => 'Cartaz do vizinho']);
    Scan::factory()->count(9)->for($doVizinho)->create();

    $dono = User::factory()->create();
    $meu = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz da entrada']);
    Scan::factory()->count(2)->for($meu)->create();

    expect(textoDaLinha(htmlDaLista($dono), $meu))->toContain('2')->not->toContain('9');
});

it('mostra o estado vazio a quem não tem códigos, mesmo com códigos de outros na base de dados', function () {
    QrCode::factory()->count(3)->create();

    $novo = User::factory()->create();

    expect(textoDaLista(htmlDaLista($novo)))->toContain('Ainda não tem códigos QR');
});

// -----------------------------------------------------------------------------
// Critério: «estado vazio com explicação e acção para criar o primeiro».
// -----------------------------------------------------------------------------

it('explica o vazio com as palavras do mockup e oferece o primeiro passo', function () {
    $texto = textoDaLista(htmlDaLista(User::factory()->create()));

    expect($texto)->toContain('Ainda não tem códigos QR')
        ->toContain('Cada código aponta para um endereço curto deste sistema.')
        ->toContain('o papel já impresso continua a funcionar')
        ->toContain('Criar o primeiro QR');
});

it('leva a acção do estado vazio ao formulário de criação', function () {
    expect(htmlDaLista(User::factory()->create()))->toContain(route('codigos.criar'));
});

it('deixa de mostrar o estado vazio assim que existe um código', function () {
    $dono = User::factory()->create();
    QrCode::factory()->for($dono)->create(['nome' => 'Flyer de Setembro']);

    $texto = textoDaLista(htmlDaLista($dono));

    expect($texto)->not->toContain('Ainda não tem códigos QR')
        ->not->toContain('Criar o primeiro QR');
});

// -----------------------------------------------------------------------------
// Critério: «pagina a partir de 25 registos».
// -----------------------------------------------------------------------------

it('mostra os 25 códigos de um utilizador sem paginar', function () {
    $dono = User::factory()->create();
    $codigos = QrCode::factory()->count(25)->for($dono)->create();

    $html = htmlDaLista($dono);

    $presentes = $codigos->filter(fn (QrCode $qrCode): bool => str_contains($html, $qrCode->slug));

    expect($presentes)->toHaveCount(25);
});

it('deixa 25 códigos na primeira página e manda o 26.º para a seguinte', function () {
    $dono = User::factory()->create();

    // Criados por ordem: o índice 0 é o mais antigo, o 25 é o mais recente.
    $codigos = collect(range(0, 25))->map(function (int $indice) use ($dono): QrCode {
        $qrCode = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz número '.$indice]);
        $qrCode->forceFill(['created_at' => now()->subMinutes(100 - $indice)])->save();

        return $qrCode;
    });

    $primeira = htmlDaLista($dono);
    $segunda = htmlDaLista($dono, ['page' => 2]);

    $naPrimeira = $codigos->filter(fn (QrCode $qrCode): bool => str_contains($primeira, $qrCode->slug));
    $naSegunda = $codigos->filter(fn (QrCode $qrCode): bool => str_contains($segunda, $qrCode->slug));

    expect($naPrimeira)->toHaveCount(25)
        ->and($naSegunda)->toHaveCount(1)
        // O que sobra para a segunda página é o mais antigo — o mais recente vem primeiro.
        ->and($naSegunda->first()->is($codigos->first()))->toBeTrue()
        ->and($primeira)->not->toContain($codigos->first()->slug);
});

it('diz quantos códigos existem ao todo, e não quantos cabem na página', function () {
    $dono = User::factory()->create();
    QrCode::factory()->count(26)->for($dono)->create();

    // O 26 é a contagem total; os 25 da página não a substituem.
    expect(textoDaLista(htmlDaLista($dono)))->toMatch('/\b26\b/');
});

// -----------------------------------------------------------------------------
// Critério: «o slug é copiável com um clique».
// -----------------------------------------------------------------------------

it('mostra o slug de cada código', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    expect(textoDaLinha(htmlDaLista($dono), $qrCode))->not->toBeEmpty()
        ->and(textoDaLista(linhaDaLista(htmlDaLista($dono), $qrCode)))->toContain($qrCode->slug);
});

it('dá a copiar o endereço curto completo, e não apenas o slug mostrado', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $linha = linhaDaLista(htmlDaLista($dono), $qrCode);

    // É este o endereço que vai para o papel; copiar só `abc123` não serve a
    // ninguém do outro lado.
    expect($linha)->toContain(route('redirect.publico', $qrCode->slug));
});

it('põe um botão de copiar identificado em cada linha', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['nome' => 'Rótulo da garrafa']);

    $linha = linhaDaLista(htmlDaLista($dono), $qrCode);

    expect($linha)->toMatch('/<button\b/')
        ->and($linha)->toMatch('/\baria-label\s*=\s*"[^"]*opiar[^"]*"/u');
});

it('dá a cada código o seu próprio endereço para copiar', function () {
    $dono = User::factory()->create();
    $primeiro = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz da entrada']);
    $segundo = QrCode::factory()->for($dono)->create(['nome' => 'Postal da encomenda']);

    $html = htmlDaLista($dono);

    expect(linhaDaLista($html, $primeiro))->toContain(route('redirect.publico', $primeiro->slug))
        ->not->toContain($segundo->slug);
});

// -----------------------------------------------------------------------------
// Critério: «uma única acção primária no ecrã: "Criar QR"».
// -----------------------------------------------------------------------------

it('tem uma única acção primária quando há códigos', function () {
    $dono = User::factory()->create();
    QrCode::factory()->count(3)->for($dono)->create();

    test()->actingAs($dono);

    $accoes = accoesPrimarias(Livewire::test('pages::codigos.index')->html());

    expect($accoes)->toHaveCount(1)
        ->and($accoes[0])->toContain('Criar QR');
});

it('tem uma única acção primária no estado vazio, e é a do estado vazio', function () {
    test()->actingAs(User::factory()->create());

    $accoes = accoesPrimarias(Livewire::test('pages::codigos.index')->html());

    // `docs/DESIGN.md`: no estado vazio a ação do cabeçalho passa a secundária.
    // Duas primárias com o mesmo texto é pedir a mesma decisão duas vezes.
    expect($accoes)->toHaveCount(1)
        ->and($accoes[0])->toContain('Criar o primeiro QR');
});

it('leva a acção primária ao formulário de criação', function () {
    $dono = User::factory()->create();
    QrCode::factory()->for($dono)->create();

    expect(htmlDaLista($dono))->toContain(route('codigos.criar'));
});

// -----------------------------------------------------------------------------
// Leituras: a contagem é por código, e o zero escreve-se.
// -----------------------------------------------------------------------------

it('escreve o zero num código que ainda ninguém leu', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz da entrada',
        'destino' => 'https://loja.exemplo.pt/entrada',
    ]);

    $texto = textoDaLinha(htmlDaLista($dono), $qrCode);

    // `docs/DESIGN.md`: valor ausente é informação. Não se esconde nem se
    // substitui por um traço.
    expect($texto)->toContain('0')
        ->not->toContain('—')
        ->not->toContain('n/d');
});

it('conta as leituras de cada código, e não as do vizinho de linha', function () {
    $dono = User::factory()->create();

    $muito = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz da entrada', 'destino' => 'https://loja.exemplo.pt/entrada']);
    $pouco = QrCode::factory()->for($dono)->create(['nome' => 'Postal da encomenda', 'destino' => 'https://loja.exemplo.pt/postal']);
    $nenhum = QrCode::factory()->for($dono)->create(['nome' => 'Placa da montra', 'destino' => 'https://loja.exemplo.pt/montra']);

    Scan::factory()->count(7)->for($muito)->create();
    Scan::factory()->count(2)->for($pouco)->create();

    $html = htmlDaLista($dono);

    expect(textoDaLinha($html, $muito))->toContain('7')->not->toContain('2')
        ->and(textoDaLinha($html, $pouco))->toContain('2')->not->toContain('7')
        ->and(textoDaLinha($html, $nenhum))->toContain('0')->not->toContain('7');
});

// -----------------------------------------------------------------------------
// Estado do código: aparece na lista e lê-se sem depender da cor.
// -----------------------------------------------------------------------------

it('mostra também os códigos inactivos', function () {
    $dono = User::factory()->create();
    $inactivo = QrCode::factory()->for($dono)->inactivo()->create(['nome' => 'Menu da esplanada']);

    $html = htmlDaLista($dono);

    expect($html)->toContain($inactivo->slug)
        ->and(textoDaLista($html))->toContain('Menu da esplanada');
});

it('distingue activo de inactivo por texto, e não só por cor', function () {
    $dono = User::factory()->create();
    $activo = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz da entrada']);
    $inactivo = QrCode::factory()->for($dono)->inactivo()->create(['nome' => 'Menu da esplanada']);

    $html = htmlDaLista($dono);

    expect(textoDaLinha($html, $activo))->toContain('Activo')->not->toContain('Inactivo')
        ->and(textoDaLinha($html, $inactivo))->toContain('Inactivo');
});

// -----------------------------------------------------------------------------
// Ordem: o mais recente primeiro.
// -----------------------------------------------------------------------------

it('põe o código mais recente à cabeça da lista', function () {
    $dono = User::factory()->create();

    $antigo = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz do ano passado']);
    $antigo->forceFill(['created_at' => now()->subYear()])->save();

    $meio = QrCode::factory()->for($dono)->create(['nome' => 'Postal do mês passado']);
    $meio->forceFill(['created_at' => now()->subMonth()])->save();

    $recente = QrCode::factory()->for($dono)->create(['nome' => 'Flyer de hoje']);

    $html = htmlDaLista($dono);

    $posicao = fn (QrCode $qrCode): int => (int) strpos($html, $qrCode->slug);

    expect($posicao($recente))->toBeLessThan($posicao($meio))
        ->and($posicao($meio))->toBeLessThan($posicao($antigo));
});

// -----------------------------------------------------------------------------
// Eficiência: contar leituras não pode custar uma consulta por linha.
// -----------------------------------------------------------------------------

it('não faz uma consulta por linha para contar as leituras', function () {
    $comPoucos = consultasDaLista(3);
    $comMuitos = consultasDaLista(25);

    // O trabalho da listagem não depende de quantos códigos lá estão: se
    // depender, é uma consulta por linha, e a 25.ª pessoa paga a conta.
    expect($comMuitos)->toBe($comPoucos);
});
