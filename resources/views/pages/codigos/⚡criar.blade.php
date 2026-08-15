<?php

use App\Models\QrCode;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Criar código QR')] class extends Component
{
    public string $nome = '';

    public string $destino = '';

    public function criar(): void
    {
        ['nome' => $this->nome, 'destino' => $this->destino] = QrCode::normalizar([
            'nome' => $this->nome,
            'destino' => $this->destino,
        ]);

        $dados = $this->validate([
            'nome' => QrCode::regras()['nome'],
            'destino' => QrCode::regras()['destino'],
        ], QrCode::mensagens());

        $qrCode = auth()->user()->qrCodes()->create($dados);

        // O slug só existe depois de gravar, e é o que o utilizador veio buscar:
        // leva-se logo ao detalhe, onde o vê e descarrega o ficheiro.
        $this->redirectRoute('codigos.detalhe', $qrCode, navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'nome' => 'nome',
            'destino' => 'URL de destino',
        ];
    }
}; ?>

<div class="p-6">
    <div class="max-w-xl">
        <div class="rounded-card border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Criar código QR</flux:heading>

            <flux:text class="mt-1">
                O código impresso aponta sempre para o mesmo endereço curto. O destino
                para onde esse endereço leva pode ser mudado depois, sem reimprimir nada.
            </flux:text>

            <form wire:submit="criar" class="mt-6 grid gap-5">
                <flux:input
                    wire:model="nome"
                    label="Nome"
                    placeholder="Flyer Setembro"
                    description="Só para si, para reconhecer o código na lista. Não aparece em lado nenhum público."
                    autofocus
                />

                <flux:input
                    wire:model="destino"
                    type="url"
                    label="URL de destino"
                    placeholder="https://loja.exemplo.pt/campanhas/setembro"
                    description="Para onde a pessoa é levada ao ler o código. Pode mudar sempre que quiser."
                />

                <div class="flex items-center justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                    <flux:button size="sm" :href="route('dashboard')" wire:navigate>Cancelar</flux:button>

                    <flux:button type="submit" variant="primary" size="sm">Criar QR</flux:button>
                </div>
            </form>
        </div>

        <flux:text size="sm" class="mt-3">
            O endereço curto é gerado automaticamente e nunca muda depois de criado.
        </flux:text>
    </div>
</div>
