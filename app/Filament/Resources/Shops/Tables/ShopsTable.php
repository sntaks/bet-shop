<?php

namespace App\Filament\Resources\Shops\Tables;

use App\Models\Shop;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('users'))
            ->columns([
                TextColumn::make('shop_no')
                    ->label('Shop')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key_hint')
                    ->label('Key')
                    ->fontFamily('mono')
                    ->state(fn (Shop $record): string => $record->key_hint),
                TextColumn::make('users_count')
                    ->label('Staff')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('shop_no')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
