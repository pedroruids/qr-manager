<?php

use App\Http\Middleware\LimitarPedidosDaApiPorIp;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zxing\QrReader;

// =============================================================================
// Issue #13 — API: endpoints de criação e gestão de QRs
// =============================================================================

/**
 * Valores que não se parecem com nada do sistema nem uns com os outros: assim
 * nenhuma asserção os apanha por acaso, nem deixa de os apanhar quando devia.
 */
const NOME_NA_API = 'Flyer da feira de Setembro';

const DESTINO_NA_API = 'https://loja.exemplo.pt/feira-de-setembro';

const DESTINO_NOVO_NA_API = 'https://loja.exemplo.pt/campanha-de-inverno';

const NOME_DO_VIZINHO_NA_API = 'Cartaz privado do vizinho';

const DESTINO_DO_VIZINHO_NA_API = 'https://outra-empresa.exemplo.pt/interno';

/**
 * O token em claro de uma ferramenta externa. É o que ela guarda e envia; nada
 * mais dela existe deste lado.
 */
function tokenDaApiDe(User $utilizador, string $nome = 'Ferramenta de importação'): string
{
    return $utilizador->createToken($nome)->plainTextToken;
}

/**
 * Um pedido feito de fora, como o faz a ferramenta que guarda o token: só com o
 * cabeçalho, sem a sessão de quem está no ecrã. Sem esquecer os guards, o
 * `sanctum` autenticava pelo `web` e o token não provava nada.
 *
 * @param  array<string, mixed>  $dados
 */
function pedidoNaApi(?string $token, string $metodo, string $url, array $dados = []): TestResponse
{
    auth()->forgetGuards();

    $pedido = test();

    if ($token !== null) {
        $pedido = $pedido->withToken($token);
    }

    return $pedido->json($metodo, $url, $dados);
}

function criarNaApi(?string $token, mixed $dados = null): TestResponse
{
    return pedidoNaApi($token, 'post', route('api.qr-codes.store'), $dados ?? [
        'nome' => NOME_NA_API,
        'destino' => DESTINO_NA_API,
    ]);
}

function listarNaApi(?string $token, array $parametros = []): TestResponse
{
    return pedidoNaApi($token, 'get', route('api.qr-codes.index', $parametros));
}

function verNaApi(?string $token, QrCode|string $qrCode): TestResponse
{
    return pedidoNaApi($token, 'get', route('api.qr-codes.show', $qrCode));
}

/**
 * @param  array<string, mixed>  $dados
 */
function alterarNaApi(?string $token, QrCode|string $qrCode, array $dados): TestResponse
{
    return pedidoNaApi($token, 'patch', route('api.qr-codes.update', $qrCode), $dados);
}

/**
 * Passa a fazer os pedidos seguintes a partir de outro IP, como se fossem de
 * outra máquina. Fica posto para o resto do teste.
 */
function pedidosDaApiVindosDe(string $ip): void
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip]);
}

/**
 * Um endereço qualquer seguido com o token, tal como o faria quem leu o JSON.
 * Não é `json()`: o que se espera de volta é um ficheiro, não uma estrutura.
 */
function seguirComToken(?string $token, string $url): TestResponse
{
    auth()->forgetGuards();

    $pedido = test();

    if ($token !== null) {
        $pedido = $pedido->withToken($token);
    }

    return $pedido->get($url);
}

/**
 * Todos os valores escalares de uma resposta, seja qual for a profundidade e a
 * chave onde a implementação os arrumou. O critério fala de valores — o slug, o
 * URL curto, os endereços dos ficheiros — e não de nomes de campos.
 *
 * @param  mixed  $json
 * @return list<string>
 */
function valoresDaRespostaDaApi($json): array
{
    if (is_array($json)) {
        $valores = [];

        foreach ($json as $valor) {
            $valores = [...$valores, ...valoresDaRespostaDaApi($valor)];
        }

        return $valores;
    }

    return is_scalar($json) ? [(string) $json] : [];
}

/**
 * O endereço do ficheiro daquele formato dentro da resposta, procurado pelo que
 * ele é — um endereço absoluto, daquele código, daquele formato.
 *
 * @param  array<mixed>  $json
 */
