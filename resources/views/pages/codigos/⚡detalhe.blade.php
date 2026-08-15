<?php

use App\Models\QrCode;
use App\Services\GeradorQrCode;
use App\Services\LeiturasPorDia;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Código QR')] class extends Component
{
    public QrCode $qrCode;

    /**
     * O tamanho só afecta o PNG. O SVG é vectorial e sai sempre igual.
     */
    public int $tamanho = 1024;

    public function mount(QrCode $qrCode): void
    {
        // Quem não é dono recebe o mesmo que receberia se o código não
        // existisse. Um 403 confirmaria que existe e é de outra pessoa.
        abort_unless($qrCode->user_id === auth()->id(), 404);

        $this->qrCode = $qrCode;
    }

    /**
     * @return array<int, string>
     */
    public function tamanhos(): array
    {
        return [
            512 => '512 px — ecrã',
            1024 => '1024 px — flyer',
            GeradorQrCode::TAMANHO_PNG_MAXIMO => GeradorQrCode::TAMANHO_PNG_MAXIMO.' px — cartaz',
        ];
    }

    public function urlCurto(): string
    {
        return route('redirect.publico', $this->qrCode->slug);
    }

    /**
     * O total é de sempre. O gráfico é dos últimos trinta dias — são perguntas
     * diferentes: "quanto rendeu este código" e "ainda está a render".
     */
    #[Computed]
    public function totalDeLeituras(): int
    {
        return $this->qrCode->scans()->count();
    }

    /**
     * @return list<array{data: \Carbon\CarbonImmutable, valor: int}>
     */
    #[Computed]
    public function leiturasPorDia(): array
    {
        return app(LeiturasPorDia::class)->paraQrCode($this->qrCode);
    }

    #[Computed]
    public function maximoPorDia(): int
    {
        return max(array_column($this->leiturasPorDia, 'valor'));
    }
}; ?>

<div class="p-6">
    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">

        {{-- Coluna esquerda: o código e o que se leva daqui --}}
        <div class="grid content-start gap-3">
            <x-qr-preview :qr-code="$qrCode" />

            <div class="grid gap-2">
                <div class="flex gap-2">
                    <flux:button
                        variant="primary"
                        size="sm"
                        class="flex-1"
                        :href="route('codigos.descarregar', ['qrCode' => $qrCode, 'formato' => 'png', 'tamanho' => $tamanho])"
                    >PNG</flux:button>

                    <flux:button
                        size="sm"
                        class="flex-1"
                        :href="route('codigos.descarregar', ['qrCode' => $qrCode, 'formato' => 'svg'])"
                    >SVG</flux:button>
                </div>

                <flux:select wire:model.live="tamanho" size="sm" label="Tamanho do PNG">
                    @foreach ($this->tamanhos() as $valor => $rotulo)
                        <flux:select.option :value="$valor">{{ $rotulo }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:text size="sm">
                    Para gráfica, peça sempre o SVG: não perde definição em nenhum tamanho.
                </flux:text>
            </div>
        </div>

        {{-- Coluna direita: o que o código é e para onde aponta --}}
        <div class="grid min-w-0 content-start gap-4">
            <div class="rounded-card border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="truncate" :title="$qrCode->nome">{{ $qrCode->nome }}</flux:heading>
                        <flux:text size="sm">Criado {{ $qrCode->created_at->diffForHumans() }}</flux:text>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <flux:button size="sm" :href="route('codigos.editar', $qrCode)" wire:navigate>Editar</flux:button>

                        {{-- O estado nunca é só cor: o ponto acompanha sempre o rótulo em texto. --}}
                        <flux:badge size="sm" color="zinc">
                            @if ($qrCode->activo)
                                <span class="me-1.5 size-1.5 rounded-full bg-emerald-600"></span>Activo
                            @else
                                <span class="me-1.5 size-1.5 rounded-full border border-zinc-400 dark:border-zinc-600"></span>Inactivo
                            @endif
                        </flux:badge>
                    </div>
                </div>

                <dl class="grid gap-3 p-4 text-sm sm:grid-cols-[140px_1fr]">
                    <dt class="text-zinc-500 dark:text-zinc-400">Endereço do código</dt>
                    <dd class="min-w-0">
                        <x-copy-field
                            :valor="$this->urlCurto()"
                            :rotulo="'Copiar o endereço de '.$qrCode->nome"
                        />
                        <flux:text size="sm" class="mt-0.5">
                            É isto que está codificado no código impresso. Nunca muda.
                        </flux:text>
                    </dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">Destino actual</dt>
                    <dd class="min-w-0">
                        <flux:link :href="$qrCode->destino" class="block truncate" :title="$qrCode->destino" external>
                            {{ $qrCode->destino }}
                        </flux:link>
                        <flux:text size="sm" class="mt-0.5">
                            Pode ser alterado a qualquer momento, sem reimprimir o código.
                        </flux:text>
                    </dd>
                </dl>
            </div>

            <div class="rounded-card border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-baseline justify-between gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex items-baseline gap-2">
                        <flux:heading>Leituras</flux:heading>

                        <span class="text-xl font-semibold tabular-nums {{ $this->totalDeLeituras === 0 ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                            {{ number_format($this->totalDeLeituras, 0, ',', ' ') }}
                        </span>
                    </div>

                    @if ($this->totalDeLeituras > 0)
                        <flux:text size="sm">
                            últimos {{ LeiturasPorDia::DIAS }} dias · máx. {{ $this->maximoPorDia }}/dia
                        </flux:text>
                    @endif
                </div>

                @if ($this->totalDeLeituras === 0)
                    {{--
                        Vazio, não erro: o código foi criado e ainda ninguém o
                        leu. O ecrã diz isso e diz desde quando conta.
                    --}}
                    <x-empty-state
                        compacto
                        valor="0"
                        titulo="Ainda sem leituras"
                        descricao="As leituras aparecem aqui assim que alguém apontar a câmara ao código. A contagem começa no momento em que o código foi criado."
                    />
                @else
                    <div class="p-4">
                        <x-bar-chart
                            :serie="$this->leiturasPorDia"
                            rotulo="Leituras por dia nos últimos {{ LeiturasPorDia::DIAS }} dias"
                        />
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
