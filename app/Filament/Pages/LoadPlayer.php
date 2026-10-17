<?php

namespace App\Filament\Pages;

use App\Enums\ActivityAction;
use App\Filament\Concerns\HandlesKadiResults;
use App\Filament\Support\Format;
use App\Models\ActivityLog;
use App\Services\KadiResult;
use App\Services\KadiShopApi;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * The main counter screen: look the player up, confirm the masked name with them, take the
 * cash, load. Each load attempt gets one Idempotency-Key, made when Load is pressed and kept
 * only while its outcome is unknown, so "Retry (safe)" can never load twice. A new player or
 * a new amount always gets a new key.
 */
class LoadPlayer extends Page
{
    use HandlesKadiResults;

    public const string STEP_PLAYER = 'player';

    public const string STEP_AMOUNT = 'amount';

    public const string STEP_CONFIRM = 'confirm';

    public const string STEP_DONE = 'done';

    /**
     * Used when GET /shop/me could not be read; KadiApi checks the real limits anyway.
     */
    private const int FALLBACK_MIN_AMOUNT = 50;

    private const int FALLBACK_MAX_AMOUNT = 20_000;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Load player';

    protected static ?string $title = 'Load a player';

    protected static ?string $slug = 'load';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.load-player';

    #[Locked]
    public string $step = self::STEP_PLAYER;

    public string $playerNoInput = '';

    public string $amountInput = '';

    /**
     * The player from the lookup: player_no, masked name, active.
     *
     * @var array{player_no: string, name: string, active: bool}|null
     */
    #[Locked]
    public ?array $player = null;

    #[Locked]
    public ?int $amount = null;

    #[Locked]
    public ?string $idempotencyKey = null;

    #[Locked]
    public bool $outcomeUnknown = false;

    #[Locked]
    public ?string $refusal = null;

    /**
     * The `data` of the 201: the receipt.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $load = null;

    /**
     * The `data` of GET /shop/me: shop name and number for the receipt, limits, float.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $shop = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('loads.create') ?? false;
    }

    public function mount(KadiShopApi $api): void
    {
        $result = $api->me();

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        $this->shop = $result->ok() ? $result->data : null;
    }

    public function lookUp(KadiShopApi $api): void
    {
        $this->resetErrorBag();
        $this->refusal = null;
        $this->player = null;

        $playerNo = KadiShopApi::normalizePlayerNo($this->playerNoInput);

        if (preg_match('/^\d{8}$/', $playerNo) !== 1) {
            $this->addError('playerNoInput', 'Enter the 8-digit player number.');

            return;
        }

        $result = $api->lookupPlayer($playerNo);

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        if (! $result->ok()) {
            $this->addError('playerNoInput', match (true) {
                $result->is('invalid_player_no') => 'That number is not valid. Check it with the player.',
                $result->is('player_not_found') => 'No player has that number. Check it with the player.',
                $result->outcomeUnknown() => 'Kadi Kings could not be reached. Try again.',
                default => $result->message !== '' ? $result->message : 'The player could not be checked. Try again.',
            });

            return;
        }

        $this->player = [
            'player_no' => (string) ($result->data['player_no'] ?? $playerNo),
            'name' => (string) ($result->data['name'] ?? ''),
            'active' => (bool) ($result->data['active'] ?? false),
        ];

        if (! $this->player['active']) {
            $this->refusal = 'This account is not active.';

            return;
        }

        $this->step = self::STEP_AMOUNT;
    }

    public function review(): void
    {
        if ($this->step !== self::STEP_AMOUNT) {
            return;
        }

        $this->validate(
            ['amountInput' => ['required', 'integer', 'min:'.$this->minAmount(), 'max:'.$this->maxAmount()]],
            [
                'amountInput.required' => 'Enter the cash the player handed over.',
                'amountInput.integer' => 'Enter whole shillings.',
                'amountInput.min' => 'The smallest load is '.number_format($this->minAmount()).' shillings.',
                'amountInput.max' => 'The largest load is '.number_format($this->maxAmount()).' shillings.',
            ],
        );

        $this->amount = (int) $this->amountInput;
        $this->idempotencyKey = null;
        $this->refusal = null;
        $this->step = self::STEP_CONFIRM;
    }

    /**
     * Load the player; when the last attempt's outcome is unknown this is the safe retry, with
     * the same key and the same player and amount.
     */
    public function loadPlayer(KadiShopApi $api): void
    {
        if ($this->step !== self::STEP_CONFIRM || $this->player === null || $this->amount === null) {
            return;
        }

        $this->idempotencyKey ??= (string) Str::uuid();
        $this->refusal = null;

        $result = $api->load($this->player['player_no'], $this->amount, $this->idempotencyKey);

        if ($result->ok()) {
            $this->loaded($result);

            return;
        }

        if ($result->outcomeUnknown()) {
            $this->outcomeUnknown = true;
            $this->record(ActivityAction::LoadOutcomeUnknown, $result);

            return;
        }

        // A definite refusal is stored by KadiApi under this key: the next press is a new attempt.
        $this->record(ActivityAction::LoadRefused, $result);
        $this->outcomeUnknown = false;
        $this->idempotencyKey = null;

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        $this->refusal = $this->refusalMessage($result);
    }

