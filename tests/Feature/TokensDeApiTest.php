<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// =============================================================================
// Issue #12 — Tokens de API: modelo e ecrã de gestão
// =============================================================================

/**
 * A mensagem que o mockup aprovado exige quando se tenta criar um token sem
 * nome — `docs/mockups/tokens-api.html`, estado 5, modal com o campo em erro.
 * Não é a mensagem genérica de campo obrigatório: diz para que serve o nome.
 */
const MENSAGEM_TOKEN_SEM_NOME = 'Dê um nome ao token. Sem nome, daqui a seis meses não vai saber qual revogar.';

/**
 * Nomes com significado e sem caracteres que o Blade escape — o que se procura
 * no HTML tem de ser o que lá foi escrito.
 */
const TOKEN_DO_CATALOGO = 'Catálogo — sincronização nocturna';

const TOKEN_DE_IMPORTACAO = 'Ferramenta de importação (descontinuada)';

const TOKEN_DO_VIZINHO = 'Integração privada do vizinho';

function ecraDeTokens(?User $utilizador = null): Testable
{
    test()->actingAs($utilizador ?? User::factory()->create());

    return Livewire::test('pages::tokens.index');
}

/**
 * O HTML do ecrã tal como o utilizador o recebe.
 */
function htmlDosTokens(User $utilizador): string
{
    return (string) test()->actingAs($utilizador)
        ->get(route('tokens.index'))
        ->getContent();
}

/**
 * O texto que um humano lê, sem marcas e com o espaço em branco normalizado.
 */
function textoDeTokens(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
}

/**
 * Cria um token pelo ecrã e devolve o componente e o token em claro que ele
 * mostrou. É o único momento em que o segredo existe fora de quem o copiou.
 *
 * @return array{0: Testable, 1: string}
 */
function criarTokenPeloEcra(User $utilizador, string $nome = TOKEN_DO_CATALOGO): array
{
    $componente = ecraDeTokens($utilizador)
        ->set('nome', $nome)
        ->call('criar')
        ->assertHasNoErrors();

    $emClaro = $componente->get('tokenEmClaro');

    expect($emClaro)->toBeString()->not->toBeEmpty();

    return [$componente, (string) $emClaro];
}

/**
 * O segredo dentro do token em claro. O Sanctum aceita o token com ou sem o id
 * à cabeça; o que nunca pode aparecer em lado nenhum é a parte de trás do `|`.
 */
function segredoDoToken(string $emClaro): string
{
    return Str::after($emClaro, '|');
}

function tokenChamado(string $nome): ApiToken
{
    return ApiToken::query()->where('name', $nome)->sole();
}

/**
 * Um pedido à API feito de fora, como o faria a ferramenta que guarda o token:
 * só com o cabeçalho, sem a sessão de quem está no ecrã. Sem esquecer a sessão,
 * o guard `sanctum` autenticava pelo `web` e o token não provava nada.
 */
function pedidoAApiCom(string $emClaro): TestResponse
{
    auth()->forgetGuards();

    return test()->withToken($emClaro)->getJson('/api/user');
}

/**
 * A linha da tabela onde aquele token aparece. A pergunta «este valor é deste
 * token?» só tem resposta dentro da linha.
 */
function linhaDoToken(string $html, string $nome): string
{
    preg_match_all('/<tr\b[^>]*>.*?<\/tr>/su', $html, $linhas);

    foreach ($linhas[0] as $linha) {
        if (str_contains(textoDeTokens($linha), $nome)) {
            return $linha;
        }
    }

    throw new RuntimeException("Não há nenhuma linha do token [{$nome}].");
}

/**
 * A posição da coluna com aquele título, perguntada ao cabeçalho da tabela e
 * não contada à mão: a coluna existe porque tem título, não porque é a quarta.
 */
