<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->options(RoleSeeder::LABELS)
                    ->default(RoleSeeder::CASHIER)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?User $record): bool => self::isCurrentUser($record))
                    ->helperText(fn (?User $record): ?string => self::isCurrentUser($record) ? 'You cannot change your own role.' : null)
                    ->dehydrated(false),
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
     * A manager cannot demote or deactivate themselves, so the shop never loses its last manager
     * by accident.
     */
    public static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }
}
