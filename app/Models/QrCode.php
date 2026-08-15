<?php

namespace App\Models;

use App\Rules\DestinoForaDoRedirect;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    /**
     * Alfabeto do slug, sem caracteres que se confundem em papel impresso:
     * sem 0/O, sem 1/l/I. Quem lê um slug de um flyer lê-o carácter a carácter.
     */
    public const ALFABETO_SLUG = '23456789abcdefghijkmnpqrstuvwxyz';

    public const COMPRIMENTO_SLUG = 6;

    /** @var list<string> */
    protected $fillable = [
        'nome',
        'destino',
        'activo',
    ];

    /**
     * Regras de validação partilhadas pela interface e pela API.
     *
     * @return array<string, list<string|DestinoForaDoRedirect>>
     */
    public static function regras(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:2048', 'url:http,https', new DestinoForaDoRedirect],
            'activo' => ['boolean'],
        ];
    }

    /**
     * As rotas da aplicação identificam um QR pelo slug. É único, imutável e já
     * é público — não há nada a ganhar em expor também o id sequencial.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Apara o que vem de fora antes de validar.
     *
     * Um URL colado do browser traz espaços à volta com frequência, e sem isto
     * a validação recusa-o com "falta o início do endereço" — uma mensagem que
     * manda corrigir uma coisa que já está certa. O `TrimStrings` do HTTP não
     * chega aqui: um pedido Livewire não passa por ele.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function normalizar(array $dados): array
    {
        foreach (['nome', 'destino'] as $campo) {
            if (is_string($dados[$campo] ?? null)) {
                $dados[$campo] = trim($dados[$campo]);
            }
        }

        return $dados;
    }

    /**
     * As mensagens andam com as regras, e não com o formulário: a aplicação
     * fala pt-PT, e a mensagem por omissão do Laravel — em inglês, a citar o
     * nome da coluna — não é dirigida a quem está a preencher isto.
     *
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'nome.required' => 'Dê um nome ao código para o reconhecer na lista.',
            'nome.max' => 'O nome não pode passar dos 255 caracteres.',
            'destino.required' => 'Indique o endereço para onde este código leva.',
            'destino.url' => 'Falta o início do endereço. Escreva https://loja.exemplo.pt/campanha, com o https:// à frente.',
            'destino.max' => 'O endereço não pode passar dos 2048 caracteres.',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QrCode $qrCode): void {
            $qrCode->slug ??= static::gerarSlug();
        });

        // O slug é o que está impresso no papel. Alterá-lo mata todo o material
        // já distribuído, e não há forma de o corrigir depois.
        static::updating(function (QrCode $qrCode): void {
            if ($qrCode->isDirty('slug')) {
                throw new LogicException(
                    'O slug de um código QR não pode ser alterado depois de gravado.'
                );
            }
        });
    }

    /**
     * Gera um slug livre. A colisão é improvável (32^6), mas não impossível —
     * e a coluna é única, portanto o insert falharia.
     */
    public static function gerarSlug(): string
    {
        do {
            $slug = '';

            for ($i = 0; $i < self::COMPRIMENTO_SLUG; $i++) {
                $slug .= self::ALFABETO_SLUG[random_int(0, strlen(self::ALFABETO_SLUG) - 1)];
            }
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * O destino tem de ser um URL absoluto http/https: é para onde o redirect
     * envia quem lê o código, e um valor relativo não leva a lado nenhum.
     *
     * @return Attribute<string, string>
     */
    protected function destino(): Attribute
    {
        return Attribute::set(function (string $valor): string {
            $valor = trim($valor);

            if (! self::destinoValido($valor)) {
                throw new InvalidArgumentException(
                    "O destino tem de ser um URL absoluto com esquema http ou https. Recebido: [{$valor}]"
                );
            }

            return $valor;
        });
    }

    public static function destinoValido(string $valor): bool
    {
        if (filter_var($valor, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $esquema = parse_url($valor, PHP_URL_SCHEME);
        $anfitriao = parse_url($valor, PHP_URL_HOST);

        return in_array($esquema, ['http', 'https'], true) && is_string($anfitriao) && $anfitriao !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
