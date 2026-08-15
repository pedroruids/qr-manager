@props([
    'titulo',
    'descricao' => null,
    /** Dentro de um cartão que já tem cabeçalho e borda: sem moldura própria nem chip de ícone. */
    'compacto' => false,
    /** Valor a zero, quando o vazio é uma contagem e não uma ausência de dados. */
    'valor' => null,
])

{{--
    Estado vazio. Três ecrãs precisam de um, e sem componente cada um inventava o
    seu — que é exactamente a incoerência que o docs/DESIGN.md existe para evitar.

    Sem slot de acção não desenha botão nenhum: um vazio que não tem nada a
    propor não deve inventar uma acção para preencher o espaço.
--}}
<div
    {{ $attributes->merge([
        'class' => $compacto
            ? 'px-4 py-10 text-center'
            : 'rounded-card border border-zinc-200 bg-white px-6 py-14 text-center dark:border-zinc-800 dark:bg-zinc-900',
    ]) }}
>
    @if (filled($valor))
        {{-- Zero é informação, não erro: diz-se em zinc-400, sem puxar o olho. --}}
        <p class="text-2xl font-semibold tabular-nums text-zinc-400 dark:text-zinc-500">{{ $valor }}</p>
    @elseif (isset($icone))
        <div class="mx-auto grid size-12 place-items-center rounded-card bg-brand-50 text-brand-600 dark:bg-brand-900 dark:text-brand-300">
            {{ $icone }}
        </div>
    @endif

    @if ($compacto)
        {{-- Dentro de um cartão o título da secção já é o do cabeçalho: aqui é texto, não outro nível de cabeçalho. --}}
        <p @class(['text-sm font-medium text-zinc-900 dark:text-zinc-100', 'mt-1' => filled($valor)])>{{ $titulo }}</p>
    @else
        <h2 class="mt-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $titulo }}</h2>
    @endif

    @if (filled($descricao))
        <p class="mx-auto mt-1 max-w-md text-sm text-zinc-500 dark:text-zinc-400">{{ $descricao }}</p>
    @endif

    @isset($acao)
        <div class="mt-5">{{ $acao }}</div>
    @endisset
</div>
