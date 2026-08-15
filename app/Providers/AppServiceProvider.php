<?php

namespace App\Providers;

use App\Models\ApiToken;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurarTokensDeApi();
    }

    /**
     * Revogar um token não apaga a linha — o utilizador tem de continuar a ver
     * que aquele token existiu. Como a linha fica, o Sanctum continuaria a
     * aceitá-la: é este callback que a recusa, e é aqui que "revogado" passa a
     * significar mesmo 401.
     */
    protected function configurarTokensDeApi(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        Sanctum::authenticateAccessTokensUsing(
            fn (ApiToken $token, bool $eValido): bool => $eValido && ! $token->estaRevogado()
        );

        // O limite é por token, não por IP: duas ferramentas do mesmo cliente
        // atrás do mesmo IP não se estorvam uma à outra, e um token que se
        // descontrola não leva os outros com ele. Sem token — um pedido que vai
        // levar 401 de qualquer forma — cai-se no IP, para que ninguém possa
        // martelar a porta à borla.
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            return $token instanceof ApiToken
                ? Limit::perMinute(60)->by('token:'.$token->id)
                : Limit::perMinute(20)->by('ip:'.$request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
