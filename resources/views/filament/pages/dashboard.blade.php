@php
    use App\Filament\Support\Format;
@endphp

<x-filament-panels::page>
    @if ($shop === null)
        <x-filament::section>
            <p class="text-base text-gray-300">Kadi Kings could not be reached, so the float is not shown. Press Refresh to try again.</p>
        </x-filament::section>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-2">
                <p class="text-sm font-semibold uppercase tracking-wider text-gray-400">Float</p>
                <p class="mt-1 font-display text-5xl font-bold text-gold sm:text-6xl" data-test="float-balance">
                    {{ Format::money($shop['float_balance'] ?? 0) }}
                </p>

                @if ($this->isFloatLow())
                    <div class="mt-4 rounded-lg bg-danger-500/10 px-4 py-3 text-base font-medium text-danger-400 ring-1 ring-danger-500/30">
                        The float is low (under {{ Format::money($lowFloatThreshold) }}). Buy more float before it runs out.
                    </div>
                @endif

                <p class="mt-4 text-base text-gray-300">
                    To buy float, pay the Kadi Kings paybill with account
                    <strong class="font-semibold text-ivory">{{ $shop['float_bill_ref'] ?? '' }}</strong>.
                    It is credited after Kadi Kings checks it.
                </p>
            </x-filament::section>

            <x-filament::section heading="Today">
                <dl class="grid gap-4">
                    <div>
                        <dt class="text-sm text-gray-400">Loads</dt>
                        <dd class="font-display text-3xl font-bold text-ivory">{{ number_format((int) ($shop['today']['loads'] ?? 0)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Cash taken</dt>
                        <dd class="font-display text-3xl font-bold text-ivory">{{ Format::money($shop['today']['gross_amount'] ?? 0) }}</dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>

        <x-filament::section heading="This shop">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-gray-400">Shop number (players pick this in the app)</dt>
                    <dd class="font-display text-3xl font-bold text-gold">{{ $shop['shop_no'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-400">Shop</dt>
                    <dd class="text-lg text-ivory">{{ $shop['name'] ?? '' }}</dd>
                    <dd class="text-sm text-gray-400">{{ $shop['location'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-400">Load limits</dt>
                    <dd class="text-base text-ivory">
                        {{ Format::money($shop['load_limits']['min_amount'] ?? 0) }} to {{ Format::money($shop['load_limits']['max_amount'] ?? 0) }} a load
                    </dd>
                    <dd class="text-sm text-gray-400">
                        At most {{ Format::money($shop['load_limits']['daily_limit_per_player'] ?? 0) }} a player a day, across all shops
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
