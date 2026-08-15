<?php

use App\Models\QrCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Features\SupportRedirects\SupportRedirects;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// =============================================================================
// Issue #10 — Ecrã: editar QR (nome, destino, activo)
// =============================================================================

/**
 * O destino com que o código foi impresso, e o destino novo. Nenhum deles se
 * parece com o domínio da aplicação nem com o outro, para que nenhuma asserção
 * apanhe um pelo outro.
 */
const DESTINO_ANTERIOR = 'https://loja.exemplo.pt/campanhas/verao-2026';

const DESTINO_NOVO = 'https://loja.exemplo.pt/campanhas/outono-2026';

/**
 * O slug que está impresso no papel — o do estado 4 do mockup. Fixo e
 * reconhecível: nenhuma asserção o confunde com um valor gerado ao acaso.
 */
const SLUG_IMPRESSO = 'k7m2qp';

/**
 * A mensagem que o mockup de criar exige quando o destino aponta para o próprio
 * sistema. O ciclo é o mesmo quando se edita: quem mudar o destino de um código
 * para o endereço curto de outro fica com papel impresso que não leva a lado
 * nenhum.
 */
const MENSAGEM_DE_LOOP_AO_EDITAR = 'Este endereço pertence ao qr-manager. Um código que aponta para outro código '
    .'entra em ciclo e nunca chega a lado nenhum. Indique o endereço final.';

function ecraDeEditar(QrCode $qrCode, ?User $utilizador = null): Testable
{
    test()->actingAs($utilizador ?? $qrCode->user);

    return Livewire::test('pages::codigos.editar', ['qrCode' => $qrCode]);
}

/**
 * Um código impresso, com dono, slug e destino conhecidos.
 *
 * @param  array<string, mixed>  $atributos
 */
function codigoImpresso(User $dono, array $atributos = []): QrCode
{
    return QrCode::factory()->for($dono)->create([
        'nome' => 'Flyer Setembro — regresso às aulas',
        'destino' => DESTINO_ANTERIOR,
        'slug' => SLUG_IMPRESSO,
        ...$atributos,
    ]);
}

/**
 * Um endereço do próprio sistema, construído a partir da configuração e nunca
 * de um domínio escrito à mão: é o domínio onde o redirect público vive.
 */
function enderecoDaCasa(string $caminho = '/abc123'): string
{
    return rtrim((string) config('app.url'), '/').'/'.ltrim($caminho, '/');
}

/**
 * Compara textos ignorando diferenças de espaços e de quebras de linha — o
 * mockup parte as frases em várias linhas, o código-fonte não tem de as partir.
 */
function semEspacosARepetir(string $texto): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

/**
 * O texto que sobra do ecrã depois de lhe tirar a marcação: é o que quem está a
 * ler vê, sem depender de onde a implementação pôs os `<span>`.
 */
function textoDoEcraDeEditar(string $html): string
{
    return semEspacosARepetir(html_entity_decode(strip_tags($html)));
}

/**
 * Devolve ao contentor o `Redirector` do Laravel.
 *
 * O Livewire põe o seu de lado durante um pedido e só o repõe no fim; quando a
 * chamada rebenta a meio — que é exactamente o que se quer provar nos pedidos
 * forjados — o dele fica lá, e o pedido HTTP seguinte do mesmo teste apanhava-o.
 * É um efeito do banco de ensaio, não do produto.
 */
function devolverORedirectorDoLaravel(): void
{
    if (SupportRedirects::$redirectorCacheStack !== []) {
        app()->instance('redirect', array_pop(SupportRedirects::$redirectorCacheStack));
    }
}

/**
 * Todas as marcas de campo de formulário do ecrã — só estas contam para saber
 * se um valor é submetível. O slug aparece no ecrã em texto, e isso é o que o
 * mockup pede; o que não pode existir é um campo que o envie de volta.
 *
 * @return list<string>
 */
function camposDeFormulario(string $html): array
{
    preg_match_all('/<(?:input|textarea|select)\b[^>]*>/i', $html, $partes);

    return $partes[0];
}

