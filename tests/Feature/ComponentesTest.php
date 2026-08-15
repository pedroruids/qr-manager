<?php

use App\Models\QrCode;
use App\Services\GeradorQrCode;
use Illuminate\Support\Facades\Blade;

// =============================================================================
// Issues #17, #18 e #19 — os três componentes próprios de `docs/DESIGN.md`
// =============================================================================

/**
 * O texto que um humano vê no ecrã: fora as marcas, fora os atributos, com o
 * espaço em branco normalizado. É aqui que se pergunta «isto aparece?».
 */
function textoVisivel(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
}

/**
 * O valor de todos os atributos do HTML. Serve para perguntar o contrário:
 * «isto está ligado à marcação, e não apenas escrito no ecrã?».
 *
 * @return list<string>
 */
function valoresDeAtributos(string $html): array
{
    preg_match_all('/=\s*"([^"]*)"|=\s*\'([^\']*)\'/u', $html, $encontrados);

    return array_map(
        fn (string $aspas, string $plicas): string => $aspas !== '' ? $aspas : $plicas,
        $encontrados[1],
        $encontrados[2]
    );
}

/**
 * A marca de abertura do primeiro elemento com o atributo pedido.
 */
function marcaCom(string $html, string $atributo, string $valor): string
{
    preg_match(
        '/<[^>]*\b'.preg_quote($atributo, '/').'\s*=\s*"'.preg_quote($valor, '/').'"[^>]*>/u',
        $html,
        $partes
    );

    return $partes[0] ?? throw new RuntimeException("Não há nenhum elemento com {$atributo}=\"{$valor}\".");
}

/**
 * A primeira marca de abertura que corresponde ao padrão.
 */
function primeiraMarca(string $html, string $padrao): string
{
    preg_match($padrao, $html, $partes);

    return $partes[0] ?? throw new RuntimeException("Nada corresponde a {$padrao}.");
}

/**
 * O `aria-label` de uma marca de abertura.
 */
function ariaLabelDe(string $marca): string
{
    preg_match('/\baria-label\s*=\s*"([^"]+)"/u', $marca, $partes);

    return $partes[1] ?? throw new RuntimeException("A marca [{$marca}] não tem aria-label.");
}

/**
 * A geometria do código dentro de um SVG do `bacon/bacon-qr-code`: os caminhos
 * vêm em unidades de módulo, portanto não mudam com o tamanho pedido. Duas
 * matrizes iguais são o mesmo conteúdo codificado.
 */
function geometriaDoSvg(string $svg): string
{
    preg_match_all('/\sd\s*=\s*"([^"]+)"/u', $svg, $caminhos);

    expect($caminhos[1])->not->toBeEmpty('O SVG não tem nenhum caminho desenhado.');

    return implode('|', $caminhos[1]);
}

// -----------------------------------------------------------------------------
// Issue #17 — x-copy-field
// -----------------------------------------------------------------------------

// Critério: «o que é copiado é o valor completo, não o truncado». O `texto`
// mostra uma versão curta; a área de transferência leva sempre o `valor`.

it('copia o valor completo mesmo quando mostra uma versão curta', function () {
    $completo = 'https://qr.exemplo.pt/bf26xq';
    $curto = 'qr.exemplo.pt/bf26xq';

    $html = Blade::render(
        '<x-copy-field :valor="$valor" :texto="$texto" />',
        ['valor' => $completo, 'texto' => $curto]
    );

    // O que se lê no ecrã é a versão curta — a completa não está lá.
    expect(textoVisivel($html))->toContain($curto)
        ->and(textoVisivel($html))->not->toContain($completo);

    // E o valor completo está ligado à marcação, que é de onde a cópia sai.
    expect(valoresDeAtributos($html))->toContain($completo);
});

it('mostra o próprio valor quando não recebe versão curta', function () {
    $html = Blade::render('<x-copy-field valor="bf26xq" />');

    expect(textoVisivel($html))->toContain('bf26xq');
});

// Critério: «botão tem `aria-label` que identifica o que copia».

it('dá ao botão de copiar um aria-label que diz o que faz', function () {
    $html = Blade::render('<x-copy-field valor="qrm_9Xk2LpQ7ta4NvZ0RbEwH6mYcJs1UgDf3" />');

    expect($html)->toMatch('/<button\b/');

    expect(ariaLabelDe(primeiraMarca($html, '/<button\b[^>]*>/u')))->toContain('opiar');
});

// Critério: «confirmação visível após copiar, que desaparece sozinha». Sem
// navegador não se clica; o que se verifica é que a confirmação existe na
// marcação e que é anunciada a quem não a vê.

it('traz uma confirmação de cópia que é anunciada por leitor de ecrã', function () {
    $html = Blade::render('<x-copy-field valor="bf26xq" />');

    $marca = marcaCom($html, 'role', 'status');

    expect($marca)->not->toBeEmpty();
});

// Critério: «valor longo trunca sem partir a linha». O `docs/DESIGN.md` fixa a
// regra: trunca numa linha, com o valor completo em `title`.

it('trunca um valor longo numa linha e guarda o completo no title', function () {
    $longo = 'https://qr.exemplo.pt/campanhas/setembro-2026/regresso-as-aulas?utm_source=flyer&utm_medium=qr';

    $html = Blade::render('<x-copy-field :valor="$valor" />', ['valor' => $longo]);

    expect($html)->toContain('truncate');

    $titulos = array_filter(
        valoresDeAtributos($html),
        fn (string $valor): bool => str_contains($valor, 'utm_medium=qr')
    );

    expect($html)->toMatch('/\btitle\s*=\s*"[^"]+"/u')
        ->and($titulos)->not->toBeEmpty('O valor completo não aparece em nenhum atributo title.');
});

