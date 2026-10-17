@props(['withdrawal', 'shop' => null])

@php
    use App\Filament\Support\Format;
@endphp

<div {{ $attributes->class('kk-receipt') }} data-test="withdrawal-receipt">
    <p class="text-center text-sm font-bold uppercase">Kadi Kings</p>
    <p class="text-center">{{ $shop['name'] ?? ($withdrawal['shop_name'] ?? '') }} · {{ $shop['shop_no'] ?? ($withdrawal['shop_no'] ?? '') }}</p>
    <p class="mt-1 text-center font-bold">CASH WITHDRAWAL</p>

    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
        <dt>Date</dt>
        <dd class="text-right">{{ Format::dateTime($withdrawal['closed_at'] ?? now()->toIso8601String()) }}</dd>
        <dt>Withdrawal</dt>
        <dd class="text-right break-all">{{ $withdrawal['withdrawal_id'] ?? '' }}</dd>
        <dt>Player</dt>
        <dd class="text-right">{{ Format::playerNo($withdrawal['player']['player_no'] ?? null) }}</dd>
        <dt>Name</dt>
        <dd class="text-right">{{ $withdrawal['player']['name'] ?? '' }}</dd>
    </dl>

    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 border-t border-dashed border-black pt-2">
        <dt class="font-bold">Cash paid</dt>
        <dd class="text-right font-bold">{{ Format::money($withdrawal['amount'] ?? 0) }}</dd>
    </dl>

    <p class="mt-3 border-t border-dashed border-black pt-2 text-center">Paid against the player's pickup code.</p>
</div>
