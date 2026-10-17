<?php

namespace App\Console\Commands;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Services\ShopKeyVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;

/**
 * Adds a shop from the command line, as the Shops page does: the key is asked for (never taken
 * as an argument, so it stays out of shell history) and checked with KadiApi before saving.
 */
#[Signature('shop:create {shop_no : The shop number in GMS, e.g. S101} {name : The shop\'s name}')]
#[Description('Add a shop with its KadiApi shop key')]
class CreateShopCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shopNo = $this->argument('shop_no');
        $name = $this->argument('name');

        $attributes = [
            'shop_no' => is_string($shopNo) ? strtoupper($shopNo) : '',
            'name' => is_string($name) ? $name : '',
        ];

        $validator = Validator::make($attributes, [
            'shop_no' => ['required', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique(Shop::class)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $apiKey = password('Shop API key', required: true);

        $problem = ShopKeyVerifier::problem($apiKey, $attributes['shop_no']);

        if ($problem !== null) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        $shop = Shop::create([...$attributes, 'api_key' => $apiKey, 'is_active' => true]);

        ActivityLog::record(ActivityAction::ShopCreated, [
            'shop_id' => $shop->id,
            'subject_id' => $shop->shop_no,
            'details' => ['name' => $shop->name, 'is_active' => true, 'key' => $shop->key_hint],
        ]);

        $this->components->info("Shop [{$shop->shop_no}] added. Add its manager under Staff.");

        return self::SUCCESS;
    }
}
