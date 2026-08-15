<?php

use App\Models\QrCode;
use App\Services\GeradorQrCode;
use Zxing\QrReader;

// =============================================================================
// Issue #8 — Geração do código QR em PNG e SVG
// =============================================================================

/**
 * Um destino bem distinto do URL curto, para que nenhuma asserção o apanhe por
 * acaso: nada nele se parece com o domínio da aplicação nem com um slug.
 */
const DESTINO_DISTINTO = 'https://loja.exemplo.pt/campanha-de-natal';

function gerador(): GeradorQrCode
{
    return app(GeradorQrCode::class);
}

/**
 * Descodifica um PNG em memória com o `Zxing\QrReader`, que só lê de ficheiro.
 */
function descodificarPng(string $png): string
{
    // O ficheiro é o que o `tempnam` criou, sem sufixo acrescentado: o leitor
    // não olha para a extensão, e assim há exactamente um ficheiro para apagar.
    $ficheiro = tempnam(sys_get_temp_dir(), 'qr-');
    expect($ficheiro)->toBeString();
    expect(file_put_contents($ficheiro, $png))->toBe(strlen($png));

    try {
        // Terceiro argumento a `false`: força o GD e mantém o teste igual em
        // qualquer máquina, haja ou não Imagick instalado.
        $leitor = new QrReader($ficheiro, QrReader::SOURCE_TYPE_FILE, false);

        // `TRY_HARDER` obriga o leitor a varrer todas as linhas à procura dos
        // padrões de localização. Sem ele salta linhas — tanto mais quanto maior
        // a imagem — e a partir dos 256 px perde os padrões em cerca de 2,5% dos
        // slugs, que são aleatórios: falha intermitente, num teste diferente de
        // cada vez. Custa alguns milissegundos, irrelevante a esta escala.
        $texto = $leitor->text(['TRY_HARDER' => true]);

        expect($texto)->toBeString()->not->toBeEmpty();

        return (string) $texto;
    } finally {
        @unlink($ficheiro);
    }
}

/**
 * Mede o PNG na própria imagem — dimensões, espessura das quatro margens brancas
 * e o lado de um módulo, deduzido da aresta de cima do padrão de localização
 * (sete módulos, por norma). Nada aqui repete o cálculo do serviço.
 *
 * @return array{largura: int, altura: int, cima: int, baixo: int, esquerda: int, direita: int, modulo: float}
 */
function medidasDoPng(string $png): array
{
    $imagem = imagecreatefromstring($png);
    expect($imagem)->not->toBeFalse();

    $largura = imagesx($imagem);
    $altura = imagesy($imagem);

    $escuro = function (int $x, int $y) use ($imagem): bool {
        $cor = imagecolorat($imagem, $x, $y);
        $vermelho = ($cor >> 16) & 0xFF;
        $verde = ($cor >> 8) & 0xFF;
        $azul = $cor & 0xFF;

        return (0.299 * $vermelho + 0.587 * $verde + 0.114 * $azul) < 128;
    };

    $linhaTemEscuro = function (int $y) use ($escuro, $largura): bool {
        for ($x = 0; $x < $largura; $x++) {
            if ($escuro($x, $y)) {
                return true;
            }
        }

        return false;
    };

    $colunaTemEscuro = function (int $x) use ($escuro, $altura): bool {
        for ($y = 0; $y < $altura; $y++) {
            if ($escuro($x, $y)) {
                return true;
            }
        }

        return false;
    };

    for ($cima = 0; $cima < $altura && ! $linhaTemEscuro($cima); $cima++);
    for ($ultimaLinha = $altura - 1; $ultimaLinha >= 0 && ! $linhaTemEscuro($ultimaLinha); $ultimaLinha--);
    for ($esquerda = 0; $esquerda < $largura && ! $colunaTemEscuro($esquerda); $esquerda++);
    for ($ultimaColuna = $largura - 1; $ultimaColuna >= 0 && ! $colunaTemEscuro($ultimaColuna); $ultimaColuna--);

    expect($cima)->toBeLessThan($altura);
    expect($esquerda)->toBeLessThan($largura);

    // A primeira linha escura é a aresta de cima dos padrões de localização; o
    // primeiro troço escuro dessa linha tem exactamente sete módulos.
    $troco = 0;
    for ($x = $esquerda; $x < $largura && $escuro($x, $cima); $x++) {
        $troco++;
    }

    imagedestroy($imagem);

    return [
        'largura' => $largura,
        'altura' => $altura,
        'cima' => $cima,
        'baixo' => $altura - 1 - $ultimaLinha,
        'esquerda' => $esquerda,
        'direita' => $largura - 1 - $ultimaColuna,
        'modulo' => $troco / 7,
    ];
}

/**
 * Lê o nível de correcção de erros directamente da imagem.
 *
 * A informação de formato (ISO/IEC 18004, 8.9) são quinze módulos em posições
 * fixas à volta do padrão de localização de cima à esquerda; depois de desfeita
 * a máscara `0x5412`, os dois bits mais significativos são o nível. O
 * descodificador não serve para isto: devolve sempre `L`, à mão, no
 * `DecodedBitStreamParser`.
 *
 * A leitura foi conferida contra códigos gerados de propósito nos quatro níveis:
 * devolve L, M, Q e H, cada um no seu. Trocar as coordenadas por engano faz esta
 * função devolver um nível errado mas estável, portanto convincente — quem lhe
 * mexer tem de repetir essa conferência.
 */
