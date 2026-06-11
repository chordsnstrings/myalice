<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $plan
 * @property string $locale
 * @property string $timezone
 * @property string $currency
 * @property numeric-string $wallet_balance
 * @property string $billing_status
 * @property bool $csat_enabled
 */
class Workspace extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'plan',
        'locale',
        'timezone',
        'currency',
        'wallet_balance',
        'billing_status',
        'csat_enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'csat_enabled' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * All members (across the membership pivot), with their per-workspace role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('workspace_role')
            ->withTimestamps();
    }
}