function urlDoFicheiroNaApi(array $json, string $slug, string $formato): string
{
    $candidatos = array_values(array_filter(
        valoresDaRespostaDaApi($json),
        fn (string $valor): bool => str_starts_with($valor, 'http')
            && str_contains($valor, $slug)
            && preg_match('/\b'.preg_quote($formato, '/').'\b/i', $valor) === 1
    ));

    expect($candidatos)->not->toBeEmpty(
        "A resposta não traz nenhum endereço do ficheiro {$formato} do código [{$slug}]."
    );

    return $candidatos[0];
}

/**
 * Os itens de uma listagem, com ou sem o embrulho `data` que os API Resources
 * põem por omissão.
 *
 * @param  array<mixed>  $json
 * @return array<int, mixed>
 */
function itensDaListagemDaApi(array $json): array
{
    $itens = array_key_exists('data', $json) && is_array($json['data']) ? $json['data'] : $json;

    return array_values($itens);
}

/**
 * O corpo de um download, seja ele enviado de uma vez, em stream ou a partir de
 * um ficheiro em disco. O teste não deve depender de qual das três.
 */
function corpoDoFicheiroDaApi(TestResponse $resposta): string
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
 * Descodifica um PNG em memória com o `Zxing\QrReader`, que só lê de ficheiro.
 */
