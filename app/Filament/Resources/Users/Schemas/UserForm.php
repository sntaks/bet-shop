<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => Str::lower($state)),
                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New password')
                    ->helperText(fn (string $operation): ?string => $operation === 'create' ? null : 'Leave blank to keep the current password.')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('role')
                    ->options(fn (): array => self::actorIsAdmin() ? RoleSeeder::LABELS : RoleSeeder::SHOP_ROLE_LABELS)
                    ->default(RoleSeeder::CASHIER)
                    ->required()
                    ->native(false)
                    ->live()
                    ->disabled(fn (?User $record): bool => self::isCurrentUser($record))
                    ->helperText(fn (?User $record): ?string => self::isCurrentUser($record) ? 'You cannot change your own role.' : null)
                    ->dehydrated(false),
                Select::make('shop_id')
                    ->label('Shop')
                    ->options(fn (): array => Shop::query()->orderBy('shop_no')->get()->mapWithKeys(fn (Shop $shop): array => [$shop->id => "{$shop->shop_no} · {$shop->name}"])->all())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::actorIsAdmin() && $get('role') !== RoleSeeder::ADMIN)
                    ->required(fn (Get $get): bool => $get('role') !== RoleSeeder::ADMIN)
                    ->disabled(fn (?User $record): bool => self::isCurrentUser($record)),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText(fn (?User $record): string => self::isCurrentUser($record)
                        ? 'You cannot deactivate yourself.'
                        : 'A deactivated staff member cannot log in.')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => self::isCurrentUser($record)),
            ]);
    }

    /**
     * Admins place anyone in any shop (or make admins); managers only add staff to their own
     * shop, as cashiers or managers.
     */
    public static function actorIsAdmin(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Nobody can demote, move or deactivate themselves, so a shop never loses its last manager
     * (or the platform its last admin) by accident.
     */
    public static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }
}