// -----------------------------------------------------------------------------
// Issue #18 — x-empty-state
// -----------------------------------------------------------------------------

// Critério: «aceita ícone, título, descrição e slot de acção».

it('desenha ícone, título, descrição e acção', function () {
    $html = Blade::render(<<<'BLADE'
        <x-empty-state
            titulo="Ainda não tem códigos QR"
            descricao="Cada código aponta para um endereço curto deste sistema.">
            <x-slot:icone>
                <svg data-teste="icone-grelha"></svg>
            </x-slot:icone>
            <x-slot:acao>
                <button type="button">Criar o primeiro QR</button>
            </x-slot:acao>
        </x-empty-state>
    BLADE);

    expect(textoVisivel($html))
        ->toContain('Ainda não tem códigos QR')
        ->toContain('Cada código aponta para um endereço curto deste sistema.')
        ->toContain('Criar o primeiro QR')
        ->and($html)->toContain('data-teste="icone-grelha"');
});

// Critério: «sem acção definida, não desenha botão nenhum».

it('não desenha botão nenhum quando não recebe acção', function () {
    $html = Blade::render(
        '<x-empty-state titulo="Ainda não criou nenhum token" descricao="Um token deixa outra ferramenta gerir os códigos." />'
    );

    expect(textoVisivel($html))->toContain('Ainda não criou nenhum token')
        ->and($html)->not->toMatch('/<button\b/')
        ->and($html)->not->toMatch('/<a\b/');
});

// Critério: «variante compacta para usar dentro de um cartão». Duas densidades
// que se desenham igual não são duas densidades.

it('desenha a variante compacta diferente da variante de página', function () {
    $conteudo = 'titulo="Ainda sem leituras" descricao="As leituras aparecem aqui assim que alguém apontar a câmara."';

    $pagina = Blade::render("<x-empty-state {$conteudo} />");
    $compacto = Blade::render("<x-empty-state compacto {$conteudo} />");

    expect(textoVisivel($compacto))->toContain('Ainda sem leituras')
        ->and(textoVisivel($pagina))->toContain('Ainda sem leituras')
        ->and($compacto)->not->toBe($pagina);
});

// Critério: «`valor` quando o vazio é uma contagem a zero» (`docs/DESIGN.md`).
// O `docs/DESIGN.md` também fixa que valor ausente é informação: o zero
// escreve-se, não se esconde.

it('mostra o zero quando o vazio é uma contagem', function () {
    $conteudo = 'titulo="Ainda sem leituras" descricao="As leituras aparecem aqui assim que alguém apontar a câmara."';

    $semValor = Blade::render("<x-empty-state compacto {$conteudo} />");
    $comZero = Blade::render("<x-empty-state compacto :valor=\"0\" {$conteudo} />");

    // Sem contagem não há número nenhum no ecrã; com contagem a zero, há um zero.
    expect(textoVisivel($semValor))->not->toContain('0')
        ->and(textoVisivel($comZero))->toContain('0');
});

// -----------------------------------------------------------------------------
// Issue #19 — x-qr-preview
// -----------------------------------------------------------------------------

// Critério: «tem `role="img"` e um `aria-label` que identifica de que código se
// trata».

it('anuncia-se como imagem e diz de que código se trata', function () {
    $qrCode = QrCode::factory()->create(['nome' => 'Autocolante montra Black Friday']);

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);

    expect(ariaLabelDe(marcaCom($html, 'role', 'img')))->toContain('Autocolante montra Black Friday');
});

// Critério: o código mostrado é o do URL curto do slug. O conteúdo não se
// descodifica a partir do SVG, mas a geometria vem em unidades de módulo:
// comparar com o que o `GeradorQrCode` produz para o mesmo QR é comparar a
// matriz codificada.

it('mostra o código do URL curto do slug, e não outro', function () {
    $qrCode = QrCode::factory()->create(['destino' => 'https://loja.exemplo.pt/black-friday']);
    $outro = QrCode::factory()->create();

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);
    $gerador = app(GeradorQrCode::class);

    expect($html)->toContain('<svg')
        ->and(geometriaDoSvg($html))->toBe(geometriaDoSvg($gerador->svg($qrCode)))
        ->and(geometriaDoSvg($html))->not->toBe(geometriaDoSvg($gerador->svg($outro)));
});

it('não deixa o destino escapar para dentro da moldura', function () {
    $qrCode = QrCode::factory()->create(['destino' => 'https://loja.exemplo.pt/campanha-de-natal']);

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);

    expect($html)->not->toContain('loja.exemplo.pt')
        ->and($html)->not->toContain('campanha-de-natal');
});

// Critério: «sem raio de cantos, ao contrário do resto do sistema».

it('não arredonda os cantos, ao contrário do resto do sistema', function () {
    $qrCode = QrCode::factory()->create();

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);

    expect($html)->not->toContain('rounded');
});

// Critério: «fundo branco e margem (quiet zone) preservados mesmo em tema
// escuro». Fundo que não muda no escuro é fundo sem variante `dark:`.

it('mantém o fundo branco também no tema escuro', function () {
    $qrCode = QrCode::factory()->create();

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);

    expect($html)->toContain('bg-white')
        ->and($html)->not->toContain('dark:bg-');
});

// Critério: «responsivo sem deformar: mantém a proporção 1:1».

it('mantém o código quadrado', function () {
    $qrCode = QrCode::factory()->create();

    $html = Blade::render('<x-qr-preview :qr-code="$qrCode" />', ['qrCode' => $qrCode]);

    expect($html)->toMatch('/viewBox\s*=\s*"0 0 (\d+(?:\.\d+)?) \1"/u');
});