function indiceDaColunaDeTokens(string $html, string $titulo): int
{
    preg_match('/<thead\b.*?<\/thead>/su', $html, $cabecalho);

    preg_match_all('/<th\b[^>]*>(.*?)<\/th>/su', $cabecalho[0] ?? '', $celulas);

    foreach ($celulas[1] as $indice => $celula) {
        if (str_contains(Str::lower(textoDeTokens($celula)), Str::lower($titulo))) {
            return $indice;
        }
    }

    throw new RuntimeException("A tabela de tokens não tem coluna «{$titulo}».");
}

/**
 * O texto de uma célula daquele token, na coluna com aquele título.
 */
function celulaDoToken(string $html, string $nome, string $coluna): string
{
    preg_match_all('/<td\b[^>]*>(.*?)<\/td>/su', linhaDoToken($html, $nome), $celulas);

    $indice = indiceDaColunaDeTokens($html, $coluna);

    expect(array_key_exists($indice, $celulas[1]))->toBeTrue(
        "A linha do token [{$nome}] não tem célula na coluna «{$coluna}»."
    );

    return textoDeTokens($celulas[1][$indice]);
}

// -----------------------------------------------------------------------------
// Quem chega ao ecrã.
// -----------------------------------------------------------------------------

it('deixa um utilizador autenticado abrir o ecrã dos tokens', function () {
    $this->actingAs(User::factory()->create())->get(route('tokens.index'))->assertOk();
});

it('manda o anónimo para o login em vez de lhe mostrar os tokens', function () {
    $this->get(route('tokens.index'))->assertRedirect(route('login'));
});

// -----------------------------------------------------------------------------
// Critério: «o token em claro é mostrado uma única vez, no momento da criação».
// -----------------------------------------------------------------------------

it('mostra o token em claro logo a seguir a criá-lo', function () {
    [$componente, $emClaro] = criarTokenPeloEcra(User::factory()->create());

    expect($componente->html())->toContain($emClaro);
});

it('não tem token em claro para mostrar antes de se criar algum', function () {
    expect(ecraDeTokens()->get('tokenEmClaro'))->toBeNull();
});

it('não volta a mostrar o token em claro quando se recarrega o ecrã', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);
    $segredo = segredoDoToken($emClaro);

    $recarregado = ecraDeTokens($dono);

    expect($recarregado->get('tokenEmClaro'))->toBeNull()
        ->and($recarregado->html())->not->toContain($segredo)
        ->and(htmlDosTokens($dono))->not->toContain($segredo);
});

it('avisa, com as palavras do mockup, que é a única vez que o token aparece', function () {
    [$componente] = criarTokenPeloEcra(User::factory()->create());

    $texto = textoDeTokens($componente->html());

    expect($texto)->toContain('Copie o token agora')
        ->toContain('É a única vez que ele aparece');
});

// -----------------------------------------------------------------------------
// Critério: «em base de dados fica apenas o hash».
// -----------------------------------------------------------------------------

it('não guarda o token em claro em nenhuma coluna da linha', function () {
    [, $emClaro] = criarTokenPeloEcra(User::factory()->create());

    $segredo = segredoDoToken($emClaro);

    /** @var array<string, mixed> $linha */
    $linha = (array) DB::table('personal_access_tokens')->sole();

    foreach ($linha as $coluna => $valor) {
        expect((string) $valor)->not->toContain(
            $segredo,
            "A coluna [{$coluna}] guarda o token em claro."
        );
    }
});

it('guarda o hash que o Sanctum reconhece, e não o segredo', function () {
    [, $emClaro] = criarTokenPeloEcra(User::factory()->create());

    $segredo = segredoDoToken($emClaro);
    $guardado = DB::table('personal_access_tokens')->sole();

    // O que está na coluna é o que o Sanctum lá teria posto: o SHA-256 do
    // segredo. Não é o segredo, e não há como voltar dele ao segredo.
    expect($guardado->token)->toBe(hash('sha256', $segredo))
        ->not->toBe($segredo)
        ->and(ApiToken::findToken($emClaro)?->getKey())->toBe((int) $guardado->id);
});

it('emite o token com o prefixo qrm_, tal como o mockup o mostra', function () {
    [, $emClaro] = criarTokenPeloEcra(User::factory()->create());

    // O prefixo serve para se reconhecer o segredo à vista e para os varredores
    // de segredos o apanharem — `docs/DECISIONS.md`, 2026-08-15.
    expect($emClaro)->toStartWith('qrm_');
});