function lerPngDaApi(string $png): string
{
    $ficheiro = tempnam(sys_get_temp_dir(), 'qr-api-');
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

/**
 * Um endereço do próprio sistema, construído a partir da configuração e nunca
 * de um domínio escrito à mão.
 */
function enderecoDaCasaNaApi(string $caminho = '/abc234'): string
{
    return rtrim((string) config('app.url'), '/').'/'.ltrim($caminho, '/');
}

/**
 * Os pedidos que compõem a API, para os critérios que valem em todos eles e não
 * apenas naquele onde é mais cómodo prová-los.
 *
 * @return array{0: string, 1: string, 2: array<string, mixed>}
 */
function pedidoDaApiPara(string $endpoint, QrCode $qrCode): array
{
    return match ($endpoint) {
        'listar' => ['get', route('api.qr-codes.index'), []],
        'ver' => ['get', route('api.qr-codes.show', $qrCode), []],
        'criar' => ['post', route('api.qr-codes.store'), ['nome' => NOME_NA_API, 'destino' => DESTINO_NA_API]],
        'alterar' => ['patch', route('api.qr-codes.update', $qrCode), ['destino' => DESTINO_NOVO_NA_API]],
        'ficheiro' => ['get', route('api.qr-codes.ficheiro', ['qrCode' => $qrCode, 'formato' => 'png']), []],
    };
}

dataset('endpoints da api', ['listar', 'ver', 'criar', 'alterar', 'ficheiro']);

/**
 * O balde do limitador vive na cache. A cache de teste é de memória e nasce
 * vazia em cada teste, mas isto deixa-o dito em vez de suposto.
 */
beforeEach(function () {
    Cache::flush();
});

// -----------------------------------------------------------------------------
// Critério: «POST /api/qr-codes cria e devolve 201 com slug, URL curto e URLs
// dos ficheiros PNG/SVG».
// -----------------------------------------------------------------------------

it('cria um código e responde 201', function () {
    $dono = User::factory()->create();

    criarNaApi(tokenDaApiDe($dono))->assertCreated();

    $criado = QrCode::sole();

    expect($criado->nome)->toBe(NOME_NA_API)
        ->and($criado->destino)->toBe(DESTINO_NA_API)
        ->and($criado->user_id)->toBe($dono->id);
});

it('devolve o slug do código que acabou de criar', function () {
    $resposta = criarNaApi(tokenDaApiDe(User::factory()->create()))->assertCreated();

    $slug = QrCode::sole()->slug;

    expect(valoresDaRespostaDaApi($resposta->json()))->toContain($slug);
});

it('devolve o URL curto, e não só o slug solto', function () {
    $resposta = criarNaApi(tokenDaApiDe(User::factory()->create()))->assertCreated();

    // É este o endereço que vai para o papel. Quem automatiza não tem de o
    // compor a partir do slug e de um domínio adivinhado.
    expect(valoresDaRespostaDaApi($resposta->json()))
        ->toContain(route('redirect.publico', QrCode::sole()->slug));
});

it('devolve um endereço para o ficheiro PNG e outro para o SVG', function () {
    $resposta = criarNaApi(tokenDaApiDe(User::factory()->create()))->assertCreated();

    $slug = QrCode::sole()->slug;
    $json = $resposta->json();

    expect(urlDoFicheiroNaApi($json, $slug, 'png'))->toStartWith('http')
        ->and(urlDoFicheiroNaApi($json, $slug, 'svg'))->toStartWith('http')
        ->and(urlDoFicheiroNaApi($json, $slug, 'png'))
        ->not->toBe(urlDoFicheiroNaApi($json, $slug, 'svg'));
});

it('entrega mesmo um PNG no endereço que devolveu, a quem tem o token', function () {
    $dono = User::factory()->create();
    $token = tokenDaApiDe($dono);

    $resposta = criarNaApi($token)->assertCreated();

    $url = urlDoFicheiroNaApi($resposta->json(), QrCode::sole()->slug, 'png');

    $ficheiro = seguirComToken($token, $url);

    // Um endereço no JSON que dá 404 — ou que exige a sessão do ecrã — não é um
    // endereço: quem chama a API só tem o token.
    $ficheiro->assertOk();

    expect((string) $ficheiro->headers->get('Content-Type'))->toStartWith('image/png');

    $corpo = corpoDoFicheiroDaApi($ficheiro);

    // A assinatura de um PNG, byte a byte (ISO/IEC 15948, 5.2).
    expect($corpo)->toStartWith("\x89PNG\x0d\x0a\x1a\x0a");

    $informacao = getimagesizefromstring($corpo);

    expect($informacao)->not->toBeFalse()
        ->and($informacao[2])->toBe(IMAGETYPE_PNG);
});

it('entrega mesmo um SVG no endereço que devolveu, a quem tem o token', function () {
    $dono = User::factory()->create();
    $token = tokenDaApiDe($dono);

    $resposta = criarNaApi($token)->assertCreated();

    $url = urlDoFicheiroNaApi($resposta->json(), QrCode::sole()->slug, 'svg');

    $ficheiro = seguirComToken($token, $url);

    $ficheiro->assertOk();

    expect((string) $ficheiro->headers->get('Content-Type'))->toStartWith('image/svg+xml');

    $corpo = corpoDoFicheiroDaApi($ficheiro);

    expect($corpo)->toContain('<svg');

    $anterior = libxml_use_internal_errors(true);
    $documento = simplexml_load_string($corpo);
    libxml_use_internal_errors($anterior);

    expect($documento)->not->toBeFalse()
        ->and($documento->getName())->toBe('svg');
});

it('entrega o ficheiro do código que criou, com o URL curto lá dentro', function () {
    $dono = User::factory()->create();
    $token = tokenDaApiDe($dono);

    $resposta = criarNaApi($token)->assertCreated();

    $slug = QrCode::sole()->slug;
    $url = urlDoFicheiroNaApi($resposta->json(), $slug, 'png');

    $lido = lerPngDaApi(corpoDoFicheiroDaApi(seguirComToken($token, $url)));

    // O que fica no papel é o URL curto do próprio código — nunca o destino,
    // que muda, e nunca o de outro código.
    expect($lido)->toBe(route('redirect.publico', $slug))
        ->not->toContain(DESTINO_NA_API);
});

it('dá a cada código criado o seu próprio slug', function () {
    $token = tokenDaApiDe(User::factory()->create());

    $primeiro = criarNaApi($token, ['nome' => 'Flyer A', 'destino' => DESTINO_NA_API])->assertCreated();
    $segundo = criarNaApi($token, ['nome' => 'Flyer B', 'destino' => DESTINO_NOVO_NA_API])->assertCreated();

    $slugs = QrCode::query()->pluck('slug')->all();

    expect($slugs)->toHaveCount(2)
        ->and(array_unique($slugs))->toHaveCount(2)
        ->and($primeiro->getContent())->not->toBe($segundo->getContent());
});

it('cria o código em nome do dono do token, mesmo que o pedido diga outra coisa', function () {
    $dono = User::factory()->create();
    $vizinho = User::factory()->create();

    criarNaApi(tokenDaApiDe($dono), [
        'nome' => NOME_NA_API,
        'destino' => DESTINO_NA_API,
        'user_id' => $vizinho->id,
    ]);

    expect(QrCode::sole()->user_id)->toBe($dono->id);
});

it('nunca deixa quem chama a API escolher o slug', function () {
    $token = tokenDaApiDe(User::factory()->create());

    $resposta = criarNaApi($token, [
        'nome' => NOME_NA_API,
        'destino' => DESTINO_NA_API,
        'slug' => 'escolhido',
    ]);

    // Recusar o pedido serve; ignorar o campo também. O que não pode acontecer
    // é o slug vir de fora — ele é a única coisa que o papel impresso conhece.
    if ($resposta->status() === 422) {
        expect(QrCode::count())->toBe(0);

        return;
    }

    $resposta->assertCreated();

    expect(QrCode::sole()->slug)->not->toBe('escolhido');
});

// -----------------------------------------------------------------------------
// Critério: «GET /api/qr-codes e GET /api/qr-codes/{slug} devolvem apenas os
// QRs do dono do token».
// -----------------------------------------------------------------------------

it('lista os códigos do dono do token', function () {
    $dono = User::factory()->create();
    $meus = QrCode::factory()->count(3)->for($dono)->create();

    $resposta = listarNaApi(tokenDaApiDe($dono))->assertOk();

    $valores = valoresDaRespostaDaApi($resposta->json());

    foreach ($meus as $qrCode) {
        expect($valores)->toContain($qrCode->slug);
    }
});

it('não deixa passar na listagem nada do vizinho', function () {
    $vizinho = User::factory()->create();
    $doVizinho = QrCode::factory()->for($vizinho)->create([
        'nome' => NOME_DO_VIZINHO_NA_API,
        'destino' => DESTINO_DO_VIZINHO_NA_API,
    ]);

    $dono = User::factory()->create();
    QrCode::factory()->for($dono)->create(['nome' => NOME_NA_API, 'destino' => DESTINO_NA_API]);

    $resposta = listarNaApi(tokenDaApiDe($dono))->assertOk();

    $corpo = (string) $resposta->getContent();

    expect($corpo)->toContain(NOME_NA_API)
        ->not->toContain(NOME_DO_VIZINHO_NA_API)
        ->not->toContain($doVizinho->slug)
        ->not->toContain(DESTINO_DO_VIZINHO_NA_API);
});

it('devolve uma listagem vazia a quem não tem códigos, com os dos outros na base de dados', function () {
    QrCode::factory()->count(2)->create(['nome' => NOME_DO_VIZINHO_NA_API]);

    $resposta = listarNaApi(tokenDaApiDe(User::factory()->create()))->assertOk();

    expect(itensDaListagemDaApi($resposta->json()))->toBeEmpty()
        ->and((string) $resposta->getContent())->not->toContain(NOME_DO_VIZINHO_NA_API);
});

it('deixa o dono ver um código seu pelo slug', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => NOME_NA_API,
        'destino' => DESTINO_NA_API,
    ]);

    $resposta = verNaApi(tokenDaApiDe($dono), $qrCode)->assertOk();

    $valores = valoresDaRespostaDaApi($resposta->json());

    expect($valores)->toContain($qrCode->slug)
        ->toContain(NOME_NA_API)
        ->toContain(DESTINO_NA_API);
});

