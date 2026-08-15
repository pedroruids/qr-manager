<?php

use App\Models\QrCode;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// =============================================================================
// Issue #7 — Ecrã: criar QR
// =============================================================================

/**
 * Um destino plausível e inequivocamente externo: nada nele se parece com o
 * domínio da aplicação, para que nenhuma asserção o apanhe por acaso.
 */
const DESTINO_EXTERNO = 'https://loja.exemplo.pt/campanhas/setembro';

/**
 * A mensagem que o mockup aprovado exige quando o destino aponta para o próprio
 * sistema — `docs/mockups/criar-qr.html`, estado 3, «variante do mesmo estado:
 * destino em loop». Não é uma mensagem genérica de validação: explica o ciclo.
 */
const MENSAGEM_DESTINO_EM_LOOP = 'Este endereço pertence ao qr-manager. Um código que aponta para outro código '
    .'entra em ciclo e nunca chega a lado nenhum. Indique o endereço final.';

function ecraDeCriar(?User $utilizador = null): Testable
{
    test()->actingAs($utilizador ?? User::factory()->create());

    return Livewire::test('pages::codigos.criar');
}

/**
 * O formulário preenchido de raiz, para depois se estragar só o campo em causa.
 */
function formularioPreenchido(?User $utilizador = null): Testable
{
    return ecraDeCriar($utilizador)
        ->set('nome', 'Flyer Setembro')
        ->set('destino', DESTINO_EXTERNO);
}

/**
 * Um endereço do próprio sistema, construído a partir da configuração e nunca
 * de um domínio escrito à mão: é o domínio onde o redirect público vive.
 */
function enderecoDoProprioSistema(string $caminho = '/abc123'): string
{
    return rtrim((string) config('app.url'), '/').'/'.ltrim($caminho, '/');
}

/**
 * Compara textos ignorando diferenças de espaços e de quebras de linha — o
 * mockup parte a frase em três linhas, o código-fonte não tem de a partir.
 */
function textoNormalizado(string $texto): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

// -----------------------------------------------------------------------------
// Quem chega ao ecrã.
// -----------------------------------------------------------------------------

it('deixa um utilizador autenticado abrir o ecrã de criar', function () {
    $this->actingAs(User::factory()->create())->get(route('codigos.criar'))->assertOk();
});

it('manda o anónimo para o login em vez de lhe mostrar o ecrã de criar', function () {
    $this->get(route('codigos.criar'))->assertRedirect(route('login'));
});

it('não deixa um anónimo criar um código', function () {
    $this->get(route('codigos.criar'));

    expect(QrCode::count())->toBe(0);
});

// -----------------------------------------------------------------------------
// Critério: «nome obrigatório; destino obrigatório e validado como URL
// http/https».
// -----------------------------------------------------------------------------

it('recusa gravar sem nome', function () {
    formularioPreenchido()
        ->set('nome', '')
        ->call('criar')
        ->assertHasErrors(['nome']);

    expect(QrCode::count())->toBe(0);
});

it('recusa gravar com um nome só de espaços', function () {
    formularioPreenchido()
        ->set('nome', '   ')
        ->call('criar')
        ->assertHasErrors(['nome']);

    expect(QrCode::count())->toBe(0);
});

it('recusa gravar sem destino', function () {
    formularioPreenchido()
        ->set('destino', '')
        ->call('criar')
        ->assertHasErrors(['destino']);

    expect(QrCode::count())->toBe(0);
});

it('recusa um destino que não seja um URL http ou https', function (string $destino) {
    formularioPreenchido()
        ->set('destino', $destino)
        ->call('criar')
        ->assertHasErrors(['destino']);

    expect(QrCode::count())->toBe(0);
})->with([
    'relativo' => '/campanhas/setembro',
    'sem esquema' => 'loja.exemplo.pt/campanha',
    'só o domínio' => 'loja.exemplo.pt',
    'ftp' => 'ftp://loja.exemplo.pt/ficheiro',
    'javascript' => 'javascript:alert(1)',
    'mailto' => 'mailto:alguem@exemplo.pt',
    'file' => 'file:///etc/passwd',
    'data' => 'data:text/html,<h1>ola</h1>',
    'texto' => 'isto não é um endereço',
    'esquema sem anfitrião' => 'https://',
]);

