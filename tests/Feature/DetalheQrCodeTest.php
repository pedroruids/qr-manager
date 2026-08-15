<?php

use App\Models\QrCode;
use App\Models\User;
use App\Services\GeradorQrCode;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zxing\QrReader;

// =============================================================================
// Issue #9 — Ecrã de detalhe do QR com pré-visualização e download
// =============================================================================

/**
 * Um destino que não se parece com nada do sistema: nem com o domínio da
 * aplicação, nem com um slug, nem com o nome do código. Assim nenhuma asserção
 * o apanha por acaso, nem deixa de o apanhar quando devia.
 */
const DESTINO_DO_DETALHE = 'https://loja.exemplo.pt/campanha-de-natal';

function geradorDoDetalhe(): GeradorQrCode
{
    return app(GeradorQrCode::class);
}

function urlDoEcra(QrCode $qrCode): string
{
    return route('codigos.detalhe', $qrCode);
}

function urlDoDownload(QrCode $qrCode, string $formato, array $parametros = []): string
{
    return route('codigos.descarregar', ['qrCode' => $qrCode, 'formato' => $formato, ...$parametros]);
}

/**
 * O corpo de um download, seja ele enviado de uma vez, em stream ou a partir de
 * um ficheiro em disco. O teste não deve depender de qual das três.
 */
function corpoDoDownload(TestResponse $resposta): string
{
    $base = $resposta->baseResponse;

    if ($base instanceof BinaryFileResponse) {
        return (string) file_get_contents($base->getFile()->getPathname());
    }

    if ($base instanceof StreamedResponse) {
        return $resposta->streamedContent();
    }

    return (string) $base->getContent();
}

/**
 * O nome de ficheiro anunciado no `Content-Disposition` — o `filename=` simples,
 * que é o que qualquer cliente entende, e não o `filename*=` opcional.
 */
function nomeDoFicheiro(TestResponse $resposta): string
{
    $disposicao = (string) $resposta->headers->get('Content-Disposition');

    preg_match('/(?<![\w*])filename\s*=\s*(?:"([^"]*)"|([^;]+))/i', $disposicao, $partes);

    return trim($partes[1] ?? $partes[2] ?? '');
}

/**
 * O desenho do código dentro de um SVG: o `d` do único `<path>` que o compõe.
 *
 * Vem em unidades de módulo, dentro de um `<g transform="scale(...)">`, portanto
 * é o mesmo seja qual for o tamanho pedido. É exactamente o que se quer comparar
 * quando a pergunta é «é o mesmo código?» e não «é o mesmo ficheiro?».
 */
function desenhoDoCodigo(string $svg): string
{
    preg_match('/<path[^>]*\sd="([^"]+)"/', $svg, $partes);

    return $partes[1] ?? '';
}

/**
 * Descodifica um PNG em memória com o `Zxing\QrReader`, que só lê de ficheiro.
 * O terceiro argumento a `false` força o GD; o `TRY_HARDER` evita as falhas
 * intermitentes em imagens grandes.
 */
function lerPngDescarregado(string $png): string
{
    $ficheiro = tempnam(sys_get_temp_dir(), 'qr-detalhe-');
    expect($ficheiro)->toBeString();
    file_put_contents($ficheiro, $png);

    try {
        $texto = (new QrReader($ficheiro, QrReader::SOURCE_TYPE_FILE, false))->text(['TRY_HARDER' => true]);

        expect($texto)->toBeString()->not->toBeEmpty();

        return (string) $texto;
    } finally {
        @unlink($ficheiro);
    }
}

// -----------------------------------------------------------------------------
// Critério: «só o dono do QR acede (teste que prova o 403/404 para outro
// utilizador)».
// -----------------------------------------------------------------------------

it('deixa o dono abrir o detalhe do seu código', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $this->actingAs($dono)->get(urlDoEcra($qrCode))->assertOk();
});

it('recusa o ecrã de detalhe a outro utilizador autenticado', function () {
    $qrCode = QrCode::factory()->create();
    $intruso = User::factory()->create();

    $resposta = $this->actingAs($intruso)->get(urlDoEcra($qrCode));

    expect($resposta->status())->toBeIn([403, 404]);
});

