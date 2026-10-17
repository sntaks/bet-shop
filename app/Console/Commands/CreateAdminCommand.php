<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Registration is off, so the first platform admin is made here (seeding the roles first if they
 * are missing). Admins belong to no shop: in the panel they add shops and each shop's managers,
 * who add their own cashiers.
 */
#[Signature('staff:create-admin {--name= : The admin\'s name} {--email= : The email they log in with} {--password= : Their password (prompted for when left out)}')]
#[Description('Create a platform admin account')]
class CreateAdminCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $attributes = [
            'name' => $this->stringOption('name') ?? text('Name', required: true),
            'email' => Str::lower($this->stringOption('email') ?? text('Email', required: true)),
            'password' => $this->stringOption('password') ?? password('Password', required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $user = User::create([
            ...$validator->validated(),
            'shop_id' => null,
            'is_active' => true,
        ]);

        $user->assignRole(RoleSeeder::ADMIN);

        $this->components->info("Admin [{$user->email}] created.");

        return self::SUCCESS;
    }

    /**
     * A value option as given, or null when it was left out (so it is prompted for).
     */
    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