it('aceita destinos http e https', function (string $destino) {
    formularioPreenchido()
        ->set('destino', $destino)
        ->call('criar')
        ->assertHasNoErrors();

    expect(QrCode::sole()->destino)->toBe($destino);
})->with([
    'https' => 'https://loja.exemplo.pt/campanha',
    'http' => 'http://loja.exemplo.pt',
    'com parâmetros' => 'https://loja.exemplo.pt/campanhas/setembro?utm_source=flyer&utm_medium=qr',
    'com porta' => 'https://loja.exemplo.pt:8443/campanha',
    'com âncora' => 'https://loja.exemplo.pt/campanha#preco',
]);

// -----------------------------------------------------------------------------
// Critério: «destino que aponte para o domínio do próprio redirect é recusado
// com mensagem clara».
// -----------------------------------------------------------------------------

it('recusa um destino que aponta para o endereço curto de um código já existente', function () {
    $dono = User::factory()->create();
    $existente = QrCode::factory()->for($dono)->create();

    formularioPreenchido($dono)
        ->set('destino', route('redirect.publico', $existente->slug))
        ->call('criar')
        ->assertHasErrors(['destino']);

    // O código existente continua a ser o único: nada foi gravado.
    expect(QrCode::count())->toBe(1);
});

it('explica o ciclo, com as palavras do mockup, quando o destino é do próprio sistema', function () {
    $erros = formularioPreenchido()
        ->set('destino', enderecoDoProprioSistema())
        ->call('criar')
        ->assertHasErrors(['destino'])
        ->errors();

    $mensagens = array_map(textoNormalizado(...), $erros->get('destino'));

    expect($mensagens)->toContain(textoNormalizado(MENSAGEM_DESTINO_EM_LOOP));
});

it('não despacha o destino em loop com a mensagem genérica de endereço inválido', function () {
    $erros = formularioPreenchido()
        ->set('destino', enderecoDoProprioSistema())
        ->call('criar')
        ->errors();

    $mensagens = textoNormalizado(implode(' ', $erros->get('destino')));

    // Quem escreveu um endereço bem formado não pode ser mandado corrigir o
    // «início do endereço»: o problema não é a forma, é o ciclo.
    expect($mensagens)->not->toContain('Falta o início do endereço')
        ->and($mensagens)->toContain('ciclo');
});

it('recusa qualquer endereço do próprio domínio, não apenas o de um slug', function (string $caminho) {
    formularioPreenchido()
        ->set('destino', enderecoDoProprioSistema($caminho))
        ->call('criar')
        ->assertHasErrors(['destino']);

    expect(QrCode::count())->toBe(0);
})->with([
    'raiz' => '/',
    'slug' => '/abc123',
    'slug que não existe' => '/zzzzzz',
    'ecrã interno' => '/codigos/criar',
    'com parâmetros' => '/abc123?utm_source=qr',
]);

it('recusa o próprio domínio escrito de outra maneira', function (callable $destino) {
    formularioPreenchido()
        ->set('destino', $destino())
        ->call('criar')
        ->assertHasErrors(['destino']);

    expect(QrCode::count())->toBe(0);
})->with([
    // O anfitrião de um URL não distingue maiúsculas de minúsculas.
    'anfitrião em maiúsculas' => fn () => (fn (array $partes) => $partes['scheme'].'://'.Str::upper($partes['host']).'/abc123')(
        parse_url((string) config('app.url')) + ['scheme' => 'http', 'host' => 'localhost']
    ),
    // Trocar o esquema não muda o sítio para onde o endereço leva.
    'esquema trocado' => fn () => (fn (array $partes) => ($partes['scheme'] === 'https' ? 'http' : 'https').'://'.$partes['host'].'/abc123')(
        parse_url((string) config('app.url')) + ['scheme' => 'http', 'host' => 'localhost']
    ),
]);

