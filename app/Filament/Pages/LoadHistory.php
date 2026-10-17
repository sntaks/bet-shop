<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HandlesKadiResults;
use App\Filament\Support\Format;
use App\Services\KadiShopApi;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * This shop's loads from GET /shop/loads, newest first, for a range of Nairobi days (today by
 * default), with the receipt to reprint. KadiApi pages and filters; nothing is kept here.
 */
class LoadHistory extends Page implements HasTable
{
    use HandlesKadiResults;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Load history';

    protected static ?string $title = 'Load history';

    protected static ?string $slug = 'loads';

    protected static ?int $navigationSort = 2;

    /**
     * Shop name and number for reprinted receipts.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $shop = null;

    /**
     * Why the last fetch showed nothing, when it failed.
     */
    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('loads.view') ?? false;
    }

    public function mount(KadiShopApi $api): void
    {
        $result = $api->me();

        if (! $this->handledCommonOutcome($result) && $result->ok()) {
            $this->shop = $result->data;
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->fetchLoads($filters, (int) $page, (int) $recordsPerPage))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('loaded_at')
                    ->label('Loaded')
                    ->formatStateUsing(fn (?string $state): string => Format::dateTime($state)),
                TextColumn::make('load_id')
                    ->label('Load')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Str::limit($state, 8, '…'))
                    ->tooltip(fn (array $record): string => $record['load_id'])
                    ->copyable()
                    ->copyableState(fn (array $record): string => $record['load_id']),
                TextColumn::make('player.name')
                    ->label('Player')
                    ->description(fn (array $record): string => Format::playerNo($record['player']['player_no'] ?? null)),
                TextColumn::make('gross_amount')
                    ->label('Cash received')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (mixed $state): string => Format::money($state)),
                TextColumn::make('excise_amount')
                    ->label('Excise')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => Format::money($state)),
                TextColumn::make('net_amount')
                    ->label('Credited')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => Format::money($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'completed' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'reversed' ? 'Reversed by Kadi Kings' : Str::headline($state))
                    ->description(fn (array $record): ?string => filled($record['reversed_at'] ?? null) ? Format::dateTime($record['reversed_at']) : null),
            ])
            ->filters([
                Filter::make('loaded')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->default(fn (): string => self::today())
                            ->maxDate(fn (): string => self::today()),
                        DatePicker::make('to')
                            ->label('To')
                            ->default(fn (): string => self::today())
                            ->maxDate(fn (): string => self::today()),
                    ])
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['from'] ?? null) && filled($data['to'] ?? null) => 'Loaded '.$data['from'].' to '.$data['to'],
                        filled($data['from'] ?? null) => 'Loaded from '.$data['from'],
                        filled($data['to'] ?? null) => 'Loaded until '.$data['to'],
                        default => null,
                    }),
            ])
            ->recordActions([
                Action::make('reprint')
                    ->label('Reprint')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->modalHeading('Receipt')
                    ->modalWidth(Width::Small)
                    ->modalContent(fn (array $record): View => view('filament.partials.load-receipt', ['load' => $record, 'shop' => $this->shop]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions([
                        Action::make('print')
                            ->label('Print')
                            ->icon(Heroicon::OutlinedPrinter)
                            ->alpineClickHandler('window.print()'),
                    ]),
            ])
            ->emptyStateHeading(fn (): string => $this->loadError === null ? 'No loads on these days' : 'Load history could not be shown')
            ->emptyStateDescription(fn (): ?string => $this->loadError);
    }

    /**
     * One page of loads, keyed by load_id (the table's record key). Empty, with the reason in
     * $loadError, when KadiApi did not answer with the list.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<string, array<mixed>>
     */
    private function fetchLoads(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $result = app(KadiShopApi::class)->loads(
            from: $filters['loaded']['from'] ?? null,
            to: $filters['loaded']['to'] ?? null,
            page: $page,
            perPage: $perPage,
        );

        $this->loadError = null;
        $rows = [];

        if ($this->handledCommonOutcome($result) || ! $result->ok()) {
            $this->loadError ??= match (true) {
                $result->outcomeUnknown() => 'Kadi Kings could not be reached. Try again in a moment.',
                $result->rateLimited() => 'Too many requests, wait a moment.',
                $result->message !== '' => $result->message,
                default => 'Kadi Kings refused the request.',
            };
        }

        foreach ($result->ok() ? $result->items() : [] as $item) {
            $loadId = $item['load_id'] ?? null;

            if (is_string($loadId)) {
                $rows[$loadId] = $item;
            }
        }

        $pagination = $result->ok() ? $result->pagination() : null;

        return new LengthAwarePaginator(
            $rows,
            $pagination['total'] ?? count($rows),
            $pagination['per_page'] ?? $perPage,
            $pagination['page'] ?? $page,
        );
    }

    /**
     * Today in Nairobi, as Y-m-d: the day KadiApi's date filters mean.
     */
    private static function today(): string
    {
        return CarbonImmutable::now(config('kadi.timezone'))->toDateString();
    }
}
