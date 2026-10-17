<?php

namespace App\Filament\Pages;

use App\Services\DeskBlock;
use App\Services\KadiShopApi;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The full-page notice shown instead of every screen while KadiApi refuses this desk.
 */
class BlockedDesk extends SimplePage
{
    public function mount(): void
    {
        if (! DeskBlock::isBlocked()) {
            $this->redirect(Filament::getUrl());
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Actions::make([$this->checkAgainAction()])->fullWidth(),
                Actions::make([$this->logoutAction()])->alignment(Alignment::Center),
            ]);
    }

    public function checkAgainAction(): Action
    {
        return Action::make('checkAgain')
            ->label('Check again')
            ->action(function (KadiShopApi $api): void {
                $result = $api->me();

                if (! DeskBlock::isBlocked()) {
                    $this->redirect(Filament::getUrl());

                    return;
                }

                Notification::make()
                    ->title(match (true) {
                        $result->outcomeUnknown() => 'Kadi Kings could not be reached. Try again in a moment.',
                        $result->rateLimited() => 'Too many requests, wait a moment.',
                        default => 'Still blocked.',
                    })
                    ->danger()
                    ->send();
            });
    }

    public function logoutAction(): Action
    {
        return Action::make('logout')
            ->label('Log out')
            ->link()
            ->action(function (): void {
                Filament::auth()->logout();

                session()->invalidate();
                session()->regenerateToken();

                $this->redirect(Filament::getLoginUrl());
            });
    }

    public function getTitle(): string|Htmlable
    {
        return 'Desk blocked';
    }

    public function getHeading(): string|Htmlable
    {
        return DeskBlock::message(DeskBlock::reason() ?? DeskBlock::SHOP_NOT_ACTIVE);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Nothing can be loaded or paid out until Kadi Kings sorts this out.';
    }
}
