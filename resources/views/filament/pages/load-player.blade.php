@php
    use App\Filament\Pages\LoadPlayer;
    use App\Filament\Support\Format;
@endphp

<x-filament-panels::page>
    <div class="kk-touch mx-auto grid w-full max-w-2xl gap-6">
        @if ($step === LoadPlayer::STEP_PLAYER)
            <x-filament::section heading="1. Player number">
                <form wire:submit="lookUp" class="grid gap-4">
                    <label for="playerNoInput" class="text-base text-gray-300">Ask the player for the number in their app (it looks like 1234 5678).</label>

                    <x-filament::input.wrapper :valid="! $errors->has('playerNoInput')">
                        <x-filament::input
                            id="playerNoInput"
                            type="text"
                            wire:model="playerNoInput"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="11"
                            placeholder="1234 5678"
                            autofocus
                            class="py-4 text-center font-mono text-4xl tracking-widest"
                        />
                    </x-filament::input.wrapper>

                    @error('playerNoInput')
                        <p class="text-base font-medium text-danger-400">{{ $message }}</p>
                    @enderror

                    <x-filament::button type="submit" size="xl" wire:target="lookUp">Look up</x-filament::button>
                </form>

                @if ($player !== null && ! $player['active'])
                    <div class="mt-6 rounded-lg bg-danger-500/10 p-4 ring-1 ring-danger-500/30">
                        <p class="font-display text-3xl font-bold text-ivory">{{ $player['name'] }}</p>
                        <p class="text-gray-400">{{ Format::playerNo($player['player_no']) }}</p>
                        <p class="mt-2 text-base font-medium text-danger-400">{{ $refusal }}</p>
                    </div>
                @endif
            </x-filament::section>
        @endif

        @if (in_array($step, [LoadPlayer::STEP_AMOUNT, LoadPlayer::STEP_CONFIRM], true) && $player !== null)
            <x-filament::section>
                <p class="text-base font-medium text-gold">Confirm with the player: is this you?</p>
                <p class="mt-1 font-display text-4xl font-bold text-ivory" data-test="player-name">{{ $player['name'] }}</p>
                <p class="text-lg text-gray-400">{{ Format::playerNo($player['player_no']) }}</p>

                @unless ($outcomeUnknown)
                    <div class="mt-4">
                        <x-filament::button color="gray" wire:click="startOver">Not them? New player</x-filament::button>
                    </div>
                @endunless
            </x-filament::section>
        @endif

        @if ($step === LoadPlayer::STEP_AMOUNT)
            <x-filament::section heading="2. Cash received">
                <form wire:submit="review" class="grid gap-4" x-data="{ amount: @js($amountInput) }">
                    <x-filament::input.wrapper prefix="KES" :valid="! $errors->has('amountInput')">
                        <x-filament::input
                            type="text"
                            wire:model="amountInput"
                            x-on:input="amount = $event.target.value"
                            inputmode="numeric"
                            autocomplete="off"
                            autofocus
                            class="py-4 font-display text-4xl"
                        />
                    </x-filament::input.wrapper>

                    <p class="text-sm text-gray-400">Whole shillings, {{ number_format($this->minAmount()) }} to {{ number_format($this->maxAmount()) }}.</p>

                    <p class="text-base text-gray-300" x-show="Number(amount) > 0" x-cloak>
                        The player gets
                        <strong class="text-ivory" x-text="'KES ' + (Math.floor(Number(amount)) * 0.95).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                        after 5% excise duty.
                    </p>

                    @error('amountInput')
                        <p class="text-base font-medium text-danger-400">{{ $message }}</p>
                    @enderror

                    <x-filament::button type="submit" size="xl">Next</x-filament::button>
                </form>
            </x-filament::section>
        @endif

        @if ($step === LoadPlayer::STEP_CONFIRM)
            <x-filament::section heading="3. Take the cash, then load">
                <p class="text-xl text-ivory">
                    Take <strong class="font-display text-3xl text-gold">{{ Format::money($amount) }}</strong> cash from the player, then press Load.
                </p>
                <p class="mt-2 text-base text-gray-400">Loads can't be undone here. For a mistake, call Kadi Kings.</p>

                @if ($outcomeUnknown)
                    <div class="mt-4 rounded-lg bg-warning-500/10 p-4 text-base text-warning-300 ring-1 ring-warning-500/30" role="alert">
                        <p class="font-semibold">Not sure the load went through.</p>
                        <p>Retrying is safe: it will never load the player twice.</p>
                    </div>
                @endif

                @if ($refusal !== null)
                    <div class="mt-4 rounded-lg bg-danger-500/10 p-4 text-base font-medium text-danger-400 ring-1 ring-danger-500/30" role="alert">
                        {{ $refusal }}
                    </div>
                @endif

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <x-filament::button size="xl" wire:click="loadPlayer" wire:loading.attr="disabled" wire:target="loadPlayer" class="sm:col-span-2">
                        {{ $outcomeUnknown ? 'Retry (safe)' : 'Load' }}
                    </x-filament::button>

                    @if ($outcomeUnknown)
                        <x-filament::button
                            color="gray"
                            wire:click="startOver"
                            wire:confirm="Only give up after checking Load history. Loading this player again could load them twice."
                            class="sm:col-span-2"
                        >
                            Give up and check Load history
                        </x-filament::button>
                    @else
                        <x-filament::button color="gray" wire:click="changeAmount">Change amount</x-filament::button>
                        <x-filament::button color="gray" wire:click="startOver">New player</x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($step === LoadPlayer::STEP_DONE && $load !== null)
            <x-filament::section>
                <p class="text-base font-medium text-success-400">Loaded.</p>
                <p class="mt-1 text-xl text-ivory">
                    {{ $load['player']['name'] ?? '' }} gets
                    <strong class="font-display text-3xl text-gold">{{ Format::money($load['net_amount'] ?? 0) }}</strong>
                </p>

                @if (isset($shop['float_balance']))
                    <p class="mt-2 text-base text-gray-400">Float left: {{ Format::money($shop['float_balance']) }}</p>
                @endif

                <div class="mt-6">
                    <x-receipt.load :load="$load" :shop="$shop" />
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <x-filament::button size="xl" color="gray" x-on:click="window.print()">Print receipt</x-filament::button>
                    <x-filament::button size="xl" wire:click="startOver">Load another player</x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
