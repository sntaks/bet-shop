@props(['load', 'shop' => null])

@php
    use App\Filament\Support\Format;
@endphp

<div {{ $attributes->class('kk-receipt') }} data-test="load-receipt">
    <p class="text-center text-sm font-bold uppercase">Kadi Kings</p>
    <p class="text-center">{{ $shop['name'] ?? '' }} · {{ $shop['shop_no'] ?? ($load['shop_no'] ?? '') }}</p>
    <p class="mt-1 text-center font-bold">CASH LOAD</p>

    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
        <dt>Date</dt>
        <dd class="text-right">{{ Format::dateTime($load['loaded_at'] ?? null) }}</dd>
        <dt>Load</dt>
        <dd class="text-right break-all">{{ $load['load_id'] ?? '' }}</dd>
        <dt>Player</dt>
        <dd class="text-right">{{ Format::playerNo($load['player']['player_no'] ?? null) }}</dd>
        <dt>Name</dt>
        <dd class="text-right">{{ $load['player']['name'] ?? '' }}</dd>
    </dl>

    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 border-t border-dashed border-black pt-2">
        <dt>Cash received</dt>
        <dd class="text-right">{{ Format::money($load['gross_amount'] ?? 0) }}</dd>
        <dt>Excise duty (5%)</dt>
        <dd class="text-right">{{ Format::money($load['excise_amount'] ?? 0) }}</dd>
        <dt class="font-bold">Credited</dt>
        <dd class="text-right font-bold">{{ Format::money($load['net_amount'] ?? 0) }}</dd>
    </dl>

    @if (($load['status'] ?? null) === 'reversed')
        <p class="mt-3 border-t border-dashed border-black pt-2 text-center font-bold">REVERSED BY KADI KINGS {{ Format::dateTime($load['reversed_at'] ?? null) }}</p>
    @endif

    <p class="mt-3 border-t border-dashed border-black pt-2 text-center">Loads cannot be undone at the shop. For a mistake, call Kadi Kings.</p>
</div>
