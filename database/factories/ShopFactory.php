<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_no' => 'S'.fake()->unique()->numberBetween(100, 9999),
            'name' => fake()->city().' Bet Shop',
            'api_key' => 'shop-key-'.Str::random(32),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the shop has been switched off in this app.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