// -----------------------------------------------------------------------------
// O token que o utilizador tem em mãos é o que autentica.
// -----------------------------------------------------------------------------

it('entrega o token sem o id à cabeça, e ele autentica assim mesmo', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);

    // Quem cola isto numa ferramenta não tem de perceber que o `{id}|` do
    // Sanctum é ruído nem de o cortar à mão: já não vem.
    expect($emClaro)->not->toContain('|');

    pedidoAApiCom($emClaro)->assertOk()->assertJsonPath('id', $dono->id);
});

it('deixa autenticar com o token copiado do ecrã, sem cortar nem colar nada', function () {
    $dono = User::factory()->create();

    [$componente, $emClaro] = criarTokenPeloEcra($dono);

    preg_match_all('/qrm_[A-Za-z0-9]+/u', $componente->html(), $encontrados);

    $mostrados = array_values(array_unique($encontrados[0]));

    usort($mostrados, fn (string $um, string $outro): int => strlen($outro) <=> strlen($um));

    expect($mostrados)->not->toBeEmpty('O ecrã não mostra token nenhum para copiar.')
        ->and($mostrados[0])->toBe($emClaro);

    // O que está escrito no ecrã, tal e qual, é o que a API aceita.
    pedidoAApiCom($mostrados[0])->assertOk()->assertJsonPath('id', $dono->id);
});

it('continua a reconhecer o token na forma que o Sanctum escreve, com o id à cabeça', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);

    $comId = tokenChamado(TOKEN_DO_CATALOGO)->getKey().'|'.$emClaro;

    pedidoAApiCom($comId)->assertOk()->assertJsonPath('id', $dono->id);
});

it('recusa um token inventado', function () {
    criarTokenPeloEcra(User::factory()->create());

    pedidoAApiCom('qrm_estoutokennuncafoiemitidoporninguem')->assertUnauthorized();
});

// -----------------------------------------------------------------------------
// Critério: «revogar um token faz os pedidos com ele passarem a 401».
// -----------------------------------------------------------------------------

it('deixa passar um pedido à API feito com um token acabado de criar', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);

    pedidoAApiCom($emClaro)->assertOk()->assertJsonPath('id', $dono->id);
});

it('recusa com 401 o pedido feito com um token revogado', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    pedidoAApiCom($emClaro)->assertOk();

    ecraDeTokens($dono)->call('revogar', tokenChamado(TOKEN_DE_IMPORTACAO)->getKey());

    pedidoAApiCom($emClaro)->assertUnauthorized();
});

it('não apaga a linha do token revogado', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    ecraDeTokens($dono)->call('revogar', tokenChamado(TOKEN_DE_IMPORTACAO)->getKey());

    // Revogar é um estado, não um apagamento: o utilizador tem de continuar a
    // ver que aquele token existiu — `docs/DECISIONS.md`, 2026-08-15.
    expect(DB::table('personal_access_tokens')->count())->toBe(1)
        ->and(tokenChamado(TOKEN_DE_IMPORTACAO)->revogado_em)->not->toBeNull();
});

it('mantém na lista o token revogado, marcado como revogado e sem acção', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    ecraDeTokens($dono)->call('revogar', tokenChamado(TOKEN_DE_IMPORTACAO)->getKey());

    $linha = textoDeTokens(linhaDoToken(htmlDosTokens($dono), TOKEN_DE_IMPORTACAO));

    expect($linha)->toContain(Str::substr(segredoDoToken($emClaro), -4))
        ->toMatch('/revogado/iu')
        // Já não há nada a revogar: o mockup põe um traço no lugar do botão.
        ->not->toContain('Revogar');
});

