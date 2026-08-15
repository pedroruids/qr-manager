<?php

use Illuminate\Support\Facades\DB;

it('liga à base de dados', function () {
    expect(DB::select('select 1 as um'))->not->toBeEmpty();
});

it('responde na página inicial', function () {
    $this->get('/')->assertSuccessful();
});
