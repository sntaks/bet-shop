<?php

namespace App\Filament\Pages;

use App\Enums\ActivityAction;
use App\Filament\Concerns\HandlesKadiResults;
use App\Filament\Support\Format;
use App\Models\ActivityLog;
use App\Services\KadiResult;
use App\Services\KadiShopApi;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Cash withdrawals players asked for at this shop (GET /shop/withdrawals), by status, with the
 * pay and reject steps. Paying takes the player's 6-digit pickup code; every code attempt gets
 * a new Idempotency-Key and only an attempt whose outcome is unknown is retried, with the same
 * key and code. Cash is handed over only after a 200. Pickup codes are never logged.
 */
class Withdrawals extends Page implements HasTable
{
    use HandlesKadiResults;
    use InteractsWithTable;

    /**
     * @var array<string, string>
     */
    public const array STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ];

    private const string PENDING_COUNT_CACHE_PREFIX = 'kadi:pending-withdrawals:';

    private const int PENDING_COUNT_SECONDS = 30;

    /**
     * KadiApi cannot filter by player, so a search looks through this many of the newest.
     */
    public const int SEARCH_WINDOW = 200;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $title = 'Cash withdrawals';

    protected static ?string $slug = 'withdrawals';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.withdrawals';

    #[Locked]
    public string $status = 'pending';

    /**
     * Shop name and number for receipts, and the float.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $shop = null;

    public ?string $listError = null;

    /**
     * The withdrawal being paid, as listed.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $paying = null;

    public string $pickupCode = '';

    #[Locked]
    public ?string $payKey = null;

    /**
     * The code of the attempt in flight, kept only to retry it when its outcome is unknown.
     */
    #[Locked]
    public ?string $payCode = null;

    #[Locked]
    public bool $payOutcomeUnknown = false;

    /**
     * No more attempts on this withdrawal: it was cancelled, expired, closed or is not ours.
     */
    #[Locked]
    public bool $payClosed = false;

    #[Locked]
    public ?string $payError = null;

    #[Locked]
    public ?int $attemptsLeft = null;

    /**
     * The `data` of the 200: the receipt.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $paid = null;

    /**
     * A rejection whose outcome is unknown: retried with the same key when the reason is the same.
     *
     * @var array{withdrawal_id: string, reason: string, key: string}|null
     */
    #[Locked]
    public ?array $pendingReject = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('withdrawals.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::pendingCount();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Pending cash withdrawals';
    }

    /**
     * How many withdrawals wait at this shop, asked at most every 30 seconds.
     */
    public static function pendingCount(): ?int
    {
        try {
            $count = Cache::remember(self::pendingCountCacheKey(), self::PENDING_COUNT_SECONDS, function (): int|false {
                $result = app(KadiShopApi::class)->withdrawals('pending', 1, 1);

                return $result->ok() ? ($result->pagination()['total'] ?? count($result->items())) : false;
            });
        } catch (Throwable) {
            return null;
        }

        return is_int($count) ? $count : null;
    }

    private static function pendingCountCacheKey(): string
    {
        return self::PENDING_COUNT_CACHE_PREFIX.(auth()->user()->shop_id ?? 'none');
    }

    public function mount(KadiShopApi $api): void
    {
        $result = $api->me();

        if (! $this->handledCommonOutcome($result) && $result->ok()) {
            $this->shop = $result->data;
        }
    }

    public function showStatus(string $status): void
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return;
        }

        $this->status = $status;
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => Cache::forget(self::pendingCountCacheKey())),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->fetchWithdrawals($search, (int) $page, (int) $recordsPerPage))
            ->searchable()
            ->searchPlaceholder('Player number')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Asked')
                    ->formatStateUsing(fn (?string $state): string => Format::dateTime($state)),
                TextColumn::make('player.name')
                    ->label('Player')
                    ->weight('bold')
                    ->description(fn (array $record): string => Format::playerNo(self::playerNoOf($record))),
                TextColumn::make('amount')
                    ->alignEnd()
                    ->weight('bold')
                    ->size('lg')
                    ->formatStateUsing(fn (mixed $state): string => Format::money($state)),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->visible(fn (): bool => $this->status === 'pending')
                    ->formatStateUsing(fn (?string $state): string => Format::dateTime($state))
                    ->description(fn (array $record): ?string => self::expiresIn($record)),
                TextColumn::make('failed_attempts')
                    ->label('Wrong codes')
                    ->visible(fn (): bool => $this->status === 'pending')
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->visible(fn (): bool => $this->status !== 'pending')
                    ->formatStateUsing(fn (?string $state): string => Format::dateTime($state))
                    ->description(fn (array $record): ?string => is_string($record['close_reason'] ?? null) ? $record['close_reason'] : null),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Pay')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->button()
                    ->size('lg')
                    ->visible(fn (): bool => $this->status === 'pending' && (auth()->user()?->hasPermissionTo('withdrawals.pay') ?? false))
                    ->action(fn (array $record) => $this->startPaying($record)),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->size('lg')
                    ->visible(fn (): bool => $this->status === 'pending' && (auth()->user()?->hasPermissionTo('withdrawals.reject') ?? false))
                    ->modalHeading(fn (array $record): string => 'Turn down '.Format::money($record['amount'] ?? 0).' for '.($record['player']['name'] ?? 'the player').'?')
                    ->modalDescription('The money goes back to the player\'s wallet. They see your reason.')
                    ->modalSubmitActionLabel('Reject')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->placeholder('Not enough cash in the till')
                            ->default(fn (array $record): ?string => ($this->pendingReject['withdrawal_id'] ?? null) === ($record['withdrawal_id'] ?? null) ? $this->pendingReject['reason'] ?? null : null)
                            ->required()
                            ->minLength(3)
                            ->maxLength(255),
                    ])
                    ->action(fn (array $record, array $data) => $this->reject($record, (string) $data['reason'])),
            ])
            ->emptyStateHeading(fn (): string => $this->listError === null ? 'No '.Str::lower(self::STATUSES[$this->status]).' withdrawals' : 'Withdrawals could not be shown')
            ->emptyStateDescription(fn (): ?string => $this->listError);
    }

    /**
     * Open the pay step for a pending withdrawal.
     *
     * @param  array<mixed>  $record
     */
    public function startPaying(array $record): void
    {
        if ($this->payOutcomeUnknown || ! is_string($record['withdrawal_id'] ?? null)) {
            return;
        }

        $this->resetPayment();
        $this->paying = $record;
    }

    /**
     * Pay with the code the player gives; when the last attempt's outcome is unknown this is
     * the safe retry, with the same key and the same code.
     */
    public function pay(KadiShopApi $api): void
    {
        if ($this->paying === null || $this->paid !== null || $this->payClosed) {
            return;
        }

        $this->resetErrorBag();

        if (! $this->payOutcomeUnknown) {
            if (preg_match('/^\d{6}$/', $this->pickupCode) !== 1) {
                $this->addError('pickupCode', 'Enter the 6-digit code from the player.');

                return;
            }

            $this->payKey = (string) Str::uuid();
            $this->payCode = $this->pickupCode;
        }

        $this->payError = null;

        $result = $api->payWithdrawal((string) $this->paying['withdrawal_id'], (string) $this->payCode, (string) $this->payKey);

        if ($result->ok()) {
            $this->record(ActivityAction::WithdrawalPaid, $result);
            $this->paid = $result->data;
            $this->forgetAttempt();

            if ($this->shop !== null && array_key_exists('float_balance', $result->data)) {
                $this->shop['float_balance'] = $result->data['float_balance'];
            }

            Cache::forget(self::pendingCountCacheKey());

            return;
        }

        if ($result->outcomeUnknown()) {
            $this->record(ActivityAction::WithdrawalPayOutcomeUnknown, $result);
            $this->payOutcomeUnknown = true;

            return;
        }

        // A definite answer: the next code is a new attempt with a new key.
        $this->record(ActivityAction::WithdrawalPayRefused, $result);
        $this->forgetAttempt();

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        if ($result->is('wrong_code')) {
            $this->attemptsLeft = is_numeric($result->field('attempts_left')) ? (int) $result->field('attempts_left') : null;
            $this->payError = match ($this->attemptsLeft) {
                null => 'That pickup code is wrong.',
                1 => 'That pickup code is wrong. 1 attempt left.',
                default => "That pickup code is wrong. {$this->attemptsLeft} attempts left.",
            };

            return;
        }

        $this->payClosed = true;
        Cache::forget(self::pendingCountCacheKey());

        $this->payError = match (true) {
            $result->is('too_many_attempts') => 'Too many wrong codes. Kadi Kings cancelled the request and gave the money back to the player.',
            $result->is('expired') => 'This request expired after 24 hours. The money went back to the player.',
            $result->is('not_pending') => 'This request is no longer pending: it was already paid, cancelled, rejected or it expired.',
            $result->is('withdrawal_not_found') || $result->status === 404 => 'This request is not one of this shop\'s.',
            $result->message !== '' => $result->message,
            default => 'Kadi Kings refused the payment.',
        };
    }

    /**
     * Leave the pay step. While a payment's outcome is unknown the view only offers this after a
     * warning: the cashier must check the Paid tab before paying that player any other way.
     */
    public function closePayment(): void
    {
        $this->resetPayment();
    }

    /**
     * @param  array<mixed>  $record
     */
    public function reject(array $record, string $reason): void
    {
        $withdrawalId = $record['withdrawal_id'] ?? null;

        if (! is_string($withdrawalId)) {
            return;
        }

        $retrying = $this->pendingReject !== null
            && $this->pendingReject['withdrawal_id'] === $withdrawalId
            && $this->pendingReject['reason'] === $reason;

        $key = $retrying ? $this->pendingReject['key'] : (string) Str::uuid();

        $result = app(KadiShopApi::class)->rejectWithdrawal($withdrawalId, $reason, $key);

        $this->recordRejection($result, $record, $key, $reason);

        if ($result->outcomeUnknown()) {
            $this->pendingReject = ['withdrawal_id' => $withdrawalId, 'reason' => $reason, 'key' => $key];

            Notification::make()
                ->title('Not sure it was rejected')
                ->body('Press Reject again with the same reason. It is safe: it will not happen twice.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->pendingReject = null;

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        if (! $result->ok()) {
            Notification::make()
                ->title('Not rejected')
                ->body($result->message !== '' ? $result->message : 'Kadi Kings refused the rejection.')
                ->danger()
                ->send();

            return;
        }

        if (($this->paying['withdrawal_id'] ?? null) === $withdrawalId) {
            $this->resetPayment();
        }

        Cache::forget(self::pendingCountCacheKey());

        Notification::make()
            ->title('Rejected')
            ->body('The money is back in the player\'s wallet.')
            ->success()
            ->send();
    }

    /**
     * @return LengthAwarePaginator<string, array<mixed>>
     */
    private function fetchWithdrawals(?string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $digits = preg_replace('/\D+/', '', (string) $search) ?? '';
        $searching = $digits !== '';

        $result = app(KadiShopApi::class)->withdrawals(
            $this->status,
            $searching ? 1 : $page,
            $searching ? self::SEARCH_WINDOW : $perPage,
        );

        $this->listError = null;
        $rows = [];

        if ($this->handledCommonOutcome($result) || ! $result->ok()) {
            $this->listError ??= match (true) {
                $result->outcomeUnknown() => 'Kadi Kings could not be reached. Try again in a moment.',
                $result->rateLimited() => 'Too many requests, wait a moment.',
                $result->message !== '' => $result->message,
                default => 'Kadi Kings refused the request.',
            };
        }

        foreach ($result->ok() ? $result->items() : [] as $item) {
            $withdrawalId = $item['withdrawal_id'] ?? null;

            if (is_string($withdrawalId) && (! $searching || str_contains(self::playerNoOf($item), $digits))) {
                $rows[$withdrawalId] = $item;
            }
        }

        $pagination = $result->ok() ? $result->pagination() : null;

        if ($this->status === 'pending' && ! $searching && $pagination !== null) {
            Cache::put(self::pendingCountCacheKey(), $pagination['total'], self::PENDING_COUNT_SECONDS);
        }

        if ($searching) {
            return new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage, true), count($rows), $perPage, $page);
        }

        return new LengthAwarePaginator(
            $rows,
            $pagination['total'] ?? count($rows),
            $pagination['per_page'] ?? $perPage,
            $pagination['page'] ?? $page,
        );
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function playerNoOf(array $record): string
    {
        $player = $record['player'] ?? null;

        return is_array($player) && is_string($player['player_no'] ?? null) ? $player['player_no'] : '';
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function expiresIn(array $record): ?string
    {
        $expiresAt = $record['expires_at'] ?? null;

        if (! is_string($expiresAt)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->diffForHumans(['parts' => 2]);
        } catch (Throwable) {
            return null;
        }
    }

    private function forgetAttempt(): void
    {
        $this->payKey = null;
        $this->payCode = null;
        $this->pickupCode = '';
        $this->payOutcomeUnknown = false;
    }

    private function resetPayment(): void
    {
        $this->resetErrorBag();
        $this->reset(['paying', 'pickupCode', 'payKey', 'payCode', 'payOutcomeUnknown', 'payClosed', 'payError', 'attemptsLeft', 'paid']);
    }

    private function record(ActivityAction $action, KadiResult $result): void
    {
        ActivityLog::record($action, [
            'subject_id' => is_string($this->paying['withdrawal_id'] ?? null) ? $this->paying['withdrawal_id'] : null,
            'player_no' => self::playerNoOf($this->paying ?? []) ?: null,
            'amount' => is_numeric($this->paying['amount'] ?? null) ? $this->paying['amount'] : null,
            'http_status' => $result->status,
            'api_code' => $result->code,
            'idempotency_key' => $this->payKey,
            'details' => array_filter([
                'message' => $result->message,
                'attempts_left' => $result->field('attempts_left'),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }

    /**
     * @param  array<mixed>  $record
     */
    private function recordRejection(KadiResult $result, array $record, string $key, string $reason): void
    {
        ActivityLog::record($result->ok() ? ActivityAction::WithdrawalRejected : ActivityAction::WithdrawalRejectFailed, [
            'subject_id' => is_string($record['withdrawal_id'] ?? null) ? $record['withdrawal_id'] : null,
            'player_no' => self::playerNoOf($record) ?: null,
            'amount' => is_numeric($record['amount'] ?? null) ? $record['amount'] : null,
            'http_status' => $result->status,
            'api_code' => $result->code,
            'idempotency_key' => $key,
            'details' => ['reason' => $reason, 'message' => $result->message],
        ]);
    }
}
