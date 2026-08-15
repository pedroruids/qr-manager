<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token de API.
 *
 * Estende o do Sanctum para acrescentar duas coisas que o produto precisa e que
 * o package não traz: os últimos caracteres do token em claro, para o utilizador
 * distinguir os seus tokens numa lista, e a revogação como estado em vez de
 * apagamento.
 *
 * @property string|null $ultimos_caracteres
 * @property Carbon|null $revogado_em
 */
class ApiToken extends PersonalAccessToken
{
    /**
     * O nome da classe mudou; a tabela é a do Sanctum e fica onde está.
     */
    protected $table = 'personal_access_tokens';

    public function revogar(): void
    {
        $this->forceFill(['revogado_em' => now()])->save();
    }

    public function estaRevogado(): bool
    {
        return $this->revogado_em !== null;
    }

    /**
     * @param  Builder<ApiToken>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->whereNull('revogado_em');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'revogado_em' => 'datetime',
        ]);
    }
}
