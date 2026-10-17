<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A bet shop using this app. Its KadiApi shop key is stored encrypted (APP_KEY) and only ever
 * read by App\Services\KadiShopApi; the panel shows its last 4 characters at most.
 *
 * @property int $id
 * @property string $shop_no
 * @property string $name
 * @property string $api_key
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $key_hint
 * @property-read ShopSetting|null $settings
 */
#[Fillable(['shop_no', 'name', 'api_key', 'is_active'])]
#[Hidden(['api_key'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<ShopSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(ShopSetting::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Shop numbers are stored upper case, as KadiApi does.
     *
     * @return Attribute<string, string>
     */
    protected function shopNo(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper(trim($value)));
    }

    /**
     * The key's last 4 characters, to tell keys apart without showing them.
     *
     * @return Attribute<string, never>
     */
    protected function keyHint(): Attribute
    {
        return Attribute::make(get: fn (): string => '…'.substr($this->api_key, -4));
    }
}
