<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 */
class DeviceToken extends Model
{
    // Issue #7711 (suite #7646) — table `device_tokens` du schéma partagé
    // shared_tenants : company_id est l'unique frontière d'isolation. Les jobs
    // push tournent sous EnsureTenantContext (contexte tenant disponible).
    use BelongsToCompany;

    protected $fillable = [
        'employee_id',
        'token',
        'platform',
        'device_name',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
