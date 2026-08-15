<?php

use App\Models\ApiToken;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tokens de API')] class extends Component
{
    public string $nome = '';

    /**
     * O token em claro vive só nesta propriedade e só até à próxima navegação.
     * Em base de dados fica o hash e mais nada — se o utilizador o perder, tem
     * de criar outro.
     */
    public ?string $tokenEmClaro = null;

    /**
     * @return Collection<int, ApiToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        /** @var Collection<int, ApiToken> $tokens */
        $tokens = auth()->user()->tokens()->latest()->get();

        return $tokens;
    }

    public function criar(): void
    {
        $this->nome = trim($this->nome);

        $this->validate(
            ['nome' => ['required', 'string', 'max:255']],
            ['nome.required' => 'Dê um nome ao token. Sem nome, daqui a seis meses não vai saber qual revogar.']
        );

        $novo = auth()->user()->createToken($this->nome);

        // Os últimos caracteres do token em claro, para o utilizador o
        // reconhecer na lista. É a última vez que o temos em mãos.
        $novo->accessToken->forceFill([
            'ultimos_caracteres' => mb_substr($novo->plainTextToken, -4),
        ])->save();

        // Sem o "{id}|" que o Sanctum põe à cabeça. O que se entrega a quem vai
        // colar isto numa ferramenta é o segredo e mais nada — e o Sanctum
        // continua a reconhecê-lo, pelo hash, que é coluna única.
        $this->tokenEmClaro = str($novo->plainTextToken)->after('|')->value();
        $this->nome = '';

        unset($this->tokens);

        Flux::modal('criar-token')->close();
    }

    public function revogar(int $id): void
    {
        $token = auth()->user()->tokens()->whereKey($id)->first();

        abort_if($token === null, 404);

        $token->revogar();

        unset($this->tokens);

        Flux::toast(variant: 'success', text: 'Token revogado.');
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <flux:heading size="lg">Tokens de API</flux:heading>

        <flux:modal.trigger name="criar-token">
            <flux:button size="sm" :variant="$this->tokens->isEmpty() ? 'outline' : 'primary'">Criar token</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($tokenEmClaro !== null)
        {{--
            O token em claro, mostrado uma única vez. Faixa neutra: o peso vem
            do texto e da posição, não da cor.
        --}}
        <div class="mb-4 rounded-card border border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Copie o token agora</flux:heading>

            <flux:text class="mt-1">
                É a única vez que ele aparece. Depois de sair desta página fica só o resumo —
                se o perder, tem de criar outro e mudar a ferramenta que o usa.
            </flux:text>

            <div class="mt-3 flex items-center gap-2 rounded-card border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                <x-copy-field class="w-full" :valor="$tokenEmClaro" rotulo="Copiar o token" />
            </div>

            <flux:text size="sm" class="mt-2">
                Envie-o por um canal seguro. Quem o tiver pode criar e alterar todos os seus códigos.
            </flux:text>
        </div>
    @endif

    @if ($this->tokens->isEmpty())
        <x-empty-state
            titulo="Ainda não criou nenhum token"
            descricao="Um token deixa outra ferramenta criar e gerir códigos QR sem passar por aqui — por exemplo, gerar um código por cada produto de um catálogo."
        >
            <x-slot:icone>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true">
                    <path d="M15 7a4 4 0 1 1-3.9 5H7v3H4v-3H2.5L7 7h4.1A4 4 0 0 1 15 7Z" />
                    <path d="M15.5 10h.01" />
                </svg>
            </x-slot:icone>

            <x-slot:acao>
                <flux:modal.trigger name="criar-token">
                    <flux:button size="sm" variant="primary">Criar o primeiro token</flux:button>
                </flux:modal.trigger>
            </x-slot:acao>
        </x-empty-state>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>Token</flux:table.column>
                <flux:table.column>Criado</flux:table.column>
                <flux:table.column>Último uso</flux:table.column>
                <flux:table.column><span class="sr-only">Acções</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->tokens as $token)
                    <flux:table.row :key="$token->id">
                        <flux:table.cell class="max-w-0">
                            <span class="block truncate font-medium" title="{{ $token->name }}">{{ $token->name }}</span>

                            @if ($token->estaRevogado())
                                <flux:badge size="sm" color="zinc" class="mt-0.5">
                                    Revogado {{ $token->revogado_em->diffForHumans() }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="font-mono">
                            {{ config('sanctum.token_prefix') }}…{{ $token->ultimos_caracteres }}
                        </flux:table.cell>

                        <flux:table.cell>{{ $token->created_at->diffForHumans() }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($token->last_used_at === null)
                                <span class="text-zinc-400 dark:text-zinc-500">nunca usado</span>
                            @else
                                {{ $token->last_used_at->diffForHumans() }}
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @unless ($token->estaRevogado())
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="revogar({{ $token->id }})"
                                    wire:confirm="Revogar este token? A ferramenta que o usa deixa de conseguir entrar."
                                >Revogar</flux:button>
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="criar-token" class="md:w-96">
        <form wire:submit="criar" class="grid gap-4">
            <div>
                <flux:heading size="lg">Criar token</flux:heading>

                <flux:text class="mt-1">
                    O nome é só para si, para saber depois onde o pôs.
                </flux:text>
            </div>

            <flux:input wire:model="nome" label="Nome" placeholder="Integração com o catálogo" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button size="sm" type="button">Cancelar</flux:button>
                </flux:modal.close>

                <flux:button size="sm" type="submit" variant="primary">Criar token</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