it('não revela nada do código a quem não é o dono', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Cartaz Confidencial',
        'destino' => 'https://loja.exemplo.pt/segredo',
    ]);
    $intruso = User::factory()->create();

    $resposta = $this->actingAs($intruso)->get(urlDoEcra($qrCode));

    $resposta->assertDontSee('Cartaz Confidencial');
    $resposta->assertDontSee('loja.exemplo.pt/segredo');
});

it('recusa o download a outro utilizador autenticado', function (string $formato) {
    $qrCode = QrCode::factory()->create();
    $intruso = User::factory()->create();

    $resposta = $this->actingAs($intruso)->get(urlDoDownload($qrCode, $formato));

    expect($resposta->status())->toBeIn([403, 404]);
    expect(corpoDoDownload($resposta))->not->toStartWith("\x89PNG");
})->with(['png', 'svg']);

it('manda o anónimo para o login em vez de lhe mostrar o detalhe', function () {
    $qrCode = QrCode::factory()->create();

    $this->get(urlDoEcra($qrCode))->assertRedirect(route('login'));
});

it('manda o anónimo para o login em vez de lhe dar o ficheiro', function (string $formato) {
    $qrCode = QrCode::factory()->create();

    $this->get(urlDoDownload($qrCode, $formato))->assertRedirect(route('login'));
})->with(['png', 'svg']);

// -----------------------------------------------------------------------------
// Critério: «mostra o URL curto e o destino actual».
// -----------------------------------------------------------------------------

it('mostra o nome do código no ecrã', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['nome' => 'Autocolante montra Black Friday']);

    $this->actingAs($dono)->get(urlDoEcra($qrCode))->assertSee('Autocolante montra Black Friday');
});

it('mostra o URL curto do slug', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $html = (string) $this->actingAs($dono)->get(urlDoEcra($qrCode))->getContent();

    // É este o endereço que está impresso no papel; tem de aparecer inteiro,
    // pronto a copiar, e não apenas o slug solto.
    expect($html)->toContain(route('redirect.publico', $qrCode->slug));
});

it('mostra o destino actual', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_DO_DETALHE]);

    $this->actingAs($dono)->get(urlDoEcra($qrCode))->assertSee(DESTINO_DO_DETALHE);
});

it('passa a mostrar o novo destino depois de o dono o mudar, sem mexer no URL curto', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => 'https://loja.exemplo.pt/verao']);
    $urlCurto = route('redirect.publico', $qrCode->slug);

    $qrCode->update(['destino' => DESTINO_DO_DETALHE]);

    $resposta = $this->actingAs($dono)->get(urlDoEcra($qrCode));

    $resposta->assertSee(DESTINO_DO_DETALHE);
    $resposta->assertDontSee('loja.exemplo.pt/verao');
    expect((string) $resposta->getContent())->toContain($urlCurto);
});

// -----------------------------------------------------------------------------
// Critério: «o download em PNG e em SVG devolve o ficheiro com o content-type
// correcto e nome legível».
// -----------------------------------------------------------------------------

it('devolve um PNG com o content-type de PNG', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, 'png'));

    $resposta->assertOk();
    expect((string) $resposta->headers->get('Content-Type'))->toStartWith('image/png');
});

it('devolve mesmo um PNG no corpo, e não só no cabeçalho', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $corpo = corpoDoDownload($this->actingAs($dono)->get(urlDoDownload($qrCode, 'png')));

    // A assinatura de um PNG, byte a byte (ISO/IEC 15948, 5.2).
    expect($corpo)->toStartWith("\x89PNG\x0d\x0a\x1a\x0a");

    $informacao = getimagesizefromstring($corpo);
    expect($informacao)->not->toBeFalse()
        ->and($informacao[2])->toBe(IMAGETYPE_PNG);
});

it('devolve um SVG com o content-type de SVG', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, 'svg'));

    $resposta->assertOk();
    expect((string) $resposta->headers->get('Content-Type'))->toStartWith('image/svg+xml');
});

it('devolve mesmo um SVG no corpo, e não só no cabeçalho', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $corpo = corpoDoDownload($this->actingAs($dono)->get(urlDoDownload($qrCode, 'svg')));

    expect($corpo)->toContain('<svg');

    $anterior = libxml_use_internal_errors(true);
    $documento = simplexml_load_string($corpo);
    libxml_use_internal_errors($anterior);

    expect($documento)->not->toBeFalse()
        ->and($documento->getName())->toBe('svg');
});