// -----------------------------------------------------------------------------
// Critério: «só o dono edita».
// -----------------------------------------------------------------------------

it('deixa o dono abrir o ecrã de editar o seu código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $this->actingAs($dono)->get(route('codigos.editar', $qrCode))->assertOk();
});

it('recusa o ecrã de editar a outro utilizador autenticado', function () {
    $qrCode = codigoImpresso(User::factory()->create());
    $intruso = User::factory()->create();

    $this->actingAs($intruso)->get(route('codigos.editar', $qrCode))->assertNotFound();
});

it('não revela nada do código a quem não é o dono', function () {
    $qrCode = codigoImpresso(User::factory()->create(), [
        'nome' => 'Cartaz Confidencial',
        'destino' => 'https://loja.exemplo.pt/segredo',
    ]);
    $intruso = User::factory()->create();

    $resposta = $this->actingAs($intruso)->get(route('codigos.editar', $qrCode));

    $resposta->assertDontSee('Cartaz Confidencial');
    $resposta->assertDontSee('loja.exemplo.pt/segredo');
});

it('não deixa outro utilizador gravar alterações no código alheio', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    $intruso = User::factory()->create();

    try {
        ecraDeEditar($qrCode, $intruso)
            ->set('nome', 'Roubado')
            ->set('destino', 'https://intruso.exemplo.pt/campanha')
            ->set('activo', false)
            ->call('guardar');
    } catch (Throwable) {
        // Recusar já ao montar o componente é a melhor das defesas possíveis.
    }

    $fresco = $qrCode->fresh();

    expect($fresco->nome)->toBe('Flyer Setembro — regresso às aulas')
        ->and($fresco->destino)->toBe(DESTINO_ANTERIOR)
        ->and($fresco->activo)->toBeTrue();
});

it('manda o anónimo para o login em vez de lhe mostrar o ecrã de editar', function () {
    $qrCode = codigoImpresso(User::factory()->create());

    $this->get(route('codigos.editar', $qrCode))->assertRedirect(route('login'));
});

it('não deixa um anónimo alterar nada', function () {
    $qrCode = codigoImpresso(User::factory()->create());

    $this->get(route('codigos.editar', $qrCode));

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
});

// -----------------------------------------------------------------------------
// Critério: «editar o destino não altera o slug».
// -----------------------------------------------------------------------------

it('mantém o slug intacto depois de mudar o destino', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', DESTINO_NOVO)
        ->call('guardar')
        ->assertHasNoErrors();

    $fresco = $qrCode->fresh();

    expect($fresco->destino)->toBe(DESTINO_NOVO)
        ->and($fresco->slug)->toBe(SLUG_IMPRESSO);
});

it('mantém o slug intacto depois de mudar o nome e o estado', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', 'Cartaz A2 — Feira do Livro')
        ->set('activo', false)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($qrCode->fresh()->slug)->toBe(SLUG_IMPRESSO);
});

it('deixa o mesmo endereço impresso a levar ao novo destino', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $antes = $this->get('/'.SLUG_IMPRESSO);
    expect($antes->headers->get('Location'))->toBe(DESTINO_ANTERIOR);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', DESTINO_NOVO)
        ->call('guardar')
        ->assertHasNoErrors();

    // É esta a razão de existir do produto: o papel não muda, o destino muda.
    $depois = $this->get('/'.SLUG_IMPRESSO);

    $depois->assertStatus(302);
    expect($depois->headers->get('Location'))->toBe(DESTINO_NOVO);
});

it('não cria um código novo quando se edita o que já existe', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)->set('destino', DESTINO_NOVO)->call('guardar');

    expect(QrCode::count())->toBe(1)
        ->and(QrCode::sole()->id)->toBe($qrCode->id);
});

