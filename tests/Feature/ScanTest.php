<?php

use App\Models\QrCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

// Critério: «índice em (qr_code_id, created_at) — é por aí que a agregação
// diária vai consultar».

it('tem um índice composto em qr_code_id e created_at, por esta ordem', function () {
    $indices = collect(Schema::getIndexes('scans'))
        ->map(fn (array $indice): array => array_map('strtolower', $indice['columns']));

    expect($indices)->toContain(['qr_code_id', 'created_at']);
});

// Enunciado de âmbito: «qr_code_id, user_agent, created_at». IP, país,
// dispositivo e referrer ficam de fora.

// A `data_local` entrou com o issue #11: é o dia a que a leitura pertence no
// fuso do utilizador, decidido na gravação. Não é dado novo sobre quem leu — é
// o mesmo instante do `created_at`, arrumado no dia certo — e por isso não
// abre a fronteira que este âmbito fecha.

it('guarda apenas qr_code_id, user_agent, created_at e o dia da leitura', function () {
    $colunas = collect(Schema::getColumnListing('scans'))
        ->reject(fn (string $coluna): bool => $coluna === 'id')
        ->values()
        ->all();

    sort($colunas);

    expect($colunas)->toBe(['created_at', 'data_local', 'qr_code_id', 'user_agent']);
});

it('não guarda dados que ficaram fora de âmbito', function (string $coluna) {
    expect(Schema::hasColumn('scans', $coluna))->toBeFalse();
})->with([
    'ip' => 'ip',
    'ip_address' => 'ip_address',
    'país' => 'pais',
    'country' => 'country',
    'dispositivo' => 'dispositivo',
    'device' => 'device',
    'referrer' => 'referrer',
    'referer' => 'referer',
]);

it('não tem updated_at, porque uma leitura acontece uma vez e nunca muda', function () {
    expect(Schema::hasColumn('scans', 'updated_at'))->toBeFalse();
});

it('carimba created_at sem ninguém o pedir', function () {
    $scan = Scan::factory()->for(QrCode::factory())->create();

    expect($scan->fresh()->created_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('grava o momento pedido à factory', function () {
    $momento = Carbon::parse('2026-03-14 10:05:00');

    $scan = Scan::factory()->for(QrCode::factory())->em($momento)->create();

    expect($scan->fresh()->created_at->toDateTimeString())->toBe('2026-03-14 10:05:00');
});

it('aceita uma leitura sem user_agent', function () {
    $scan = Scan::factory()->for(QrCode::factory())->semUserAgent()->create();

    expect($scan->fresh()->user_agent)->toBeNull();
});

it('aceita user_agent nulo e vazio', function (?string $userAgent) {
    $scan = Scan::factory()->for(QrCode::factory())->create(['user_agent' => $userAgent]);

    expect($scan->fresh()->user_agent)->toBe($userAgent);
})->with([
    'nulo' => null,
    'vazio' => '',
]);

it('recusa uma leitura sem código associado', function () {
    expect(fn () => Scan::factory()->create(['qr_code_id' => null]))
        ->toThrow(Exception::class);
});

it('recusa uma leitura de um código que não existe', function () {
    expect(fn () => Scan::factory()->create(['qr_code_id' => 999999]))
        ->toThrow(Exception::class);
});

// Critério: «existe factory e um teste da relação».

it('pertence ao código que foi lido', function () {
    $qrCode = QrCode::factory()->create();

    $scan = Scan::factory()->for($qrCode)->create();

    expect($scan->qrCode->id)->toBe($qrCode->id);
});

it('aparece na lista de leituras do seu código', function () {
    $qrCode = QrCode::factory()->create();
    $scan = Scan::factory()->for($qrCode)->create();

    expect($qrCode->scans)->toHaveCount(1)
        ->and($qrCode->scans->first()->id)->toBe($scan->id);
});

it('devolve uma lista vazia num código que nunca foi lido', function () {
    $qrCode = QrCode::factory()->create();

    expect($qrCode->scans)->toHaveCount(0)
        ->and($qrCode->scans()->count())->toBe(0);
});

it('conta todas as leituras de um código com muitas', function () {
    $qrCode = QrCode::factory()->create();
    Scan::factory()->for($qrCode)->count(300)->create();

    expect($qrCode->scans()->count())->toBe(300);
});

it('não mistura as leituras de códigos diferentes', function () {
    $lido = QrCode::factory()->create();
    $outro = QrCode::factory()->create();

    Scan::factory()->for($lido)->count(3)->create();
    Scan::factory()->for($outro)->count(7)->create();

    expect($lido->scans()->count())->toBe(3)
        ->and($outro->scans()->count())->toBe(7);
});

// Critério: «apagar um QrCode apaga os seus scans».

it('apaga as leituras quando o código é apagado', function () {
    $qrCode = QrCode::factory()->create();
    Scan::factory()->for($qrCode)->count(5)->create();

    $qrCode->delete();

    expect(Scan::count())->toBe(0);
});

it('apaga apenas as leituras do código apagado', function () {
    $apagado = QrCode::factory()->create();
    $mantido = QrCode::factory()->create();

    Scan::factory()->for($apagado)->count(4)->create();
    Scan::factory()->for($mantido)->count(2)->create();

    $apagado->delete();

    expect(Scan::count())->toBe(2)
        ->and($mantido->scans()->count())->toBe(2);
});

it('apaga um código sem leituras sem se queixar', function () {
    $qrCode = QrCode::factory()->create();

    $qrCode->delete();

    expect(QrCode::count())->toBe(0)
        ->and(Scan::count())->toBe(0);
});

it('apaga as leituras em cadeia quando o utilizador é apagado', function () {
    $user = User::factory()->create();
    $qrCode = QrCode::factory()->for($user)->create();
    Scan::factory()->for($qrCode)->count(3)->create();

    $user->delete();

    expect(QrCode::count())->toBe(0)
        ->and(Scan::count())->toBe(0);
});

it('não apaga o código quando uma leitura é apagada', function () {
    $qrCode = QrCode::factory()->create();
    $scan = Scan::factory()->for($qrCode)->create();

    $scan->delete();

    expect(QrCode::whereKey($qrCode->id)->exists())->toBeTrue()
        ->and(Scan::count())->toBe(0);
});
