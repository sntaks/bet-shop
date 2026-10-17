<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who did what, at which shop, and what KadiApi answered. Never holds a pickup code, a password
 * or an API key. Managers read their own shop's entries; admins read every shop's.
 *
 * @property int $id
 * @property int|null $shop_id
 * @property int|null $user_id
 * @property ActivityAction $action
 * @property string|null $subject_id The KadiApi load_id or withdrawal_id, or a local id.
 * @property string|null $player_no
 * @property string|null $amount
 * @property int|null $http_status
 * @property string|null $api_code
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $details
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read Shop|null $shop
 * @property-read User|null $user
 */
#[Table(name: 'activity_log')]
#[Fillable(['shop_id', 'user_id', 'action', 'subject_id', 'player_no', 'amount', 'http_status', 'api_code', 'idempotency_key', 'details', 'ip_address'])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * Record an action by the logged-in user (or the given user) from this request, against
     * their shop (or the given shop).
     *
     * @param  array{shop_id?: int|null, user_id?: int|null, subject_id?: string|null, player_no?: string|null, amount?: int|float|string|null, http_status?: int|null, api_code?: string|null, idempotency_key?: string|null, details?: array<string, mixed>|null}  $attributes
     */
    public static function record(ActivityAction $action, array $attributes = []): self
    {
        return self::query()->create([
            'shop_id' => auth()->user()?->shop_id,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            ...$attributes,
            'action' => $action,
        ]);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'amount' => 'decimal:2',
            'http_status' => 'integer',
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