    public function changeAmount(): void
    {
        if ($this->outcomeUnknown || ! in_array($this->step, [self::STEP_AMOUNT, self::STEP_CONFIRM], true)) {
            return;
        }

        $this->idempotencyKey = null;
        $this->amount = null;
        $this->refusal = null;
        $this->step = self::STEP_AMOUNT;
    }

    /**
     * Start again with a new player. While a load's outcome is unknown the view only offers this
     * after a warning: the cashier must check Load history before loading that player again.
     */
    public function startOver(): void
    {
        $this->resetErrorBag();
        $this->reset(['step', 'playerNoInput', 'amountInput', 'player', 'amount', 'idempotencyKey', 'outcomeUnknown', 'refusal', 'load']);
    }

    public function minAmount(): int
    {
        return (int) ($this->shop['load_limits']['min_amount'] ?? self::FALLBACK_MIN_AMOUNT);
    }

    public function maxAmount(): int
    {
        return (int) ($this->shop['load_limits']['max_amount'] ?? self::FALLBACK_MAX_AMOUNT);
    }

    private function loaded(KadiResult $result): void
    {
        $this->load = $result->data;
        $this->record(ActivityAction::PlayerLoaded, $result);

        if ($this->shop !== null && array_key_exists('float_balance', $result->data)) {
            $this->shop['float_balance'] = $result->data['float_balance'];
        }

        $this->outcomeUnknown = false;
        $this->idempotencyKey = null;
        $this->step = self::STEP_DONE;
    }

    private function refusalMessage(KadiResult $result): string
    {
        $amountErrors = $result->errors['amount'] ?? null;

        return match (true) {
            is_array($amountErrors) && is_string($amountErrors[0] ?? null) => $amountErrors[0],
            $result->is('insufficient_float') => rtrim($result->message, '.').'. Float left: '.Format::money($result->field('float_balance')).'.',
            $result->message !== '' => $result->message,
            default => 'The load was refused.',
        };
    }

    private function record(ActivityAction $action, KadiResult $result): void
    {
        ActivityLog::record($action, [
            'subject_id' => is_string($result->data['load_id'] ?? null) ? $result->data['load_id'] : null,
            'player_no' => $this->player['player_no'] ?? null,
            'amount' => $this->amount,
            'http_status' => $result->status,
            'api_code' => $result->code,
            'idempotency_key' => $this->idempotencyKey,
            'details' => $result->ok() ? [
                'excise_amount' => $result->data['excise_amount'] ?? null,
                'net_amount' => $result->data['net_amount'] ?? null,
                'message' => $result->message,
            ] : ['message' => $result->message],
        ]);
    }
}