it('esconde o código do vizinho de quem pede pelo slug', function () {
    $vizinho = User::factory()->create();
    $doVizinho = QrCode::factory()->for($vizinho)->create([
        'nome' => NOME_DO_VIZINHO_NA_API,
        'destino' => DESTINO_DO_VIZINHO_NA_API,
    ]);

    $resposta = verNaApi(tokenDaApiDe(User::factory()->create()), $doVizinho);

    // Um 404 não confirma que o código existe; um 403 confirmaria.
    $resposta->assertNotFound();

    expect((string) $resposta->getContent())->not->toContain(NOME_DO_VIZINHO_NA_API)
        ->not->toContain(DESTINO_DO_VIZINHO_NA_API);
});

it('responde 404 a um slug que não existe', function () {
    verNaApi(tokenDaApiDe(User::factory()->create()), 'zzzzzz')->assertNotFound();
});

it('não entrega o ficheiro de um código do vizinho', function () {
    $doVizinho = QrCode::factory()->create();

    $token = tokenDaApiDe(User::factory()->create());

    seguirComToken($token, route('api.qr-codes.ficheiro', [
        'qrCode' => $doVizinho,
        'formato' => 'png',
    ]))->assertNotFound();
});

it('pagina a listagem em vez de despejar tudo de uma vez', function () {
    $dono = User::factory()->create();
    QrCode::factory()->count(120)->for($dono)->create();

    $token = tokenDaApiDe($dono);

    $primeira = listarNaApi($token)->assertOk();
    $itens = itensDaListagemDaApi($primeira->json());

    expect($itens)->not->toBeEmpty()
        ->and(count($itens))->toBeLessThan(120);

    // O que a primeira página deixou de fora tem de estar alcançável: uma
    // listagem paginada que não deixa chegar ao resto perde códigos.
    $vistos = [];
    $pagina = 1;

    do {
        $itens = itensDaListagemDaApi(listarNaApi($token, ['page' => $pagina])->assertOk()->json());

        foreach ($itens as $item) {
            $vistos = [...$vistos, ...array_intersect(
                valoresDaRespostaDaApi($item),
                QrCode::query()->pluck('slug')->all()
            )];
        }

        $pagina++;
    } while ($itens !== [] && $pagina <= 30);

    expect(array_unique($vistos))->toHaveCount(120);
});

