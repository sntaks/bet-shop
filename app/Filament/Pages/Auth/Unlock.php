<?php

namespace App\Filament\Pages\Auth;

use App\Http\Middleware\LockIdleScreen;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The lock screen. Opening it locks the session (the idle timer sends the browser here, and a
 * cashier can lock on purpose from the user menu); only the same user's password unlocks it.
 * Anyone else switches user, which logs out.
 *
 * @property-read Schema $form
 */
class Unlock extends SimplePage
{
    use WithRateLimiting;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        LockIdleScreen::lock(session()->driver());

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->autocomplete('current-password')
                    ->autofocus()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('unlock')
                    ->footer([
                        Actions::make([
                            Action::make('unlock')
                                ->label('Unlock')
                                ->submit('unlock'),
                        ])->fullWidth(),
                    ]),
                Actions::make([$this->switchUserAction()])
                    ->alignment(Alignment::Center),
            ]);
    }

    public function unlock(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title("Too many attempts. Try again in {$exception->secondsUntilAvailable} seconds.")
                ->danger()
                ->send();

            return;
        }

        $password = $this->form->getState()['password'];
        $user = Filament::auth()->user();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages(['data.password' => 'That password is not right.']);
        }

        LockIdleScreen::unlock(session()->driver());

        $this->redirect(session()->pull('url.intended', Filament::getUrl()));
    }

    public function switchUserAction(): Action
    {
        return Action::make('switchUser')
            ->label('Switch user')
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
        return 'Screen locked';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Screen locked';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $name = Filament::auth()->user()?->name;

        return $name === null ? null : "Enter {$name}'s password to carry on.";
    }
}
