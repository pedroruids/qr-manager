<?php

use App\Http\Controllers\RedirectPublicoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas públicas do redirect.
|
| Ficheiro à parte, e não em routes/web.php, por duas razões:
|
| 1. Não leva o grupo `web` — nada de sessão, cookies nem token CSRF. Quem lê um
|    código impresso é um anónimo com o telemóvel na mão; abrir sessão para lhe
|    responder um 302 é trabalho e um cookie que ninguém pediu.
| 2. É registado depois de todas as outras rotas (ver o `then` do
|    bootstrap/app.php). O `{slug}` é um apanha-tudo: registado antes, engolia
|    /dashboard, /login e o resto da aplicação.
*/

Route::get('/{slug}', RedirectPublicoController::class)->name('redirect.publico');
