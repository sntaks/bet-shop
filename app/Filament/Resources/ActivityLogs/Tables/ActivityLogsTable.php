<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('subject_id')
                    ->label('Reference')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('player_no')
                    ->label('Player')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('KES')
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('api_code')
                    ->label('KadiApi')
                    ->formatStateUsing(fn (ActivityLog $record): string => trim(($record->http_status ?? '').' '.($record->api_code ?? '')))
                    ->placeholder('—'),
                TextColumn::make('details')
                    ->formatStateUsing(fn (ActivityLog $record): string => self::describeDetails($record->details))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(ActivityAction::class),
                SelectFilter::make('user')
                    ->label('Staff')
                    ->relationship('user', 'name'),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', self::startOfLocalDay($date)))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<', self::startOfLocalDay($date)->addDay()))),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    private static function describeDetails(?array $details): string
    {
        return collect($details ?? [])
            ->map(fn (mixed $value, string $key): string => $key.': '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)))
            ->implode(', ');
    }

    /**
     * The start of a day picked in Nairobi time, in the app's (database) timezone.
     */
    private static function startOfLocalDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('kadi.timezone'))->startOfDay()->setTimezone(config('app.timezone'));
    }
}