it('continua a aceitar um domínio que apenas se parece com o do sistema', function () {
    $anfitriao = parse_url((string) config('app.url'), PHP_URL_HOST);
    $destino = 'https://'.$anfitriao.'.exemplo.pt/campanha';

    formularioPreenchido()
        ->set('destino', $destino)
        ->call('criar')
        ->assertHasNoErrors();

    expect(QrCode::sole()->destino)->toBe($destino);
});

// -----------------------------------------------------------------------------
// Critério: «após gravar, o utilizador vai para o detalhe do QR criado».
// -----------------------------------------------------------------------------

it('leva o utilizador ao detalhe do código acabado de criar', function () {
    $componente = formularioPreenchido();

    $componente->call('criar')->assertHasNoErrors();

    $criado = QrCode::sole();

    $componente->assertRedirect(route('codigos.detalhe', $criado));
});

it('grava o código com o nome e o destino escritos', function () {
    formularioPreenchido()
        ->set('nome', 'Cartaz A2 — Feira do Livro')
        ->set('destino', DESTINO_EXTERNO)
        ->call('criar');

    $criado = QrCode::sole();

    expect($criado->nome)->toBe('Cartaz A2 — Feira do Livro')
        ->and($criado->destino)->toBe(DESTINO_EXTERNO);
});

it('grava o código em nome de quem estava autenticado', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();

    formularioPreenchido($dono)->call('criar');

    expect(QrCode::sole()->user_id)->toBe($dono->id)
        ->and(QrCode::sole()->user_id)->not->toBe($outro->id);
});

it('deixa o dono abrir o detalhe do código que acabou de criar', function () {
    $dono = User::factory()->create();

    formularioPreenchido($dono)->call('criar');

    $this->actingAs($dono)->get(route('codigos.detalhe', QrCode::sole()))->assertOk();
});

it('cria o código activo, pronto a redireccionar', function () {
    formularioPreenchido()->call('criar');

    expect(QrCode::sole()->activo)->toBeTrue();
});

it('grava um código de cada vez que se submete o formulário', function () {
    formularioPreenchido()->call('criar');

    expect(QrCode::count())->toBe(1);
});

// -----------------------------------------------------------------------------
// Critério: «erros de validação aparecem junto ao campo respectivo».
// -----------------------------------------------------------------------------

it('associa os erros aos campos e não a um erro geral', function () {
    ecraDeCriar()
        ->set('nome', '')
        ->set('destino', '')
        ->call('criar')
        ->assertOnlyHasErrors(['nome', 'destino']);
});

it('assinala só o campo errado quando o outro está bem preenchido', function () {
    formularioPreenchido()
        ->set('destino', 'loja.exemplo.pt/campanha')
        ->call('criar')
        ->assertHasErrors(['destino'])
        ->assertHasNoErrors(['nome']);

    formularioPreenchido()
        ->set('nome', '')
        ->call('criar')
        ->assertHasErrors(['nome'])
        ->assertHasNoErrors(['destino']);
});

it('mantém o utilizador no formulário quando a validação falha', function () {
    ecraDeCriar()
        ->set('nome', '')
        ->set('destino', 'loja.exemplo.pt/campanha')
        ->call('criar')
        ->assertNoRedirect();
});

it('não perde o que já estava escrito quando a validação falha', function () {
    ecraDeCriar()
        ->set('nome', 'Flyer Setembro')
        ->set('destino', 'loja.exemplo.pt/campanha')
        ->call('criar')
        ->assertSet('nome', 'Flyer Setembro')
        ->assertSet('destino', 'loja.exemplo.pt/campanha');
});

it('mostra a falta do nome com as palavras do mockup', function () {
    $erros = ecraDeCriar()
        ->set('nome', '')
        ->set('destino', DESTINO_EXTERNO)
        ->call('criar')
        ->errors();

    $mensagens = array_map(textoNormalizado(...), $erros->get('nome'));

    expect($mensagens)->toContain('Dê um nome ao código para o reconhecer na lista.');
});

