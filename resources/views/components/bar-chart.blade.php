@props([
    /** @var list<array{data: \Carbon\CarbonImmutable, valor: int}> */
    'serie',
    'rotulo' => 'Leituras por dia',
])

@php
    $valores = array_column($serie, 'valor');
    $maximo = $valores === [] ? 0 : max($valores);
    $minimo = $valores === [] ? 0 : min($valores);
    $total = array_sum($valores);

    // Percentagem da altura. Um dia a zero fica com uma linha de base visível:
    // desaparecer seria mentir sobre o eixo — o dia existiu e teve zero.
    $altura = fn (int $valor): string => $maximo > 0 && $valor > 0
        ? round($valor / $maximo * 100, 2).'%'
        : '2%';
@endphp

<div {{ $attributes }}>
    <div
        class="flex h-40 items-end gap-[3px]"
        role="img"
        aria-label="{{ $rotulo }}: mínimo {{ $minimo }}, máximo {{ $maximo }}, total {{ $total }}"
    >
        @foreach ($serie as $ponto)
            <div
                class="flex-1 {{ $ponto['valor'] > 0 ? 'bg-brand-600' : 'bg-zinc-200 dark:bg-zinc-800' }}"
                style="height: {{ $altura($ponto['valor']) }}"
            ></div>
        @endforeach
    </div>

    @if (count($serie) > 2)
        {{-- Início, meio e fim. Trinta rótulos de data não se lêem. --}}
        <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
            <span>{{ $serie[0]['data']->translatedFormat('j M') }}</span>
            <span>{{ $serie[intdiv(count($serie), 2)]['data']->translatedFormat('j M') }}</span>
            <span>{{ $serie[count($serie) - 1]['data']->translatedFormat('j M') }}</span>
        </div>
    @endif
</div>
