<?php

use App\Models\QrCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Nomes dos cookies que a resposta de uma rota pública traz na resposta.
 *
 * @return array<int, string>
 */
function cookiesDaResposta(TestResponse $resposta): array
{
    return array_map(
        fn (Cookie $cookie): string => $cookie->getName(),
        $resposta->headers->getCookies(),
    );
}

// =============================================================================
// Issue #4 — Redirect público GET /{slug} com registo de leitura
// =============================================================================

// Critério: «slug existente e activo devolve 302 para o destino actual».

it('devolve 302 para o destino de um slug activo', function () {
    $qrCode = QrCode::factory()->create(['destino' => 'https://loja.exemplo.pt/campanha']);

    $resposta = $this->get('/'.$qrCode->slug);

    $resposta->assertStatus(302);
    expect($resposta->headers->get('Location'))->toBe('https://loja.exemplo.pt/campanha');
});

it('redirecciona o leitor anónimo de um código que pertence a outra pessoa', function () {
    $dono = User::factory()->create();
    $qrCode = QrCode::factory()->for($dono)->create(['destino' => 'https://loja.exemplo.pt/flyer']);

    $resposta = $this->get('/'.$qrCode->slug);

    $resposta->assertStatus(302);
    expect($resposta->headers->get('Location'))->toBe('https://loja.exemplo.pt/flyer');
    expect(auth()->check())->toBeFalse();
});

it('preserva o destino à letra, com query string e caracteres codificados', function () {
    $destino = 'https://loja.exemplo.pt/promo%C3%A7%C3%B5es?utm_source=flyer&utm_medium=qr&q=caf%C3%A9';
    $qrCode = QrCode::factory()->create(['destino' => $destino]);

    $resposta = $this->get('/'.$qrCode->slug);

    expect($resposta->headers->get('Location'))->toBe($destino);
});

// Critério: «cada leitura cria exactamente um Scan».

it('cria exactamente uma leitura por visita', function () {
    $qrCode = QrCode::factory()->create();

    $this->get('/'.$qrCode->slug);

    expect(Scan::count())->toBe(1);
    expect(Scan::first()->qr_code_id)->toBe($qrCode->id);
});

it('conta uma leitura por cada visita, nem mais nem menos', function () {
    $qrCode = QrCode::factory()->create();

    $this->get('/'.$qrCode->slug);
    $this->get('/'.$qrCode->slug);
    $this->get('/'.$qrCode->slug);

    expect($qrCode->scans()->count())->toBe(3)
        ->and(Scan::count())->toBe(3);
});

it('não regista a leitura no código errado', function () {
    $lido = QrCode::factory()->create();
    $outro = QrCode::factory()->create();

    $this->get('/'.$lido->slug);

    expect($lido->scans()->count())->toBe(1)
        ->and($outro->scans()->count())->toBe(0);
});

it('guarda o user agent de quem leu o código', function () {
    $qrCode = QrCode::factory()->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
        ->get('/'.$qrCode->slug);

    expect(Scan::first()->user_agent)->toBe('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)');
});

it('redirecciona e regista uma leitura mesmo sem cabeçalho User-Agent', function () {
    $qrCode = QrCode::factory()->create();

    // O cliente de testes injecta sempre um User-Agent «Symfony»; só pondo a
    // variável de servidor a nulo é que o pedido chega mesmo sem o cabeçalho.
    $resposta = $this->call('GET', '/'.$qrCode->slug, [], [], [], ['HTTP_USER_AGENT' => null]);

    $resposta->assertStatus(302);
    expect(Scan::count())->toBe(1);
    expect(Scan::first()->user_agent)->toBeEmpty();
});

// Critério: «slug inexistente ou inactivo devolve 404, nunca 500».

it('devolve 404 num slug que não existe', function () {
    $this->get('/naoexiste')->assertStatus(404);
});

it('devolve 404 num slug inactivo', function () {
    $qrCode = QrCode::factory()->inactivo()->create();

    $this->get('/'.$qrCode->slug)->assertStatus(404);
});

it('devolve 404, e nunca 500, a um slug com caracteres estranhos', function (string $slug) {
    $resposta = $this->get('/'.$slug);

    expect($resposta->status())->toBe(404);
})->with([
    'aspas e sql' => "abc'%20OR%201=1--",
    'html' => '%3Cscript%3Ealert(1)%3C/script%3E',
    'acentos' => 'ç%C3%A3o-ná',
    'espaços' => 'slug%20com%20espacos',
    'ponto ponto' => '..',
    'ponto' => '.',
    'til' => '~~~~~~',
    'muito comprido' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'só um caracter' => 'a',
]);

// Requisito: «um pedido que acaba em 404 não regista leitura nenhuma» — a
// contagem existe para responder «o flyer resultou?», e um código desligado
// pelo dono não pode continuar a somar leituras que nunca chegaram ao destino.

it('não regista leitura num slug inactivo', function () {
    $qrCode = QrCode::factory()->inactivo()->create();

    $this->get('/'.$qrCode->slug)->assertStatus(404);

    expect(Scan::count())->toBe(0)
        ->and($qrCode->scans()->count())->toBe(0);
});

it('não regista leitura num slug que não existe', function () {
    $this->get('/naoexiste')->assertStatus(404);

    expect(Scan::count())->toBe(0);
});

