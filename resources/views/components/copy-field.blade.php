@props([
    /** O valor que vai para a área de transferência — sempre completo, mesmo quando o ecrã o trunca. */
    'valor',
    /** O que mostrar, se for diferente do valor copiado (o slug, quando o valor é o URL inteiro). */
    'texto' => null,
    /** Diz o que este botão copia. Sem isto, um leitor de ecrã anuncia "botão" e mais nada. */
    'rotulo' => 'Copiar',
])

{{--
    Valor copiável: mono porque é lido carácter a carácter, truncado numa linha
    porque a grelha da tabela manda, e copiado por inteiro porque o que interessa
    é o valor, não o que coube no ecrã.
--}}
<div
    {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-1.5']) }}
    x-data="{
        copiado: false,
        temporizador: null,
        copiar() {
            navigator.clipboard.writeText(@js($valor));
            this.copiado = true;
            clearTimeout(this.temporizador);
            this.temporizador = setTimeout(() => this.copiado = false, 2000);
        },
    }"
>
    <span class="truncate font-mono text-zinc-700 dark:text-zinc-300" title="{{ $valor }}">{{ $texto ?? $valor }}</span>

    <button
        type="button"
        x-on:click="copiar()"
        aria-label="{{ $rotulo }}"
        class="shrink-0 rounded-card p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-3.5" aria-hidden="true">
            <rect x="9" y="9" width="11" height="11" rx="2" />
            <path d="M5 15V5a2 2 0 0 1 2-2h8" />
        </svg>
    </button>

    {{-- role="status" para que a confirmação seja anunciada, e não só vista. --}}
    <span
        x-show="copiado"
        x-cloak
        role="status"
        class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400"
    >Copiado</span>
</div>