// -----------------------------------------------------------------------------
// Critério: «PATCH /api/qr-codes/{slug} altera o destino; tentar alterar o slug
// devolve 422».
// -----------------------------------------------------------------------------

it('altera o destino de um código', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => DESTINO_NOVO_NA_API])->assertOk();

    expect($qrCode->fresh()->destino)->toBe(DESTINO_NOVO_NA_API);
});

it('devolve o novo destino na resposta ao PATCH', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);

    $resposta = alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => DESTINO_NOVO_NA_API])->assertOk();

    expect(valoresDaRespostaDaApi($resposta->json()))->toContain(DESTINO_NOVO_NA_API)
        ->not->toContain(DESTINO_NA_API);
});

it('não mexe no slug ao alterar o destino', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);
    $slug = $qrCode->slug;

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => DESTINO_NOVO_NA_API])->assertOk();

    // Há papel impresso lá fora a apontar para este slug.
    expect($qrCode->fresh()->slug)->toBe($slug);
});

it('recusa com 422 tentar alterar o slug', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();
    $slug = $qrCode->slug;

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['slug' => 'novosl'])
        ->assertStatus(422);

    expect($qrCode->fresh()->slug)->toBe($slug);
});

it('não grava nada quando o pedido traz o slug junto com o destino', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);
    $slug = $qrCode->slug;

    alterarNaApi(tokenDaApiDe($dono), $qrCode, [
        'destino' => DESTINO_NOVO_NA_API,
        'slug' => 'novosl',
    ])->assertStatus(422);

    // O pedido inteiro é recusado: metade gravada era pior do que nada.
    $recarregado = $qrCode->fresh();

    expect($recarregado->slug)->toBe($slug)
        ->and($recarregado->destino)->toBe(DESTINO_NA_API);
});

it('recusa um destino inválido no PATCH sem estragar o que lá estava', function (string $destino) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => $destino])
        ->assertStatus(422)
        ->assertJsonValidationErrors('destino');

    expect($qrCode->fresh()->destino)->toBe(DESTINO_NA_API);
})->with([
    'vazio' => '',
    'relativo' => '/campanhas/setembro',
    'sem esquema' => 'loja.exemplo.pt/campanha',
    'ftp' => 'ftp://loja.exemplo.pt/ficheiro',
    'javascript' => 'javascript:alert(1)',
]);

it('recusa apontar o destino para o próprio sistema', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => DESTINO_NA_API]);

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => enderecoDaCasaNaApi($qrCode->slug)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('destino');

    expect($qrCode->fresh()->destino)->toBe(DESTINO_NA_API);
});

it('deixa tudo como estava quando o PATCH repete o destino actual', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => NOME_NA_API,
        'destino' => DESTINO_NA_API,
    ]);

    alterarNaApi(tokenDaApiDe($dono), $qrCode, ['destino' => DESTINO_NA_API])->assertOk();

    $recarregado = $qrCode->fresh();

    expect($recarregado->slug)->toBe($qrCode->slug)
        ->and($recarregado->nome)->toBe(NOME_NA_API)
        ->and($recarregado->destino)->toBe(DESTINO_NA_API)
        ->and($recarregado->activo)->toBe($qrCode->activo)
        ->and(QrCode::count())->toBe(1);
});

it('não deixa alterar o destino de um código do vizinho', function () {
    $doVizinho = QrCode::factory()->create(['destino' => DESTINO_DO_VIZINHO_NA_API]);

    alterarNaApi(tokenDaApiDe(User::factory()->create()), $doVizinho, ['destino' => DESTINO_NOVO_NA_API])
        ->assertNotFound();

    expect($doVizinho->fresh()->destino)->toBe(DESTINO_DO_VIZINHO_NA_API);
});

