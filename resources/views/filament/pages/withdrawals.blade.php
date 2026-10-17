@php
    use App\Filament\Pages\Withdrawals;
    use App\Filament\Support\Format;
@endphp

<x-filament-panels::page>
    <div class="kk-touch grid gap-6">
        @if ($paying !== null)
            <x-filament::section>
                @if ($paid !== null)
                    <p class="text-base font-medium text-success-400">Paid. KadiApi confirmed it.</p>
                    <p class="mt-2 font-display text-5xl font-bold text-gold" role="status">
                        Hand the player {{ Format::money($paid['amount'] ?? 0) }}
                    </p>

                    @if (isset($shop['float_balance']))
                        <p class="mt-2 text-base text-gray-400">Float now: {{ Format::money($shop['float_balance']) }}</p>
                    @endif

                    <div class="mt-6">
                        <x-receipt.withdrawal :withdrawal="$paid" :shop="$shop" />
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <x-filament::button size="xl" color="gray" x-on:click="window.print()">Print receipt</x-filament::button>
                        <x-filament::button size="xl" wire:click="closePayment">Done</x-filament::button>
                    </div>
                @else
                    <p class="text-sm font-semibold uppercase tracking-wider text-gray-400">Pay a withdrawal</p>
                    <p class="mt-1 font-display text-4xl font-bold text-ivory">{{ $paying['player']['name'] ?? '' }}</p>
                    <p class="text-lg text-gray-400">{{ Format::playerNo($paying['player']['player_no'] ?? null) }}</p>
                    <p class="mt-2 font-display text-3xl font-bold text-gold">{{ Format::money($paying['amount'] ?? 0) }}</p>

                    @if ($payClosed)
                        <div class="mt-4 rounded-lg bg-danger-500/10 p-4 text-base font-medium text-danger-400 ring-1 ring-danger-500/30" role="alert">
                            <p>{{ $payError }}</p>
                            <p class="mt-1 font-semibold">Do not hand over cash.</p>
                        </div>

                        <div class="mt-6">
                            <x-filament::button size="xl" color="gray" wire:click="closePayment">Close</x-filament::button>
                        </div>
                    @else
                        <form wire:submit="pay" class="mt-6 grid gap-4">
                            @if ($payOutcomeUnknown)
                                <div class="rounded-lg bg-warning-500/10 p-4 text-base text-warning-300 ring-1 ring-warning-500/30" role="alert">
                                    <p class="font-semibold">Not sure it was paid.</p>
                                    <p>Do not hand over cash until it is confirmed. Retrying is safe: it will never pay twice.</p>
                                </div>
                            @else
                                <label for="pickupCode" class="text-base text-gray-300">Ask the player for the 6-digit pickup code in their app.</label>

                                <x-filament::input.wrapper :valid="! $errors->has('pickupCode')">
                                    <x-filament::input
                                        id="pickupCode"
                                        type="text"
                                        wire:model="pickupCode"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        maxlength="6"
                                        placeholder="••••••"
                                        autofocus
                                        class="py-4 text-center font-mono text-4xl tracking-[0.5em]"
                                    />
                                </x-filament::input.wrapper>

                                @error('pickupCode')
                                    <p class="text-base font-medium text-danger-400">{{ $message }}</p>
                                @enderror
                            @endif

                            @if ($payError !== null)
                                <div class="rounded-lg bg-danger-500/10 p-4 text-base font-medium text-danger-400 ring-1 ring-danger-500/30" role="alert">
                                    <p>{{ $payError }}</p>

                                    @if ($attemptsLeft === 1)
                                        <p class="mt-1 font-semibold">One more wrong code cancels the request and gives the money back to the player.</p>
                                    @endif
                                </div>
                            @endif

                            <p class="text-sm text-gray-400">Hand over cash only after this screen says so.</p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-filament::button type="submit" size="xl" wire:loading.attr="disabled" wire:target="pay" class="sm:col-span-2">
                                    {{ $payOutcomeUnknown ? 'Retry (safe)' : 'Pay' }}
                                </x-filament::button>

                                @if ($payOutcomeUnknown)
                                    <x-filament::button
                                        color="gray"
                                        wire:click="closePayment"
                                        wire:confirm="Only give up after checking the Paid tab. Do not hand over cash for this request unless it shows there."
                                        class="sm:col-span-2"
                                    >
                                        Give up and check the Paid tab
                                    </x-filament::button>
                                @else
                                    <x-filament::button color="gray" wire:click="closePayment" class="sm:col-span-2">Cancel</x-filament::button>
                                @endif
                            </div>
                        </form>
                    @endif
                @endif
            </x-filament::section>
        @endif

        <x-filament::tabs label="Withdrawal status">
            @foreach (Withdrawals::STATUSES as $value => $label)
                <x-filament::tabs.item :active="$status === $value" wire:click="showStatus('{{ $value }}')">
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