function nivelDeCorreccaoDoPng(string $png): string
{
    $medidas = medidasDoPng($png);
    $imagem = imagecreatefromstring($png);

    $moduloEscuro = function (int $linha, int $coluna) use ($imagem, $medidas): int {
        $x = (int) round($medidas['esquerda'] + ($coluna + 0.5) * $medidas['modulo']);
        $y = (int) round($medidas['cima'] + ($linha + 0.5) * $medidas['modulo']);
        $cor = imagecolorat($imagem, $x, $y);
        $luminancia = 0.299 * (($cor >> 16) & 0xFF) + 0.587 * (($cor >> 8) & 0xFF) + 0.114 * ($cor & 0xFF);

        return $luminancia < 128 ? 1 : 0;
    };

    // Posição [coluna, linha] de cada bit da informação de formato, do bit menos
    // significativo (junto ao canto de cima à direita do padrão) ao mais.
    $posicoes = [
        [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
        [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
    ];

    $formato = 0;
    foreach ($posicoes as $bit => [$coluna, $linha]) {
        $formato |= $moduloEscuro($linha, $coluna) << $bit;
    }

    imagedestroy($imagem);

    return match (($formato ^ 0x5412) >> 13 & 0b11) {
        0b01 => 'L',
        0b00 => 'M',
        0b11 => 'Q',
        0b10 => 'H',
    };
}

// -----------------------------------------------------------------------------
// Critério: «o conteúdo codificado é o URL curto do slug, nunca o destino».
// -----------------------------------------------------------------------------

it('anuncia como conteúdo o URL curto do slug', function () {
    $qrCode = QrCode::factory()->create(['destino' => DESTINO_DISTINTO]);

    expect(gerador()->url($qrCode))->toBe(route('redirect.publico', $qrCode->slug));
});

it('não põe o destino no URL que vai ser codificado', function () {
    $qrCode = QrCode::factory()->create(['destino' => DESTINO_DISTINTO]);

    expect(gerador()->url($qrCode))
        ->toContain($qrCode->slug)
        ->not->toContain('loja.exemplo.pt')
        ->and(gerador()->url($qrCode))->not->toContain('campanha-de-natal');
});

it('codifica no PNG o URL do slug e nunca o destino', function () {
    $qrCode = QrCode::factory()->create(['destino' => DESTINO_DISTINTO]);

    $lido = descodificarPng(gerador()->png($qrCode));

    expect($lido)->toBe(route('redirect.publico', $qrCode->slug))
        ->not->toContain('loja.exemplo.pt')
        ->not->toContain('campanha-de-natal')
        ->not->toContain(DESTINO_DISTINTO);
});

it('continua a codificar o mesmo URL depois de o destino mudar', function () {
    $qrCode = QrCode::factory()->create(['destino' => 'https://loja.exemplo.pt/verao']);
    $esperado = route('redirect.publico', $qrCode->slug);

    $qrCode->update(['destino' => DESTINO_DISTINTO]);

    expect(descodificarPng(gerador()->png($qrCode->fresh())))->toBe($esperado);
});

it('não deixa o destino escapar para dentro do SVG', function () {
    $qrCode = QrCode::factory()->create(['destino' => DESTINO_DISTINTO]);

    $svg = gerador()->svg($qrCode);

    expect($svg)->not->toContain('loja.exemplo.pt')
        ->and($svg)->not->toContain('campanha-de-natal');
});

// -----------------------------------------------------------------------------
// Critério: «SVG gerado abre em vectorial, sem raster embutido».
// -----------------------------------------------------------------------------

it('devolve um SVG que é mesmo XML com raiz svg', function () {
    $qrCode = QrCode::factory()->create();

    $svg = gerador()->svg($qrCode);

    expect($svg)->toContain('<svg');

    $anterior = libxml_use_internal_errors(true);
    $documento = simplexml_load_string($svg);
    libxml_use_internal_errors($anterior);

    expect($documento)->not->toBeFalse()
        ->and($documento->getName())->toBe('svg');
});

it('não embute raster nenhum no SVG', function () {
    $qrCode = QrCode::factory()->create();

    $svg = gerador()->svg($qrCode);

    expect($svg)->not->toContain('<image')
        ->and($svg)->not->toContain('data:image')
        ->and($svg)->not->toContain('base64')
        ->and(strtolower($svg))->not->toContain('xlink:href');
});

it('desenha o código em formas vectoriais', function () {
    $qrCode = QrCode::factory()->create();

    $svg = gerador()->svg($qrCode);

    expect($svg)->toMatch('/<(path|rect|polygon|g)\b/');
});

it('produz o mesmo SVG para o mesmo código e SVGs diferentes para códigos diferentes', function () {
    $primeiro = QrCode::factory()->create();
    $segundo = QrCode::factory()->create();

    expect(gerador()->svg($primeiro))->toBe(gerador()->svg($primeiro))
        ->and(gerador()->svg($primeiro))->not->toBe(gerador()->svg($segundo));
});

// -----------------------------------------------------------------------------
// Critério: «PNG aceita tamanho e tem margem (quiet zone) suficiente para
// leitura impressa».
// -----------------------------------------------------------------------------

it('devolve mesmo um PNG', function () {
    $qrCode = QrCode::factory()->create();

    $informacao = getimagesizefromstring(gerador()->png($qrCode));

    expect($informacao)->not->toBeFalse()
        ->and($informacao[2])->toBe(IMAGETYPE_PNG);
});

it('devolve o PNG quadrado no tamanho pedido', function (int $tamanho) {
    $qrCode = QrCode::factory()->create();

    $informacao = getimagesizefromstring(gerador()->png($qrCode, $tamanho));

    expect($informacao[0])->toBe($tamanho)
        ->and($informacao[1])->toBe($tamanho);
})->with([
    'mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO,
    'meio' => 1024,
]);

it('aceita o tamanho máximo anunciado', function () {
    $qrCode = QrCode::factory()->create();

    // O tecto existe porque a imagem ocupa 4 bytes por pixel enquanto é
    // desenhada: tem de sair na memória de uma instalação por omissão.
    $informacao = getimagesizefromstring(gerador()->png($qrCode, GeradorQrCode::TAMANHO_PNG_MAXIMO));

    expect($informacao[0])->toBe(GeradorQrCode::TAMANHO_PNG_MAXIMO)
        ->and($informacao[1])->toBe(GeradorQrCode::TAMANHO_PNG_MAXIMO);
});

it('usa um tamanho por omissão quando ninguém o pede', function () {
    $qrCode = QrCode::factory()->create();

    $informacao = getimagesizefromstring(gerador()->png($qrCode));

    expect($informacao[0])->toBe(GeradorQrCode::TAMANHO_PNG_OMISSAO)
        ->and($informacao[1])->toBe(GeradorQrCode::TAMANHO_PNG_OMISSAO);
});

it('recusa tamanhos de PNG fora dos limites', function (int $tamanho) {
    $qrCode = QrCode::factory()->create();

    expect(fn () => gerador()->png($qrCode, $tamanho))->toThrow(Exception::class);
})->with([
    'zero' => 0,
    'negativo' => -256,
    'abaixo do mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO - 1,
    'acima do máximo' => GeradorQrCode::TAMANHO_PNG_MAXIMO + 1,
]);

it('deixa quatro módulos de margem branca dos quatro lados', function (int $tamanho) {
    $qrCode = QrCode::factory()->create();

    $medidas = medidasDoPng(gerador()->png($qrCode, $tamanho));
    $margemMinima = 4 * $medidas['modulo'];

    expect($medidas['modulo'])->toBeGreaterThan(0)
        ->and($medidas['cima'])->toBeGreaterThanOrEqual($margemMinima)
        ->and($medidas['baixo'])->toBeGreaterThanOrEqual($margemMinima)
        ->and($medidas['esquerda'])->toBeGreaterThanOrEqual($margemMinima)
        ->and($medidas['direita'])->toBeGreaterThanOrEqual($margemMinima);
})->with([
    'mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO,
    'omissão' => GeradorQrCode::TAMANHO_PNG_OMISSAO,
    'meio' => 1024,
]);

it('lê-se em qualquer um dos tamanhos aceites', function (int $tamanho) {
    $qrCode = QrCode::factory()->create(['destino' => DESTINO_DISTINTO]);

    expect(descodificarPng(gerador()->png($qrCode, $tamanho)))
        ->toBe(route('redirect.publico', $qrCode->slug));
})->with([
    'mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO,
    'omissão' => GeradorQrCode::TAMANHO_PNG_OMISSAO,
    'meio' => 1024,
]);

it('produz o mesmo PNG para o mesmo código e PNGs diferentes para códigos diferentes', function () {
    $primeiro = QrCode::factory()->create();
    $segundo = QrCode::factory()->create();

    expect(gerador()->png($primeiro))->toBe(gerador()->png($primeiro))
        ->and(gerador()->png($primeiro))->not->toBe(gerador()->png($segundo));
});

// -----------------------------------------------------------------------------
// Critério: «nível de correcção de erros escolhido e registado» — o
// `docs/DECISIONS.md` regista Q (~25%). Quem lê o ficheiro tem de encontrar Q.
// -----------------------------------------------------------------------------

it('codifica com o nível de correcção Q registado nas decisões', function () {
    $qrCode = QrCode::factory()->create();

    expect(nivelDeCorreccaoDoPng(gerador()->png($qrCode)))->toBe('Q');
});

it('mantém o nível Q seja qual for o tamanho pedido', function (int $tamanho) {
    $qrCode = QrCode::factory()->create();

    expect(nivelDeCorreccaoDoPng(gerador()->png($qrCode, $tamanho)))->toBe('Q');
})->with([
    'mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO,
    'meio' => 1024,
]);
