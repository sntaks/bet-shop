<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HandlesKadiResults;
use App\Models\ShopSetting;
use App\Services\KadiShopApi;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The shop at a glance, from GET /shop/me: the float (with a warning below the threshold in
 * Settings), how to buy more, and today's loads.
 */
class Dashboard extends BaseDashboard
{
    use HandlesKadiResults;

    protected string $view = 'filament.pages.dashboard';

    /**
     * The `data` of GET /shop/me, or null when it could not be fetched.
     *
     * @var array<string, mixed>|null
     */
    public ?array $shop = null;

    public int $lowFloatThreshold = ShopSetting::DEFAULT_LOW_FLOAT_THRESHOLD;

    public function mount(): void
    {
        $this->lowFloatThreshold = ShopSetting::current()->low_float_threshold;

        $this->loadShop();
    }

    public function loadShop(): void
    {
        $result = app(KadiShopApi::class)->me();

        if ($this->handledCommonOutcome($result)) {
            return;
        }

        $this->shop = $result->ok() ? $result->data : null;
    }

    public function isFloatLow(): bool
    {
        return $this->shop !== null && (float) ($this->shop['float_balance'] ?? 0) < $this->lowFloatThreshold;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->loadShop()),
        ];
    }
}
