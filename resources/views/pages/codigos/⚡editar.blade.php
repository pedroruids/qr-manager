<?php

use App\Models\QrCode;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar código QR')] class extends Component
{
    public QrCode $qrCode;

    public string $nome = '';

    public string $destino = '';

    public bool $activo = true;

    /**
     * Não há propriedade para o slug, e é de propósito: o que não existe no
     * componente não pode ser enviado para ele. O slug aparece no ecrã como
     * texto, nunca como campo — nem sequer como campo desactivado, porque um
     * campo desactivado sugere que algures pode ser activado.
     */
    public function mount(QrCode $qrCode): void
    {
        abort_unless($qrCode->user_id === auth()->id(), 404);

        $this->qrCode = $qrCode;
        $this->nome = $qrCode->nome;
        $this->destino = $qrCode->destino;
        $this->activo = $qrCode->activo;
    }

    public function guardar(): void
    {
        ['nome' => $this->nome, 'destino' => $this->destino] = QrCode::normalizar([
            'nome' => $this->nome,
            'destino' => $this->destino,
        ]);

        $dados = $this->validate([
            'nome' => QrCode::regras()['nome'],
            'destino' => QrCode::regras()['destino'],
            'activo' => QrCode::regras()['activo'],
        ], QrCode::mensagens());

        $this->qrCode->update($dados);

        Flux::toast(variant: 'success', text: 'Alterações gravadas.');

        $this->redirectRoute('codigos.detalhe', $this->qrCode, navigate: true);
    }

    /**
     * Quantos campos mudaram face ao que está gravado. Sem alterações, gravar
     * não faz nada — e o ecrã diz que não faz.
     */
    #[Computed]
    public function alteracoes(): int
    {
        return collect([
            $this->nome !== $this->qrCode->nome,
            $this->destino !== $this->qrCode->destino,
            $this->activo !== $this->qrCode->activo,
        ])->filter()->count();
    }

    /**
     * Desactivar um código que já foi lido apaga da rua o que está impresso.
     * O aviso só aparece quando há mesmo leituras a perder.
     */
    #[Computed]
    public function leiturasAPerder(): int
    {
        if ($this->activo || ! $this->qrCode->activo) {
            return 0;
        }

        return $this->qrCode->scans()->count();
    }

    public function urlCurto(): string
    {
        return route('redirect.publico', $this->qrCode->slug);
    }
}; ?>

<div class="p-6">
    <div class="max-w-xl">
        <div class="rounded-card border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Editar código QR</flux:heading>

            <form wire:submit="guardar" class="mt-6 grid gap-5">
                <div class="grid gap-1.5">
                    <flux:label>Endereço do código</flux:label>

                    <div class="flex items-center gap-2 rounded-card border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900">
                        <x-copy-field
                            class="w-full"
                            :valor="$this->urlCurto()"
                            :rotulo="'Copiar o endereço de '.$qrCode->nome"
                        />
                    </div>

                    <flux:text size="sm">
                        Não pode ser alterado. Há material impresso a apontar para este endereço.
                    </flux:text>
                </div>

                <flux:input wire:model.live="nome" label="Nome" />

                <flux:input
                    wire:model.live="destino"
                    type="url"
                    label="URL de destino"
                    description="Altera para onde as pessoas são levadas. Vale para todos os códigos já impressos, a partir do momento em que gravar."
                />

                <flux:switch
                    wire:model.live="activo"
                    label="Código activo"
                    description="Desactivar faz com que quem leia o código veja uma página a dizer que já não está disponível."
                    align="right"
                />

                @if ($this->leiturasAPerder > 0)
                    {{-- Faixa neutra: o sistema não tem fundos de cor. --}}
                    <div class="flex items-start gap-2 rounded-card bg-zinc-50 p-3 text-sm text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />

                        <span>
                            Vai desactivar um código com
                            <span class="font-medium">{{ number_format($this->leiturasAPerder, 0, ',', ' ') }} leituras</span>.
                            Há material impresso na rua a apontar para ele. Pode voltar a activá-lo a qualquer momento.
                        </span>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                    <flux:text size="sm">
                        @if ($this->alteracoes === 0)
                            Sem alterações por gravar
                        @elseif ($this->alteracoes === 1)
                            1 alteração por gravar
                        @else
                            {{ $this->alteracoes }} alterações por gravar
                        @endif
                    </flux:text>

                    <div class="flex items-center gap-2">
                        <flux:button size="sm" :href="route('codigos.detalhe', $qrCode)" wire:navigate>Cancelar</flux:button>

                        <flux:button
                            type="submit"
                            variant="primary"
                            size="sm"
                            :disabled="$this->alteracoes === 0"
                        >Guardar alterações</flux:button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