it('não derruba os outros tokens ao revogar um', function () {
    $dono = User::factory()->create();

    [, $revogado] = criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);
    [, $mantido] = criarTokenPeloEcra($dono, TOKEN_DO_CATALOGO);

    ecraDeTokens($dono)->call('revogar', tokenChamado(TOKEN_DE_IMPORTACAO)->getKey());

    pedidoAApiCom($revogado)->assertUnauthorized();
    pedidoAApiCom($mantido)->assertOk();
});

// -----------------------------------------------------------------------------
// Critério: «a lista mostra nome, últimos caracteres e data do último uso».
// -----------------------------------------------------------------------------

it('mostra o nome de cada token na lista', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono, TOKEN_DO_CATALOGO);
    criarTokenPeloEcra($dono, 'Testes locais do André');

    $texto = textoDeTokens(htmlDosTokens($dono));

    expect($texto)->toContain(TOKEN_DO_CATALOGO)
        ->toContain('Testes locais do André');
});

it('mostra os últimos caracteres do token, e nunca o token inteiro', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);
    $segredo = segredoDoToken($emClaro);

    $html = htmlDosTokens($dono);

    expect(tokenChamado(TOKEN_DO_CATALOGO)->ultimos_caracteres)->toBe(Str::substr($segredo, -4))
        ->and(linhaDoToken($html, TOKEN_DO_CATALOGO))->toContain(Str::substr($segredo, -4))
        ->and($html)->not->toContain($segredo)
        ->and($html)->not->toContain(Str::substr($segredo, 0, 12));
});

it('dá a cada token os seus próprios últimos caracteres', function () {
    $dono = User::factory()->create();

    [, $primeiro] = criarTokenPeloEcra($dono, TOKEN_DO_CATALOGO);
    [, $segundo] = criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    $html = htmlDosTokens($dono);

    expect(linhaDoToken($html, TOKEN_DO_CATALOGO))
        ->toContain(Str::substr(segredoDoToken($primeiro), -4))
        ->and(linhaDoToken($html, TOKEN_DE_IMPORTACAO))
        ->toContain(Str::substr(segredoDoToken($segundo), -4));
});

it('regista o uso do token quando ele serve um pedido à API', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);

    expect(tokenChamado(TOKEN_DO_CATALOGO)->last_used_at)->toBeNull();

    pedidoAApiCom($emClaro)->assertOk();

    expect(tokenChamado(TOKEN_DO_CATALOGO)->last_used_at)->not->toBeNull();
});

it('mostra o último uso de um token já usado', function () {
    $dono = User::factory()->create();

    [, $emClaro] = criarTokenPeloEcra($dono);

    pedidoAApiCom($emClaro)->assertOk();

    $celula = celulaDoToken(htmlDosTokens($dono), TOKEN_DO_CATALOGO, 'Último uso');

    expect($celula)->not->toBeEmpty()
        ->not->toMatch('/nunca/iu')
        // Uma data ou um tempo decorrido — de qualquer maneira, tem algarismos.
        ->toMatch('/\d/');
});

it('diz «nunca usado» no token que ainda ninguém usou', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono);

    $celula = celulaDoToken(htmlDosTokens($dono), TOKEN_DO_CATALOGO, 'Último uso');

    // `docs/DESIGN.md`: valor ausente é informação, escreve-se por extenso.
    expect($celula)->toMatch('/nunca usado/iu')
        ->not->toMatch('/\d/');
});

it('não mostra a todos os tokens o mesmo último uso', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono, TOKEN_DO_CATALOGO);
    criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    tokenChamado(TOKEN_DO_CATALOGO)->forceFill(['last_used_at' => now()->subHours(3)])->save();
    tokenChamado(TOKEN_DE_IMPORTACAO)->forceFill(['last_used_at' => now()->subMonths(5)])->save();

    $html = htmlDosTokens($dono);

    // Se o valor viesse de um sítio que não é a linha, os dois eram iguais.
    expect(celulaDoToken($html, TOKEN_DO_CATALOGO, 'Último uso'))
        ->not->toBe(celulaDoToken($html, TOKEN_DE_IMPORTACAO, 'Último uso'));
});

// -----------------------------------------------------------------------------
// Isolamento: os tokens são de quem os criou.
// -----------------------------------------------------------------------------

