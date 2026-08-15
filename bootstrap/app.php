<?php

use App\Http\Middleware\LimitarPedidosDaApiPorIp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sem middleware e em último lugar: o redirect público não abre sessão,
        // e o seu `{slug}` é um apanha-tudo que engoliria as rotas da aplicação
        // se fosse registado antes delas.
        then: function (): void {
            Route::group([], __DIR__.'/../routes/publico.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Limite por IP à entrada da API, antes de qualquer autenticação. Ver
        // a classe: é middleware nosso por causa da ordem, que o `throttle` do
        // Laravel não consegue garantir aqui.
        $middleware->api(prepend: [
            LimitarPedidosDaApiPorIp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
