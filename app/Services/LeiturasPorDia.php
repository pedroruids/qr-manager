<?php

namespace App\Services;

use App\Models\QrCode;
use Carbon\CarbonImmutable;

/**
 * Conta as leituras de um código, dia a dia.
 *
 * O dia de cada leitura já vem decidido da gravação, na coluna `data_local`, no
 * fuso de quem lê o gráfico: quem o lê está em Portugal, e uma leitura das
 * 23h30 de terça pertence a terça. Aqui só se agrupa e se preenchem os dias sem
 * leituras — que têm de aparecer a zero, porque desaparecerem seria mentir
 * sobre o eixo.
 */
class LeiturasPorDia
{
    public const DIAS = 30;

    /**
     * Uma consulta agregada, com o índice `(qr_code_id, data_local)` a servi-la.
     * Um código com meio milhão de leituras devolve as mesmas trinta linhas e
     * não traz uma única leitura para memória.
     *
     * @return list<array{data: CarbonImmutable, valor: int}>
     */
    public function paraQrCode(QrCode $qrCode, int $dias = self::DIAS): array
    {
        $primeiroDia = CarbonImmutable::now($this->fuso())->startOfDay()->subDays($dias - 1);

        $contagens = $qrCode->scans()
            ->whereBetween('data_local', [
                $primeiroDia->toDateString(),
                $primeiroDia->addDays($dias - 1)->toDateString(),
            ])
            ->groupBy('data_local')
            ->selectRaw('data_local, count(*) as total')
            ->pluck('total', 'data_local');

        $serie = [];

        foreach (range(0, $dias - 1) as $indice) {
            $dia = $primeiroDia->addDays($indice);

            $serie[] = [
                'data' => $dia,
                'valor' => (int) ($contagens[$dia->toDateString()] ?? 0),
            ];
        }

        return $serie;
    }

    private function fuso(): string
    {
        return (string) config('app.fuso_do_utilizador');
    }
}
