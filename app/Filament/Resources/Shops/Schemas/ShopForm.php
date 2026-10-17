<?php

namespace App\Filament\Resources\Shops\Schemas;

use App\Models\Shop;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('shop_no')
                    ->label('Shop number')
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'As in GMS, e.g. S101. It cannot change later.'
                        : 'Shop numbers never change.')
                    ->required()
                    ->regex('/^[A-Za-z0-9]{2,10}$/')
                    ->validationMessages(['regex' => 'Use 2 to 10 letters or digits.'])
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation !== 'create'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('api_key')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Shop API key' : 'New shop API key')
                    ->helperText(fn (?Shop $record): string => $record === null
                        ? 'Issued in GMS under Bet Shops. It is checked with KadiApi before saving and stored encrypted.'
                        : "Leave blank to keep the current key ({$record->key_hint}). A new key is checked with KadiApi before saving.")
                    ->password()
                    ->autocomplete('off')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Staff of a shop that is not active cannot log in.')
                    ->default(true),
            ]);
    }
}
