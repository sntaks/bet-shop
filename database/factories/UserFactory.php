<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is a cashier. Needs RoleSeeder to have run.
     */
    public function cashier(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(RoleSeeder::CASHIER));
    }

    /**
     * Indicate that the user is a manager. Needs RoleSeeder to have run.
     */
    public function manager(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(RoleSeeder::MANAGER));
    }

    /**
     * Indicate that the user has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