it('não mexe nos outros códigos do mesmo dono', function () {
    $dono = User::factory()->create();
    $editado = codigoImpresso($dono);
    $outro = QrCode::factory()->for($dono)->create(['destino' => 'https://loja.exemplo.pt/outro']);

    ecraDeEditar($editado, $dono)
        ->set('destino', DESTINO_NOVO)
        ->set('activo', false)
        ->call('guardar');

    $frescoDoOutro = $outro->fresh();

    expect($frescoDoOutro->destino)->toBe('https://loja.exemplo.pt/outro')
        ->and($frescoDoOutro->activo)->toBeTrue()
        ->and($frescoDoOutro->slug)->toBe($outro->slug);
});

// -----------------------------------------------------------------------------
// Critério: «o campo do slug não é editável nem submetível — alterá-lo por POST
// forjado não tem efeito». O mockup é explícito: o endereço do código aparece em
// texto, «nem desactivado, porque um input desactivado sugere que algures pode
// ser activado».
// -----------------------------------------------------------------------------

it('não põe no ecrã nenhum campo de formulário para o slug', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $html = (string) $this->actingAs($dono)->get(route('codigos.editar', $qrCode))->getContent();

    // Salvaguarda: se o ecrã viesse vazio, as asserções abaixo passavam sem
    // provar nada. O endereço impresso tem de estar lá, em texto.
    expect($html)->toContain(SLUG_IMPRESSO);

    // Sem campos nenhuns o ciclo abaixo não provava nada: o nome e o destino
    // são campos, e têm de estar lá.
    expect(camposDeFormulario($html))->not->toBeEmpty();

    foreach (camposDeFormulario($html) as $campo) {
        expect(Str::lower($campo))->not->toContain('slug')
            ->and($campo)->not->toContain(SLUG_IMPRESSO);
    }
});

it('não põe nenhum campo de slug no que o componente devolve', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $html = ecraDeEditar($qrCode, $dono)->html();

    expect($html)->toContain(SLUG_IMPRESSO);

    // Sem campos nenhuns o ciclo abaixo não provava nada: o nome e o destino
    // são campos, e têm de estar lá.
    expect(camposDeFormulario($html))->not->toBeEmpty();

    foreach (camposDeFormulario($html) as $campo) {
        expect(Str::lower($campo))->not->toContain('slug')
            ->and($campo)->not->toContain(SLUG_IMPRESSO);
    }
});

it('mostra o endereço do código em leitura apenas, com a explicação do mockup', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $html = (string) $this->actingAs($dono)->get(route('codigos.editar', $qrCode))->getContent();

    expect($html)->toContain(route('redirect.publico', SLUG_IMPRESSO))
        ->and(textoDoEcraDeEditar($html))
        ->toContain('Não pode ser alterado. Há material impresso a apontar para este endereço.');
});

it('não muda o slug gravado quando um pedido forjado tenta defini-lo', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    try {
        ecraDeEditar($qrCode, $dono)
            ->set('slug', 'aaaaaa')
            ->call('guardar');
    } catch (Throwable) {
        // Ou o componente não tem propriedade `slug` — e recusa o pedido logo
        // aí —, ou o modelo impede a gravação. As duas defesas servem.
    }

    expect($qrCode->fresh()->slug)->toBe(SLUG_IMPRESSO)
        ->and(QrCode::where('slug', 'aaaaaa')->exists())->toBeFalse();
});

it('não muda o slug gravado quando o pedido forjado o tenta pelo modelo', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    try {
        ecraDeEditar($qrCode, $dono)
            ->set('qrCode.slug', 'aaaaaa')
            ->set('destino', DESTINO_NOVO)
            ->call('guardar');
    } catch (Throwable) {
        // Idem: recusar a propriedade é tão válido como ignorá-la.
    }

    expect($qrCode->fresh()->slug)->toBe(SLUG_IMPRESSO)
        ->and(QrCode::where('slug', 'aaaaaa')->exists())->toBeFalse();
});

it('não deixa um pedido forjado roubar o slug de outro código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    $outro = QrCode::factory()->for($dono)->create(['slug' => 'zx7n9d']);

    try {
        ecraDeEditar($qrCode, $dono)
            ->set('slug', $outro->slug)
            ->call('guardar');
    } catch (Throwable) {
        // Recusar é o comportamento desejado.
    }

    expect($qrCode->fresh()->slug)->toBe(SLUG_IMPRESSO)
        ->and($outro->fresh()->slug)->toBe('zx7n9d');
});

