<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property string $provider
 * @property string $provider_profile_ref
 * @property array<int, string>|null $connected_platforms
 * @property string|null $display_name
 * @property string $status
 * @property string|null $last_error
 * @property Carbon|null $connected_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class SocialAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'provider',
        'provider_profile_ref',
        'connected_platforms',
        'display_name',
        'status',
        'last_error',
        'connected_at',
        'created_by',
    ];

    protected $hidden = [
        'provider_profile_ref',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider_profile_ref' => 'encrypted',
            'connected_platforms' => 'array',
            'connected_at' => 'datetime',
        ];
    }

    /** @return HasMany<SocialPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
