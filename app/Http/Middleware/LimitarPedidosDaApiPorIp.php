<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite por IP à entrada da API, antes de qualquer autenticação.
 *
 * É middleware nosso, e não o `throttle` do Laravel, por uma razão de ordem: o
 * router ordena o middleware pela lista de prioridade, onde o `Authenticate`
 * vem à frente do `ThrottleRequests`. Um `throttle` declarado antes do
 * `auth:sanctum` acabava a correr depois dele — e nunca via um pedido anónimo,
 * que é justamente quem se quer travar aqui. Uma classe fora dessa lista fica
 * onde é posta.
 *
 * Quem bate à porta sem token leva 401 na mesma; o que isto impede é que bata
 * um milhão de vezes, e cada tentativa custa a procura do hash do token.
 */
class LimitarPedidosDaApiPorIp
{
    public const POR_MINUTO = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $chave = 'api-por-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, self::POR_MINUTO)) {
            return new JsonResponse(
                ['message' => 'Demasiados pedidos. Tente daqui a pouco.'],
                429,
                ['Retry-After' => RateLimiter::availableIn($chave)]
            );
        }

        RateLimiter::hit($chave);

        return $next($request);
    }
}