it('não mostra a ninguém os tokens de outra pessoa', function () {
    $vizinho = User::factory()->create();
    [, $doVizinho] = criarTokenPeloEcra($vizinho, TOKEN_DO_VIZINHO);

    $dono = User::factory()->create();
    criarTokenPeloEcra($dono, TOKEN_DO_CATALOGO);

    $html = htmlDosTokens($dono);

    expect(textoDeTokens($html))->toContain(TOKEN_DO_CATALOGO)
        ->not->toContain(TOKEN_DO_VIZINHO)
        ->and($html)->not->toContain(segredoDoToken($doVizinho));
});

it('não deixa revogar pelo id o token de outra pessoa', function () {
    $vizinho = User::factory()->create();
    [, $doVizinho] = criarTokenPeloEcra($vizinho, TOKEN_DO_VIZINHO);

    $intruso = User::factory()->create();

    try {
        ecraDeTokens($intruso)->call('revogar', tokenChamado(TOKEN_DO_VIZINHO)->getKey());
    } catch (Throwable) {
        // Recusar em voz alta também serve. O que não pode é a revogação passar.
    }

    expect(tokenChamado(TOKEN_DO_VIZINHO)->revogado_em)->toBeNull();

    pedidoAApiCom($doVizinho)->assertOk();
});

// -----------------------------------------------------------------------------
// O nome é obrigatório.
// -----------------------------------------------------------------------------

it('recusa criar um token sem nome', function () {
    $componente = ecraDeTokens()
        ->set('nome', '')
        ->call('criar')
        ->assertHasErrors(['nome']);

    expect(DB::table('personal_access_tokens')->count())->toBe(0)
        ->and($componente->get('tokenEmClaro'))->toBeNull();
});

it('recusa criar um token com um nome só de espaços', function () {
    ecraDeTokens()
        ->set('nome', '   ')
        ->call('criar')
        ->assertHasErrors(['nome']);

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('explica, com as palavras do mockup, para que serve o nome', function () {
    $erros = ecraDeTokens()
        ->set('nome', '')
        ->call('criar')
        ->assertHasErrors(['nome'])
        ->errors();

    $mensagens = array_map(textoDeTokens(...), $erros->get('nome'));

    expect($mensagens)->toContain(textoDeTokens(MENSAGEM_TOKEN_SEM_NOME));
});

// -----------------------------------------------------------------------------
// Estado vazio.
// -----------------------------------------------------------------------------

it('explica o vazio com as palavras do mockup e oferece o primeiro passo', function () {
    $texto = textoDeTokens(htmlDosTokens(User::factory()->create()));

    expect($texto)->toContain('Ainda não criou nenhum token')
        ->toContain('Um token deixa outra ferramenta criar e gerir códigos QR')
        ->toContain('Criar o primeiro token');
});

it('mostra o estado vazio a quem não tem tokens, mesmo com tokens de outros na base de dados', function () {
    criarTokenPeloEcra(User::factory()->create(), TOKEN_DO_VIZINHO);

    $texto = textoDeTokens(htmlDosTokens(User::factory()->create()));

    expect($texto)->toContain('Ainda não criou nenhum token')
        ->not->toContain(TOKEN_DO_VIZINHO);
});

it('deixa de mostrar o estado vazio assim que existe um token', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono);

    $texto = textoDeTokens(htmlDosTokens($dono));

    expect($texto)->not->toContain('Ainda não criou nenhum token')
        ->not->toContain('Criar o primeiro token');
});

it('continua a mostrar a lista, e não o estado vazio, quando o único token está revogado', function () {
    $dono = User::factory()->create();

    criarTokenPeloEcra($dono, TOKEN_DE_IMPORTACAO);

    ecraDeTokens($dono)->call('revogar', tokenChamado(TOKEN_DE_IMPORTACAO)->getKey());

    $texto = textoDeTokens(htmlDosTokens($dono));

    expect($texto)->toContain(TOKEN_DE_IMPORTACAO)
        ->not->toContain('Ainda não criou nenhum token');
});