it('soma leituras só ao código activo quando os dois são visitados', function () {
    $activo = QrCode::factory()->create();
    $inactivo = QrCode::factory()->inactivo()->create();

    $this->get('/'.$activo->slug);
    $this->get('/'.$inactivo->slug);
    $this->get('/'.$activo->slug);

    expect($activo->scans()->count())->toBe(2)
        ->and($inactivo->scans()->count())->toBe(0)
        ->and(Scan::count())->toBe(2);
});

it('não trata a raiz do site como um slug', function () {
    $resposta = $this->get('/');

    expect($resposta->status())->not->toBe(404);
    expect(Scan::count())->toBe(0);
});

// Critério: «a rota não exige autenticação nem abre sessão».

it('responde sem utilizador autenticado', function () {
    $qrCode = QrCode::factory()->create();

    $resposta = $this->get('/'.$qrCode->slug);

    $resposta->assertStatus(302);
    $resposta->assertHeaderMissing('WWW-Authenticate');
    expect($resposta->headers->get('Location'))->not->toContain('/login');
});

it('não põe cookie de sessão ao redireccionar', function () {
    $qrCode = QrCode::factory()->create();

    $resposta = $this->get('/'.$qrCode->slug);

    expect(config('session.cookie'))->not->toBeEmpty();
    expect(cookiesDaResposta($resposta))->not->toContain(config('session.cookie'));
});

it('não põe cookie de sessão na página de erro', function () {
    $resposta = $this->get('/naoexiste');

    expect(cookiesDaResposta($resposta))->not->toContain(config('session.cookie'));
});

// Critério: «um teste prova que editar o destino muda para onde o mesmo slug
// redirecciona».

it('redirecciona para o novo destino depois de o dono o editar', function () {
    $qrCode = QrCode::factory()->create(['destino' => 'https://loja.exemplo.pt/verao']);
    $slug = $qrCode->slug;

    $primeira = $this->get('/'.$slug);
    expect($primeira->headers->get('Location'))->toBe('https://loja.exemplo.pt/verao');

    $qrCode->update(['destino' => 'https://loja.exemplo.pt/outono']);

    $segunda = $this->get('/'.$slug);

    expect($segunda->headers->get('Location'))->toBe('https://loja.exemplo.pt/outono');
    expect($qrCode->fresh()->slug)->toBe($slug);
});

// =============================================================================
// Issue #5 — Página de erro do redirect (slug inválido ou inactivo)
// =============================================================================

// Critério: «slug inexistente e slug inactivo mostram ambos esta página com
// estado 404» e «a página não revela se o slug alguma vez existiu».

it('mostra exactamente a mesma página a um slug inexistente e a um slug inactivo', function () {
    $inactivo = QrCode::factory()->inactivo()->create();

    // Pedido de aquecimento: o Vite só emite as etiquetas de preload uma vez
    // por processo, e sem isto o primeiro dos dois pedidos vinha diferente do
    // segundo por razões que nada têm a ver com o slug.
    $this->get('/aquecimento');

    $respostaInactivo = $this->get('/'.$inactivo->slug);
    $respostaInexistente = $this->get('/naoexiste');

    expect($respostaInactivo->status())->toBe(404)
        ->and($respostaInexistente->status())->toBe(404)
        ->and($respostaInactivo->getContent())->toBe($respostaInexistente->getContent());
});

it('não revela nada do código na página de erro', function () {
    $dono = User::factory()->create(['name' => 'Marta Nogueira', 'email' => 'marta@exemplo.pt']);
    $qrCode = QrCode::factory()->for($dono)->create([
        'nome' => 'Flyer Confidencial',
        'destino' => 'https://loja.exemplo.pt/segredo',
        'activo' => false,
    ]);

    $resposta = $this->get('/'.$qrCode->slug);

    $resposta->assertStatus(404);
    $resposta->assertDontSee($qrCode->slug);
    $resposta->assertDontSee('Flyer Confidencial');
    $resposta->assertDontSee('loja.exemplo.pt/segredo');
    $resposta->assertDontSee('Marta Nogueira');
    $resposta->assertDontSee('marta@exemplo.pt');
});

// Critério: «legível em ecrã de telemóvel, sem depender de JavaScript» — o texto
// principal aprovado no mockup tem de vir no HTML da resposta.

it('traz o texto principal no HTML devolvido', function () {
    $resposta = $this->get('/naoexiste');

    $resposta->assertSee('Este código já não está activo');
    $resposta->assertSee('Não há nada para ver aqui.');
});

it('não depende de JavaScript para mostrar o conteúdo', function () {
    $html = (string) $this->get('/naoexiste')->getContent();

    // Com todos os scripts removidos, o texto tem de continuar lá: é o que
    // sobra num telemóvel que bloqueia JavaScript.
    $semScripts = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);

    expect($semScripts)->toContain('Este código já não está activo')
        ->and($semScripts)->toContain('Não há nada para ver aqui.')
        ->and($html)->not->toContain('wire:')
        ->and($html)->not->toContain('x-data');
});

it('declara a página em português e adaptada a ecrã de telemóvel', function () {
    $html = $this->get('/naoexiste')->getContent();

    expect($html)->toContain('lang="pt-PT"')
        ->and($html)->toContain('name="viewport"');
});

// Critério: «não tem navegação nem sessão da aplicação».

it('não tem navegação nem ligações para a aplicação', function () {
    $html = $this->get('/naoexiste')->getContent();

    expect($html)->not->toContain('<nav')
        ->and($html)->not->toContain('/dashboard')
        ->and($html)->not->toContain('/login')
        ->and($html)->not->toContain('/register')
        ->and($html)->not->toContain('/settings');
});
