<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $provider
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property string|null $calendar_id
 * @property Carbon|null $token_expires_at
 * @property bool $sync_leaves
 * @property bool $sync_training
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 *
 * @mixin Builder<static>
 */
class CalendarConnection extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'provider',
        'access_token',
        'refresh_token',
        'calendar_id',
        'token_expires_at',
        'sync_leaves',
        'sync_training',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'sync_leaves' => 'boolean',
        'sync_training' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