it('continua a redireccionar pelo slug antigo depois de um pedido forjado', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    try {
        ecraDeEditar($qrCode, $dono)
            ->set('slug', 'aaaaaa')
            ->set('destino', DESTINO_NOVO)
            ->call('guardar');
    } catch (Throwable) {
        // Recusar é o comportamento desejado.
        devolverORedirectorDoLaravel();
    }

    // O que está impresso continua a funcionar; o slug forjado nunca existiu.
    $this->get('/'.SLUG_IMPRESSO)->assertStatus(302);
    $this->get('/aaaaaa')->assertNotFound();
});

// -----------------------------------------------------------------------------
// Critério: «novo destino é validado como URL http/https».
// -----------------------------------------------------------------------------

it('abre o formulário com os valores actuais do código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->assertSet('nome', 'Flyer Setembro — regresso às aulas')
        ->assertSet('destino', DESTINO_ANTERIOR)
        ->assertSet('activo', true);
});

it('recusa um novo destino que não seja um URL http ou https', function (string $destino) {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', $destino)
        ->call('guardar')
        ->assertHasErrors(['destino']);

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
})->with([
    'vazio' => '',
    'relativo' => '/campanhas/outono',
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

it('aceita um novo destino http ou https', function (string $destino) {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', $destino)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($qrCode->fresh()->destino)->toBe($destino);
})->with([
    'https' => 'https://loja.exemplo.pt/campanha',
    'http' => 'http://loja.exemplo.pt',
    'com parâmetros' => 'https://loja.exemplo.pt/campanhas/outono?utm_source=flyer&utm_medium=qr',
    'com porta' => 'https://loja.exemplo.pt:8443/campanha',
    'com âncora' => 'https://loja.exemplo.pt/campanha#preco',
]);

it('recusa um novo destino que aponta para o próprio sistema', function (string $caminho) {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', enderecoDaCasa($caminho))
        ->call('guardar')
        ->assertHasErrors(['destino']);

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
})->with([
    'raiz' => '/',
    'slug' => '/abc123',
    'ecrã interno' => '/codigos/criar',
    'com parâmetros' => '/abc123?utm_source=qr',
]);

it('recusa apontar o destino para o endereço curto de outro código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    $outro = QrCode::factory()->for($dono)->create();

    ecraDeEditar($qrCode, $dono)
        ->set('destino', route('redirect.publico', $outro->slug))
        ->call('guardar')
        ->assertHasErrors(['destino']);

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
});

it('recusa apontar o destino para o endereço curto do próprio código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', route('redirect.publico', SLUG_IMPRESSO))
        ->call('guardar')
        ->assertHasErrors(['destino']);

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
});

it('explica o ciclo, com as palavras do mockup, quando o destino é do próprio sistema', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $erros = ecraDeEditar($qrCode, $dono)
        ->set('destino', enderecoDaCasa())
        ->call('guardar')
        ->assertHasErrors(['destino'])
        ->errors();

    $mensagens = array_map(semEspacosARepetir(...), $erros->get('destino'));

    expect($mensagens)->toContain(semEspacosARepetir(MENSAGEM_DE_LOOP_AO_EDITAR));
});

it('não despacha o destino em loop com a mensagem genérica de endereço inválido', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $erros = ecraDeEditar($qrCode, $dono)
        ->set('destino', enderecoDaCasa())
        ->call('guardar')
        ->errors();

    $mensagens = semEspacosARepetir(implode(' ', $erros->get('destino')));

    expect($mensagens)->not->toContain('Falta o início do endereço')
        ->and($mensagens)->toContain('ciclo');
});

it('recusa gravar sem nome', function (string $nome) {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', $nome)
        ->call('guardar')
        ->assertHasErrors(['nome']);

    expect($qrCode->fresh()->nome)->toBe('Flyer Setembro — regresso às aulas');
})->with([
    'vazio' => '',
    'só espaços' => '   ',
]);

