<?php

namespace App\Filament\Pages;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\ShopSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * This desk's settings, for managers.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(ShopSetting::current()->only(['low_float_threshold', 'idle_lock_minutes']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('low_float_threshold')
                    ->label('Low float warning')
                    ->helperText('The dashboard warns when the float is below this.')
                    ->prefix('KES')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(10_000_000)
                    ->required(),
                TextInput::make('idle_lock_minutes')
                    ->label('Lock the screen after')
                    ->helperText('Minutes without activity before the cashier must enter their password again.')
                    ->suffix('minutes')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(120)
                    ->required(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = ShopSetting::current();
        $settings->fill($this->form->getState());

        if ($settings->isDirty()) {
            $changes = collect($settings->getDirty())
                ->map(fn (mixed $value, string $key): array => ['from' => $settings->getOriginal($key), 'to' => $value])
                ->all();

            $settings->save();

            ActivityLog::record(ActivityAction::SettingsUpdated, ['details' => $changes]);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