it('escreve as mensagens de erro em português', function (string $campo, string $valor) {
    $erros = formularioPreenchido()
        ->set($campo, $valor)
        ->call('criar')
        ->errors();

    $mensagens = implode(' ', $erros->all());

    expect($mensagens)->not->toBeEmpty();

    // A aplicação fala pt-PT. Uma mensagem por omissão do Laravel — em inglês e
    // a citar o nome do campo — é um defeito, não uma questão de gosto.
    foreach (['field', 'must be', 'is required', 'valid URL', 'The nome', 'The destino'] as $indicioDeIngles) {
        expect(Str::lower($mensagens))->not->toContain(Str::lower($indicioDeIngles));
    }
})->with([
    'nome em falta' => ['nome', ''],
    'destino em falta' => ['destino', ''],
    'destino sem esquema' => ['destino', 'loja.exemplo.pt/campanha'],
    'destino com esquema errado' => ['destino', 'ftp://loja.exemplo.pt/ficheiro'],
    'nome longo de mais' => ['nome', str_repeat('a', 256)],
]);

// -----------------------------------------------------------------------------
// Limites: nome, destino e espaços.
// -----------------------------------------------------------------------------

it('aceita um nome com 255 caracteres', function () {
    formularioPreenchido()
        ->set('nome', str_repeat('a', 255))
        ->call('criar')
        ->assertHasNoErrors();

    expect(QrCode::sole()->nome)->toHaveLength(255);
});

it('recusa um nome com 256 caracteres', function () {
    formularioPreenchido()
        ->set('nome', str_repeat('a', 256))
        ->call('criar')
        ->assertHasErrors(['nome']);

    expect(QrCode::count())->toBe(0);
});

it('aceita um destino com 2048 caracteres', function () {
    $prefixo = 'https://loja.exemplo.pt/';
    $destino = $prefixo.str_repeat('a', 2048 - strlen($prefixo));

    expect($destino)->toHaveLength(2048);

    formularioPreenchido()
        ->set('destino', $destino)
        ->call('criar')
        ->assertHasNoErrors();

    expect(QrCode::sole()->destino)->toBe($destino);
});

it('recusa um destino com mais de 2048 caracteres', function () {
    $destino = 'https://loja.exemplo.pt/'.str_repeat('a', 2048);

    formularioPreenchido()
        ->set('destino', $destino)
        ->call('criar')
        ->assertHasErrors(['destino']);

    expect(QrCode::count())->toBe(0);
});

it('grava o destino sem os espaços colados por quem o colou', function () {
    formularioPreenchido()
        ->set('destino', '  '.DESTINO_EXTERNO.'  ')
        ->call('criar')
        ->assertHasNoErrors();

    expect(QrCode::sole()->destino)->toBe(DESTINO_EXTERNO);
});

// -----------------------------------------------------------------------------
// Fora de âmbito do issue, e por isso mesmo a testar: escolher o slug.
// -----------------------------------------------------------------------------

it('gera o slug e ignora qualquer slug que o utilizador tente enviar', function () {
    $componente = formularioPreenchido();

    // Um slug escolhido não tem de ser recusado com erro: tem de não existir.
    // Se o componente nem sequer aceita a propriedade, melhor ainda.
    try {
        $componente->set('slug', 'aaaaaa');
    } catch (Throwable) {
        // O componente não expõe o slug. É o comportamento desejado.
    }

    $componente->call('criar');

    expect(QrCode::sole()->slug)->not->toBe('aaaaaa');
});

it('gera um slug com o alfabeto e o comprimento do modelo', function () {
    formularioPreenchido()->call('criar');

    $slug = QrCode::sole()->slug;

    expect($slug)->toHaveLength(QrCode::COMPRIMENTO_SLUG)
        ->and($slug)->toMatch('/^['.QrCode::ALFABETO_SLUG.']+$/');
});

it('dá slugs diferentes a dois códigos criados pelo mesmo utilizador', function () {
    $dono = User::factory()->create();

    formularioPreenchido($dono)->set('nome', 'Primeiro')->call('criar');
    formularioPreenchido($dono)->set('nome', 'Segundo')->call('criar');

    $slugs = QrCode::pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});