it('recusa um nome com 256 caracteres e aceita um com 255', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', str_repeat('a', 256))
        ->call('guardar')
        ->assertHasErrors(['nome']);

    expect($qrCode->fresh()->nome)->toBe('Flyer Setembro — regresso às aulas');

    ecraDeEditar($qrCode, $dono)
        ->set('nome', str_repeat('a', 255))
        ->call('guardar')
        ->assertHasNoErrors();

    expect($qrCode->fresh()->nome)->toHaveLength(255);
});

it('recusa um destino com mais de 2048 caracteres', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', 'https://loja.exemplo.pt/'.str_repeat('a', 2048))
        ->call('guardar')
        ->assertHasErrors(['destino']);

    expect($qrCode->fresh()->destino)->toBe(DESTINO_ANTERIOR);
});

it('mantém o utilizador no formulário, com o que escreveu, quando a validação falha', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', '')
        ->set('destino', 'loja.exemplo.pt/campanha')
        ->call('guardar')
        ->assertNoRedirect()
        ->assertSet('nome', '')
        ->assertSet('destino', 'loja.exemplo.pt/campanha');
});

it('assinala só o campo errado quando o outro está bem preenchido', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', 'loja.exemplo.pt/campanha')
        ->call('guardar')
        ->assertHasErrors(['destino'])
        ->assertHasNoErrors(['nome']);
});

it('escreve as mensagens de erro da edição em português', function (string $campo, string $valor) {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $erros = ecraDeEditar($qrCode, $dono)
        ->set($campo, $valor)
        ->call('guardar')
        ->errors();

    $mensagens = implode(' ', $erros->all());

    expect($mensagens)->not->toBeEmpty();

    foreach (['field', 'must be', 'is required', 'valid URL', 'The nome', 'The destino'] as $indicioDeIngles) {
        expect(Str::lower($mensagens))->not->toContain(Str::lower($indicioDeIngles));
    }
})->with([
    'nome em falta' => ['nome', ''],
    'destino em falta' => ['destino', ''],
    'destino sem esquema' => ['destino', 'loja.exemplo.pt/campanha'],
    'destino com esquema errado' => ['destino', 'ftp://loja.exemplo.pt/ficheiro'],
    'nome longo de mais' => ['nome', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
]);

// -----------------------------------------------------------------------------
// Critério: «desactivar um QR faz o redirect passar a devolver 404».
// -----------------------------------------------------------------------------

it('faz o redirect devolver 404 depois de desactivar o código', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('activo', false)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($qrCode->fresh()->activo)->toBeFalse();

    $this->get('/'.SLUG_IMPRESSO)->assertNotFound();
});

it('não regista leitura nenhuma no código que acabou de ser desactivado', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)->set('activo', false)->call('guardar');

    $this->get('/'.SLUG_IMPRESSO);
    $this->get('/'.SLUG_IMPRESSO);

    // Um código desligado pelo dono não pode continuar a somar leituras que
    // nunca chegaram ao destino.
    expect(Scan::count())->toBe(0)
        ->and($qrCode->scans()->count())->toBe(0);
});

it('põe o redirect a funcionar outra vez quando o código é reactivado', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono, ['activo' => false]);

    $this->get('/'.SLUG_IMPRESSO)->assertNotFound();

    ecraDeEditar($qrCode, $dono)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    $resposta = $this->get('/'.SLUG_IMPRESSO);

    $resposta->assertStatus(302);
    expect($resposta->headers->get('Location'))->toBe(DESTINO_ANTERIOR)
        ->and(Scan::count())->toBe(1);
});

it('desactivar e mudar o destino ao mesmo tempo não ressuscita o redirect', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('destino', DESTINO_NOVO)
        ->set('activo', false)
        ->call('guardar')
        ->assertHasNoErrors();

    $resposta = $this->get('/'.SLUG_IMPRESSO);

    $resposta->assertNotFound();
    expect((string) $resposta->getContent())->not->toContain(DESTINO_NOVO);
});

