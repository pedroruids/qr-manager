<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Recusa um destino que aponte para o próprio sistema.
 *
 * Um código cujo destino é outro endereço deste sistema entra em ciclo: o
 * redirect leva ao redirect, e quem leu o papel nunca chega a lado nenhum. O
 * caso não é hipotético — é o que acontece quando alguém copia o endereço curto
 * de um código para o campo de destino de outro.
 */
class DestinoForaDoRedirect implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $anfitriao = parse_url($value, PHP_URL_HOST);

        if (! is_string($anfitriao) || ! $this->eDaCasa($anfitriao)) {
            return;
        }

        $fail(sprintf(
            'Este endereço pertence ao %s. Um código que aponta para outro código entra em ciclo e nunca chega a lado nenhum. Indique o endereço final.',
            config('app.name')
        ));
    }

    private function eDaCasa(string $anfitriao): bool
    {
        $nosso = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($nosso) || $nosso === '') {
            return false;
        }

        return mb_strtolower($anfitriao) === mb_strtolower($nosso);
    }
}
