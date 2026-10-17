<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopSetting>
 */
class ShopSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'low_float_threshold' => ShopSetting::DEFAULT_LOW_FLOAT_THRESHOLD,
            'idle_lock_minutes' => ShopSetting::DEFAULT_IDLE_LOCK_MINUTES,
        ];
    }
}