it('não apaga as leituras já registadas ao desactivar', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    Scan::factory()->count(3)->for($qrCode)->create();

    ecraDeEditar($qrCode, $dono)->set('activo', false)->call('guardar');

    expect($qrCode->scans()->count())->toBe(3);
});

// -----------------------------------------------------------------------------
// O aviso do estado 4 do mockup: «Vai desactivar um código com 12 847 leituras».
// Só faz sentido quando há material lido lá fora e a desactivação está mesmo em
// cima da mesa.
// -----------------------------------------------------------------------------

it('avisa de quantas leituras se perdem quando se desactiva um código lido', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    Scan::factory()->count(7)->for($qrCode)->create();

    $html = ecraDeEditar($qrCode, $dono)->set('activo', false)->html();

    expect(textoDoEcraDeEditar($html))->toContain('Vai desactivar')
        ->and(textoDoEcraDeEditar($html))->toContain('7 leituras');
});

it('não avisa enquanto o código continua activo', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);
    Scan::factory()->count(7)->for($qrCode)->create();

    $html = ecraDeEditar($qrCode, $dono)->set('destino', DESTINO_NOVO)->html();

    expect(textoDoEcraDeEditar($html))->not->toContain('Vai desactivar');
});

it('não avisa quando o código nunca foi lido', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    $html = ecraDeEditar($qrCode, $dono)->set('activo', false)->html();

    expect(textoDoEcraDeEditar($html))->not->toContain('Vai desactivar');
});

it('não avisa num código que já estava inactivo', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono, ['activo' => false]);
    Scan::factory()->count(7)->for($qrCode)->create();

    // Não está a desactivar nada: já estava desligado quando o ecrã abriu.
    $html = ecraDeEditar($qrCode, $dono)->html();

    expect(textoDoEcraDeEditar($html))->not->toContain('Vai desactivar');
});

// -----------------------------------------------------------------------------
// Gravar: o que fica gravado, e o que fica igual.
// -----------------------------------------------------------------------------

it('grava o nome, o destino e o estado escritos', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', 'Cartaz A2 — Feira do Livro')
        ->set('destino', DESTINO_NOVO)
        ->set('activo', false)
        ->call('guardar')
        ->assertHasNoErrors();

    $fresco = $qrCode->fresh();

    expect($fresco->nome)->toBe('Cartaz A2 — Feira do Livro')
        ->and($fresco->destino)->toBe(DESTINO_NOVO)
        ->and($fresco->activo)->toBeFalse();
});

it('não estraga nada quando se grava sem ter mudado coisa nenhuma', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->call('guardar')
        ->assertHasNoErrors();

    $fresco = $qrCode->fresh();

    expect($fresco->nome)->toBe('Flyer Setembro — regresso às aulas')
        ->and($fresco->destino)->toBe(DESTINO_ANTERIOR)
        ->and($fresco->activo)->toBeTrue()
        ->and($fresco->slug)->toBe(SLUG_IMPRESSO)
        ->and($fresco->user_id)->toBe($dono->id);

    $this->get('/'.SLUG_IMPRESSO)->assertStatus(302);
});

it('não muda o dono do código ao gravar', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)->set('destino', DESTINO_NOVO)->call('guardar');

    expect($qrCode->fresh()->user_id)->toBe($dono->id);
});

it('apara os espaços do nome e do destino antes de gravar', function () {
    $dono = User::factory()->create();
    $qrCode = codigoImpresso($dono);

    ecraDeEditar($qrCode, $dono)
        ->set('nome', '  Flyer Outubro  ')
        ->set('destino', '  '.DESTINO_NOVO.'  ')
        ->call('guardar')
        ->assertHasNoErrors();

    $fresco = $qrCode->fresh();

    expect($fresco->nome)->toBe('Flyer Outubro')
        ->and($fresco->destino)->toBe(DESTINO_NOVO);

    // Um destino com espaços colados partia o cabeçalho `Location`.
    expect($this->get('/'.SLUG_IMPRESSO)->headers->get('Location'))->toBe(DESTINO_NOVO);
});
