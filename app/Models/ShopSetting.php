<?php

namespace App\Models;

use Database\Factories\ShopSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * This desk's settings: a single row, created with the defaults the first time it is read.
 *
 * @property int $id
 * @property int $low_float_threshold Whole shillings; the dashboard warns when the float is below it.
 * @property int $idle_lock_minutes The screen locks after this many minutes without a request.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['low_float_threshold', 'idle_lock_minutes'])]
class ShopSetting extends Model
{
    /** @use HasFactory<ShopSettingFactory> */
    use HasFactory;

    public const int DEFAULT_LOW_FLOAT_THRESHOLD = 2000;

    public const int DEFAULT_IDLE_LOCK_MINUTES = 10;

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'low_float_threshold' => self::DEFAULT_LOW_FLOAT_THRESHOLD,
        'idle_lock_minutes' => self::DEFAULT_IDLE_LOCK_MINUTES,
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'low_float_threshold' => 'integer',
            'idle_lock_minutes' => 'integer',
        ];
    }
}