it('entrega o ficheiro como transferência e não para abrir no browser', function (string $formato, string $extensao) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, $formato));

    expect((string) $resposta->headers->get('Content-Disposition'))->toContain('attachment')
        ->and(nomeDoFicheiro($resposta))->toEndWith($extensao);
})->with([
    'png' => ['png', '.png'],
    'svg' => ['svg', '.svg'],
]);

it('dá ao ficheiro um nome que vem do nome do código', function (string $formato) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['nome' => 'Autocolante montra Black Friday']);

    $nome = nomeDoFicheiro($this->actingAs($dono)->get(urlDoDownload($qrCode, $formato)));

    // Quem descarrega três códigos numa tarde tem de os distinguir na pasta das
    // transferências sem os abrir.
    expect(Str::lower($nome))->toContain('autocolante-montra-black-friday');
})->with(['png', 'svg']);

it('dá nomes de ficheiro diferentes a códigos com nomes diferentes', function () {
    $dono = User::factory()->create();
    $primeiro = QrCode::factory()->for($dono)->create(['nome' => 'Flyer de Verao']);
    $segundo = QrCode::factory()->for($dono)->create(['nome' => 'Cartaz de Inverno']);

    $nomePrimeiro = nomeDoFicheiro($this->actingAs($dono)->get(urlDoDownload($primeiro, 'png')));
    $nomeSegundo = nomeDoFicheiro($this->actingAs($dono)->get(urlDoDownload($segundo, 'png')));

    expect($nomePrimeiro)->not->toBe($nomeSegundo);
});

it('dá um nome de ficheiro seguro quando o nome do código tem acentos e pontuação', function (string $nomeDoCodigo) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['nome' => $nomeDoCodigo]);

    $nome = nomeDoFicheiro($this->actingAs($dono)->get(urlDoDownload($qrCode, 'png')));

    expect($nome)->not->toBeEmpty()
        ->toEndWith('.png')
        // Um nome com barras, aspas ou bytes fora do ASCII parte o cabeçalho ou
        // o sistema de ficheiros de quem o recebe.
        ->and(preg_match('/^[\x20-\x7e]+$/', $nome))->toBe(1)
        ->and($nome)->not->toContain('/')
        ->and($nome)->not->toContain('\\')
        ->and($nome)->not->toContain('"');
})->with([
    'acentos' => 'Acção de Verão na Praia',
    'travessão e barra' => 'Cartaz A2 — Feira do Livro / entrada poente',
    'aspas e dois pontos' => 'Flyer "Natal": 20% de desconto',
    'só acentos' => 'ÇÃOÉÍÕÜ',
]);

// -----------------------------------------------------------------------------
// Critério: «download em PNG (com tamanho)» — o mockup oferece 512, 1024 e 2048.
// -----------------------------------------------------------------------------

it('devolve o PNG no tamanho pedido', function (int $tamanho) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $corpo = corpoDoDownload(
        $this->actingAs($dono)->get(urlDoDownload($qrCode, 'png', ['tamanho' => $tamanho]))
    );

    $informacao = getimagesizefromstring($corpo);

    expect($informacao)->not->toBeFalse()
        ->and($informacao[0])->toBe($tamanho)
        ->and($informacao[1])->toBe($tamanho);
})->with([
    'ecrã' => 512,
    'flyer' => 1024,
    'cartaz' => 2048,
]);

/**
 * Um tamanho impossível faz do endereço um endereço que não existe.
 *
 * Isto é o endereço de um ficheiro, que alguém pode ter aberto directamente num
 * separador: não há formulário submetido nem «trás» para onde voltar, portanto
 * a resposta é 404 e não um redirect com erro de validação.
 */
