<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The activity log, read-only, for managers (ActivityLogPolicy).
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $slug = 'activity-log';

    protected static ?int $navigationSort = 92;

    /**
     * A manager reads their own shop's entries only; an admin reads every shop's.
     *
     * @return Builder<ActivityLog>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return ActivityLog::query()
            ->when(! ($user?->isAdmin() ?? false), fn (Builder $query): Builder => $query->where('shop_id', $user->shop_id ?? 0));
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