// -----------------------------------------------------------------------------
// Critério: «pedido sem token ou com token revogado devolve 401».
// -----------------------------------------------------------------------------

it('recusa com 401 o pedido sem token', function (string $endpoint) {
    $qrCode = QrCode::factory()->create();

    [$metodo, $url, $dados] = pedidoDaApiPara($endpoint, $qrCode);

    pedidoNaApi(null, $metodo, $url, $dados)->assertUnauthorized();
})->with('endpoints da api');

it('recusa com 401 o pedido com um token revogado', function (string $endpoint) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    $token = tokenDaApiDe($dono);
    $emitido = $dono->tokens()->sole();

    [$metodo, $url, $dados] = pedidoDaApiPara($endpoint, $qrCode);

    $emitido->forceFill(['revogado_em' => now()])->save();

    pedidoNaApi($token, $metodo, $url, $dados)->assertUnauthorized();
})->with('endpoints da api');

it('recusa com 401 um token inventado', function (string $endpoint) {
    $qrCode = QrCode::factory()->create();

    [$metodo, $url, $dados] = pedidoDaApiPara($endpoint, $qrCode);

    pedidoNaApi('qrm_estoutokennuncafoiemitidoporninguem', $metodo, $url, $dados)->assertUnauthorized();
})->with('endpoints da api');

it('não grava nada quando o pedido de criação vem sem token', function () {
    criarNaApi(null)->assertUnauthorized();

    expect(QrCode::count())->toBe(0);
});

it('responde ao 401 em JSON, e não com uma página de login', function () {
    $resposta = listarNaApi(null)->assertUnauthorized();

    expect((string) $resposta->headers->get('Content-Type'))->toContain('application/json')
        ->and((string) $resposta->getContent())->not->toContain('<html');
});

// -----------------------------------------------------------------------------
// Critério: «existe rate limit por token, com teste que o prova».
// -----------------------------------------------------------------------------

it('corta com 429 os pedidos que passam do limite do token', function () {
    $dono = User::factory()->create();
    $token = tokenDaApiDe($dono);

    $primeira = listarNaApi($token)->assertOk();

    $limite = (int) $primeira->headers->get('X-RateLimit-Limit');

    expect($limite)->toBeGreaterThan(0)
        ->and($limite)->toBeLessThan(500, 'Um limite tão alto não trava ninguém.');

    // Gastar o resto do balde. O primeiro pedido já foi.
    for ($feitos = 1; $feitos < $limite; $feitos++) {
        listarNaApi($token)->assertOk();
    }

    $cortada = listarNaApi($token);

    $cortada->assertStatus(429);

    expect((string) $cortada->headers->get('Content-Type'))->toContain('application/json');
});

it('dá a cada token o seu próprio balde, e não um por IP', function () {
    $dono = User::factory()->create();
    $vizinho = User::factory()->create();

    $gastado = tokenDaApiDe($dono, 'Importador nocturno');
    $outroDoMesmoDono = tokenDaApiDe($dono, 'Sincronização do catálogo');
    $doVizinho = tokenDaApiDe($vizinho);

    $limite = (int) listarNaApi($gastado)->assertOk()->headers->get('X-RateLimit-Limit');

    expect($limite)->toBeGreaterThan(0);

    for ($feitos = 1; $feitos < $limite; $feitos++) {
        listarNaApi($gastado)->assertOk();
    }

    listarNaApi($gastado)->assertStatus(429);

    // Duas ferramentas do mesmo cliente saem do mesmo IP. Uma que se descontrola
    // não pode levar a outra com ela — nem a de outro cliente.
    $segundo = listarNaApi($outroDoMesmoDono)->assertOk();
    $terceiro = listarNaApi($doVizinho)->assertOk();

    expect((int) $segundo->headers->get('X-RateLimit-Remaining'))->toBe($limite - 1)
        ->and((int) $terceiro->headers->get('X-RateLimit-Remaining'))->toBe($limite - 1);
});

it('trava com 429 quem martela a porta sem token nenhum', function () {
    for ($feitos = 0; $feitos < LimitarPedidosDaApiPorIp::POR_MINUTO; $feitos++) {
        listarNaApi(null)->assertUnauthorized();
    }

    $cortada = listarNaApi(null);

    // Sem token o pedido leva 401 de qualquer forma; o que não pode é levá-lo
    // um milhão de vezes, cada uma a custar a procura do hash de um token.
    $cortada->assertStatus(429);

    expect((string) $cortada->headers->get('Content-Type'))->toContain('application/json')
        ->and((string) $cortada->getContent())->not->toContain('<html');
});