it('responde 404 a um tamanho de PNG que não existe', function (int|string $tamanho) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, 'png', ['tamanho' => $tamanho]));

    expect($resposta->status())->toBe(404)
        ->and(corpoDoDownload($resposta))->not->toStartWith("\x89PNG");
})->with([
    'zero' => 0,
    'negativo' => -256,
    'abaixo do mínimo' => GeradorQrCode::TAMANHO_PNG_MINIMO - 1,
    'acima do máximo' => GeradorQrCode::TAMANHO_PNG_MAXIMO + 1,
    'absurdo' => 100000,
    'palavra' => 'grande',
    'letras' => 'abc',
]);

it('trata um tamanho vazio como tamanho nenhum e serve o PNG por omissão', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    // `?tamanho=` é a ausência de escolha, não uma escolha impossível: quem
    // abriu o endereço sem escolher tamanho leva o mesmo que quem não pediu.
    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, 'png', ['tamanho' => '']));

    $resposta->assertOk();

    $informacao = getimagesizefromstring(corpoDoDownload($resposta));

    expect($informacao)->not->toBeFalse()
        ->and($informacao[0])->toBe(GeradorQrCode::TAMANHO_PNG_OMISSAO)
        ->and($informacao[1])->toBe(GeradorQrCode::TAMANHO_PNG_OMISSAO);
});

it('responde 404 a um formato que não existe', function (string $formato) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $this->actingAs($dono)->get(urlDoDownload($qrCode, $formato))->assertNotFound();
})->with(['jpg', 'pdf', 'gif', 'eps', 'webp']);

// -----------------------------------------------------------------------------
// Requisito: desactivar um código não apaga o ficheiro. O código deixa de
// redireccionar, mas o dono continua a poder vê-lo e a levá-lo para a gráfica.
// -----------------------------------------------------------------------------

it('deixa o dono abrir o detalhe de um código inactivo', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->inactivo()->create();

    $this->actingAs($dono)->get(urlDoEcra($qrCode))->assertOk();
});

it('deixa o dono descarregar um código inactivo', function (string $formato) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->inactivo()->create();

    $resposta = $this->actingAs($dono)->get(urlDoDownload($qrCode, $formato));

    $resposta->assertOk();
    expect(corpoDoDownload($resposta))->not->toBeEmpty();
})->with(['png', 'svg']);

it('continua a recusar o código inactivo de outra pessoa', function () {
    $qrCode = QrCode::factory()->inactivo()->create();
    $intruso = User::factory()->create();

    expect($this->actingAs($intruso)->get(urlDoDownload($qrCode, 'png'))->status())->toBeIn([403, 404]);
});

// -----------------------------------------------------------------------------
// Critério: «a pré-visualização é o mesmo código que é descarregado».
// -----------------------------------------------------------------------------

it('mostra na pré-visualização o mesmo desenho que o SVG descarregado', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_DO_DETALHE]);

    $descarregado = corpoDoDownload($this->actingAs($dono)->get(urlDoDownload($qrCode, 'svg')));
    $desenho = desenhoDoCodigo($descarregado);

    expect($desenho)->not->toBeEmpty();

    $html = (string) $this->actingAs($dono)->get(urlDoEcra($qrCode))->getContent();

    expect($html)->toContain($desenho);
});

it('mostra na pré-visualização o desenho que o gerador produz para aquele código', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $desenho = desenhoDoCodigo(geradorDoDetalhe()->svg($qrCode));
    $html = (string) $this->actingAs($dono)->get(urlDoEcra($qrCode))->getContent();

    expect($desenho)->not->toBeEmpty()
        ->and($html)->toContain($desenho);
});

it('não mostra na pré-visualização o desenho de outro código', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();
    $outro = QrCode::factory()->for($dono)->create();

    $desenhoDoOutro = desenhoDoCodigo(geradorDoDetalhe()->svg($outro));
    $html = (string) $this->actingAs($dono)->get(urlDoEcra($qrCode))->getContent();

    expect($desenhoDoOutro)->not->toBeEmpty()
        ->and($html)->not->toContain($desenhoDoOutro);
});

it('descarrega um PNG que codifica o URL curto, nunca o destino', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_DO_DETALHE]);

    $corpo = corpoDoDownload($this->actingAs($dono)->get(urlDoDownload($qrCode, 'png')));

    expect(lerPngDescarregado($corpo))->toBe(route('redirect.publico', $qrCode->slug))
        ->not->toContain('loja.exemplo.pt')
        ->not->toContain('campanha-de-natal');
});
