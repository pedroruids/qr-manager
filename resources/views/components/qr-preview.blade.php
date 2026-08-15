{{--
    O código QR. É o único elemento do sistema com cantos rectos, e o único que
    mantém fundo branco nos dois temas: a moldura branca é a *quiet zone*, sem a
    qual um leitor não distingue onde o código acaba. É função, não estilo.

    O `role="img"` e o rótulo vivem no contentor; o SVG lá dentro fica escondido
    dos leitores de ecrã, que não têm nada a ganhar com centenas de caminhos.
--}}
<div
    {{ $attributes->merge(['class' => 'aspect-square border border-zinc-200 bg-white p-3 dark:border-zinc-700 [&>svg]:size-full']) }}
    role="img"
    aria-label="Código QR de {{ $qrCode->nome }}"
>
    {!! $svg() !!}
</div>
