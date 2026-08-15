<?php

use App\Models\QrCode;
use App\Models\User;

// Critério: «slug tem índice único e é gerado automaticamente na criação, com
// 6+ caracteres seguros para URL».

it('gera um slug na criação sem ninguém o pedir', function () {
    $qrCode = QrCode::factory()->create();

    expect($qrCode->slug)->not->toBeEmpty();
});

it('gera slugs com 6 ou mais caracteres seguros para URL', function () {
    $slugs = QrCode::factory()->count(20)->create()->pluck('slug');

    foreach ($slugs as $slug) {
        expect(strlen((string) $slug))->toBeGreaterThanOrEqual(6);
        expect($slug)->toMatch('/^[A-Za-z0-9_-]+$/');
        expect(rawurlencode((string) $slug))->toBe((string) $slug);
    }
});

// Critério: «um teste prova que criar dois QRs nunca produz o mesmo slug».

it('nunca repete um slug entre códigos criados', function () {
    $slugs = QrCode::factory()->count(200)->create()->pluck('slug');

    expect($slugs->unique())->toHaveCount(200);
});

it('recusa gravar um slug que já existe', function () {
    $existente = QrCode::factory()->create();

    $duplicado = QrCode::factory()->make();
    $duplicado->slug = $existente->slug;

    expect(fn () => $duplicado->save())->toThrow(Exception::class);
});

// Critério: «o modelo não permite alterar o slug depois de gravado».

it('recusa alterar o slug de um código já gravado', function () {
    $qrCode = QrCode::factory()->create();
    $original = $qrCode->slug;

    expect(function () use ($qrCode) {
        $qrCode->slug = 'zzzzzz';
        $qrCode->save();
    })->toThrow(Exception::class);

    expect($qrCode->fresh()->slug)->toBe($original);
});

it('não deixa passar um slug por atribuição em massa na alteração', function () {
    $qrCode = QrCode::factory()->create();
    $original = $qrCode->slug;

    $qrCode->update(['slug' => 'aaaaaa', 'nome' => 'Nome novo']);

    expect($qrCode->fresh()->slug)->toBe($original)
        ->and($qrCode->fresh()->nome)->toBe('Nome novo');
});

it('não deixa passar um slug por atribuição em massa na criação', function () {
    // Como viria de um formulário ou da API: dados do pedido, sem filtro nenhum.
    $qrCode = new QrCode([
        'nome' => 'Flyer Setembro',
        'destino' => 'https://loja.exemplo.pt/campanha',
        'slug' => 'escolhido-por-mim',
    ]);
    $qrCode->user_id = User::factory()->create()->id;
    $qrCode->save();

    expect($qrCode->slug)->not->toBe('escolhido-por-mim');
});

it('deixa alterar o destino sem tocar no slug', function () {
    $qrCode = QrCode::factory()->create();
    $slug = $qrCode->slug;

    $qrCode->update(['destino' => 'https://loja.exemplo.pt/outono']);

    expect($qrCode->fresh()->destino)->toBe('https://loja.exemplo.pt/outono')
        ->and($qrCode->fresh()->slug)->toBe($slug);
});

// Critério: «destino é validado como URL absoluto com esquema http/https».

it('aceita destinos http e https', function (string $destino) {
    $qrCode = QrCode::factory()->create(['destino' => $destino]);

    expect($qrCode->fresh()->destino)->toBe($destino);
})->with([
    'https://loja.exemplo.pt/campanha',
    'http://loja.exemplo.pt',
    'https://loja.exemplo.pt/campanhas/setembro?utm_source=flyer&utm_medium=qr',
]);

it('recusa destinos que não sejam URL absoluto http ou https', function (string $destino) {
    expect(fn () => QrCode::factory()->create(['destino' => $destino]))
        ->toThrow(Exception::class);
})->with([
    'vazio' => '',
    'relativo' => '/campanha',
    'sem esquema' => 'loja.exemplo.pt/campanha',
    'ftp' => 'ftp://loja.exemplo.pt/ficheiro',
    'javascript' => 'javascript:alert(1)',
    'texto' => 'isto não é um endereço',
]);

it('recusa um destino inválido também na alteração', function () {
    $qrCode = QrCode::factory()->create();

    expect(fn () => $qrCode->update(['destino' => 'loja.exemplo.pt/sem-esquema']))
        ->toThrow(Exception::class);
});

// Critério: «existe factory usada pelos testes» — mais o que a factory tem de
// garantir para os ecrãs que vêm a seguir.

it('cria códigos activos por omissão e inactivos quando pedido', function () {
    expect(QrCode::factory()->create()->activo)->toBeTrue()
        ->and(QrCode::factory()->inactivo()->create()->activo)->toBeFalse();
});

it('pertence ao utilizador que o criou', function () {
    $user = User::factory()->create();

    $qrCode = QrCode::factory()->for($user)->create();

    expect($qrCode->user->id)->toBe($user->id);
});

it('desaparece quando o utilizador é apagado', function () {
    $user = User::factory()->create();
    QrCode::factory()->for($user)->create();

    $user->delete();

    expect(QrCode::count())->toBe(0);
});
