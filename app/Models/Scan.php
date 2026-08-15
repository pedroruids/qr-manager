<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ScanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    /** @use HasFactory<ScanFactory> */
    use HasFactory;

    /**
     * Uma leitura é um facto acontecido: grava-se uma vez e nunca muda.
     */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'qr_code_id',
        'user_agent',
    ];

    /**
     * O dia a que a leitura pertence decide-se aqui, uma vez, no fuso de quem
     * vai ler o gráfico. Guardado, deixa de ser preciso converter fusos dentro
     * do SQL — que cada base de dados escreve à sua maneira e que nenhuma faz
     * bem nos dias de 23 e de 25 horas.
     */
    protected static function booted(): void
    {
        static::creating(function (Scan $scan): void {
            $momento = $scan->created_at ?? CarbonImmutable::now();

            $scan->data_local ??= $momento
                ->timezone((string) config('app.fuso_do_utilizador'))
                ->toDateString();
        });
    }

    /**
     * @return BelongsTo<QrCode, $this>
     */
    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // `data_local` fica por converter de propósito: é uma data civil, sem
        // hora e sem fuso, e é assim que entra na consulta. O cast `date` do
        // Eloquent gravava-a como "2026-08-15 00:00:00", que já não compara com
        // a data que a consulta procura.
        return [
            'created_at' => 'datetime',
        ];
    }
}
