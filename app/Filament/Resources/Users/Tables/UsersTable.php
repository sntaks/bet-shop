<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use Database\Seeders\RoleSeeder;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('shop.shop_no')
                    ->label('Shop')
                    ->fontFamily('mono')
                    ->placeholder('All shops (admin)')
                    ->visible(fn (): bool => UserForm::actorIsAdmin()),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleSeeder::LABELS[$state] ?? $state),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('shop')
                    ->relationship('shop', 'shop_no')
                    ->visible(fn (): bool => UserForm::actorIsAdmin()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
