<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff, for managers (their own shop's only) and admins (every shop's, and other admins);
 * see UserPolicy. Staff are deactivated, never deleted, so the activity log keeps their names.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static ?string $slug = 'staff';

    protected static ?int $navigationSort = 91;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * A manager sees their own shop's staff only; another shop's staff member is a 404.
     *
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return User::query()
            ->with(['roles', 'shop'])
            ->when(! ($user?->isAdmin() ?? false), fn (Builder $query): Builder => $query->where('shop_id', $user->shop_id ?? 0));
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
