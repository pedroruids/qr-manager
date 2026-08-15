<?php

use App\Models\QrCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Códigos QR')] class extends Component
{
    use WithPagination;

    public const POR_PAGINA = 25;

    /**
     * @return LengthAwarePaginator<int, QrCode>
     */
    #[Computed]
    public function codigos(): LengthAwarePaginator
    {
        return auth()->user()
            ->qrCodes()
            ->withCount('scans')
            ->latest()
            ->paginate(self::POR_PAGINA);
    }

    #[Computed]
    public function total(): int
    {
        return auth()->user()->qrCodes()->count();
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-baseline gap-2">
            <flux:heading size="lg">Códigos QR</flux:heading>

            @if ($this->total > 0)
                <flux:text class="tabular-nums">{{ $this->total }}</flux:text>
            @endif
        </div>

        {{--
            No estado vazio a acção primária é a do estado vazio, e esta passa a
            secundária: duas primárias com o mesmo texto é a mesma decisão
            pedida duas vezes.
        --}}
        <flux:button
            size="sm"
            :variant="$this->total > 0 ? 'primary' : 'outline'"
            :href="route('codigos.criar')"
            wire:navigate
        >Criar QR</flux:button>
    </div>

    @if ($this->total === 0)
        <x-empty-state
            titulo="Ainda não tem códigos QR"
            descricao="Cada código aponta para um endereço curto deste sistema. O destino pode mudar a qualquer momento — o papel já impresso continua a funcionar."
        >
            <x-slot:icone>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1" />
                    <rect x="14" y="3" width="7" height="7" rx="1" />
                    <rect x="3" y="14" width="7" height="7" rx="1" />
                    <path d="M14 14h3v3h-3zM20 14v3M17 20h4" />
                </svg>
            </x-slot:icone>

            <x-slot:acao>
                <flux:button size="sm" variant="primary" :href="route('codigos.criar')" wire:navigate>
                    Criar o primeiro QR
                </flux:button>
            </x-slot:acao>
        </x-empty-state>
    @else
        <flux:table :paginate="$this->codigos">
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column>Destino</flux:table.column>
                <flux:table.column align="end">Leituras</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->codigos as $codigo)
                    <flux:table.row :key="$codigo->id">
                        <flux:table.cell class="max-w-0">
                            <flux:link
                                :href="route('codigos.detalhe', $codigo)"
                                wire:navigate
                                class="block truncate font-medium"
                                :title="$codigo->nome"
                            >{{ $codigo->nome }}</flux:link>
                        </flux:table.cell>

                        <flux:table.cell>
                            <x-copy-field
                                :valor="route('redirect.publico', $codigo->slug)"
                                :texto="$codigo->slug"
                                :rotulo="'Copiar o endereço de '.$codigo->nome"
                            />
                        </flux:table.cell>

                        <flux:table.cell class="max-w-0">
                            <span class="block truncate" title="{{ $codigo->destino }}">{{ $codigo->destino }}</span>
                        </flux:table.cell>

                        {{-- Zero é informação, não erro: dito, mas sem puxar o olho. --}}
                        <flux:table.cell align="end" class="tabular-nums {{ $codigo->scans_count === 0 ? 'text-zinc-400 dark:text-zinc-500' : 'font-medium' }}">
                            {{ number_format($codigo->scans_count, 0, ',', ' ') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{-- O estado nunca é só cor: o ponto acompanha sempre o rótulo. --}}
                            <flux:badge size="sm" color="zinc">
                                @if ($codigo->activo)
                                    <span class="me-1.5 size-1.5 rounded-full bg-emerald-600"></span>Activo
                                @else
                                    <span class="me-1.5 size-1.5 rounded-full border border-zinc-400 dark:border-zinc-600"></span>Inactivo
                                @endif
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