it('dá a cada IP o seu próprio balde à porta da API', function () {
    for ($feitos = 0; $feitos < LimitarPedidosDaApiPorIp::POR_MINUTO; $feitos++) {
        listarNaApi(null)->assertUnauthorized();
    }

    listarNaApi(null)->assertStatus(429);

    pedidosDaApiVindosDe('203.0.113.7');

    // O balde é de quem martela, não da porta: um IP descontrolado não pode
    // fechar a API a toda a gente.
    listarNaApi(null)->assertUnauthorized();
});

// -----------------------------------------------------------------------------
// Validação e forma das respostas.
// -----------------------------------------------------------------------------

it('recusa criar um código sem nome', function () {
    $token = tokenDaApiDe(User::factory()->create());

    criarNaApi($token, ['destino' => DESTINO_NA_API])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nome');

    expect(QrCode::count())->toBe(0);
});

it('recusa criar um código sem destino', function () {
    $token = tokenDaApiDe(User::factory()->create());

    criarNaApi($token, ['nome' => NOME_NA_API])
        ->assertStatus(422)
        ->assertJsonValidationErrors('destino');

    expect(QrCode::count())->toBe(0);
});

it('recusa criar um código com um destino que não é http nem https', function (string $destino) {
    $token = tokenDaApiDe(User::factory()->create());

    criarNaApi($token, ['nome' => NOME_NA_API, 'destino' => $destino])
        ->assertStatus(422)
        ->assertJsonValidationErrors('destino');

    expect(QrCode::count())->toBe(0);
})->with([
    'relativo' => '/campanhas/setembro',
    'sem esquema' => 'loja.exemplo.pt/campanha',
    'ftp' => 'ftp://loja.exemplo.pt/ficheiro',
    'javascript' => 'javascript:alert(1)',
    'mailto' => 'mailto:alguem@exemplo.pt',
    'data' => 'data:text/html,<h1>ola</h1>',
    'texto' => 'isto não é um endereço',
]);

it('recusa criar um código cujo destino é do próprio sistema', function () {
    $token = tokenDaApiDe(User::factory()->create());

    // Um código que aponta para outro código entra em ciclo e nunca chega a
    // lado nenhum.
    criarNaApi($token, ['nome' => NOME_NA_API, 'destino' => enderecoDaCasaNaApi()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('destino');

    expect(QrCode::count())->toBe(0);
});

it('aceita destinos http e https', function (string $destino) {
    $token = tokenDaApiDe(User::factory()->create());

    criarNaApi($token, ['nome' => NOME_NA_API, 'destino' => $destino])->assertCreated();

    expect(QrCode::sole()->destino)->toBe($destino);
})->with([
    'https' => 'https://loja.exemplo.pt/campanha',
    'http' => 'http://loja.exemplo.pt/campanha',
    'com query' => 'https://loja.exemplo.pt/campanha?utm_source=flyer',
]);

it('devolve o erro de validação em JSON, e não numa página de erro', function () {
    $token = tokenDaApiDe(User::factory()->create());

    $resposta = criarNaApi($token, ['nome' => '', 'destino' => ''])->assertStatus(422);

    expect((string) $resposta->headers->get('Content-Type'))->toContain('application/json')
        ->and((string) $resposta->getContent())->not->toContain('<html')
        ->and($resposta->json())->toHaveKeys(['message', 'errors']);
});

it('responde sempre em JSON, e nunca com uma view', function (string $endpoint) {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create();

    [$metodo, $url, $dados] = pedidoDaApiPara($endpoint, $qrCode);

    $resposta = pedidoNaApi(tokenDaApiDe($dono), $metodo, $url, $dados);

    expect((string) $resposta->getContent())->not->toContain('<html');
})->with(['listar', 'ver', 'criar', 'alterar']);

it('devolve o 404 em JSON', function () {
    $resposta = verNaApi(tokenDaApiDe(User::factory()->create()), 'zzzzzz')->assertNotFound();

    expect((string) $resposta->headers->get('Content-Type'))->toContain('application/json')
        ->and((string) $resposta->getContent())->not->toContain('<html');
});
