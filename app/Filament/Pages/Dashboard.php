<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HandlesKadiResults;
use App\Filament\Resources\Shops\ShopResource;
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

    /**
     * Admins belong to no shop, so their home is the list of shops.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->shop_id !== null;
    }

    public function mount(): void
    {
        if (auth()->user()?->shop_id === null) {
            $this->redirect(ShopResource::getUrl());

            return;
        }

        $this->lowFloatThreshold = ShopSetting::forShop(auth()->user()->shop)->low_float_threshold;

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
