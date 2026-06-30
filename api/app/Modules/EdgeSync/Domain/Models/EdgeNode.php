<?php

namespace App\Modules\EdgeSync\Domain\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a Leopardo Edge node installed at a client site.
 *
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string $slug
 * @property string $site_address
 * @property string $status  active|inactive|suspended
 * @property string $mode    cloud|offline|hybrid
 * @property string $license_key
 * @property Carbon $license_expires_at
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_seen_at
 * @property string|null $local_ip
 * @property string|null $public_ip
 * @property string $edge_version
 * @property array<string,mixed> $capabilities
 * @property array<string,mixed> $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read \App\Models\Company|null $company
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class EdgeNode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'edge_nodes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id', 'name', 'slug', 'site_address',
        'status', 'mode', 'license_key', 'license_expires_at',
        'last_sync_at', 'last_seen_at', 'local_ip', 'public_ip',
        'edge_version', 'capabilities', 'metadata',
    ];

    protected $casts = [
        'license_expires_at' => 'datetime',
        'last_sync_at'       => 'datetime',
        'last_seen_at'       => 'datetime',
        'capabilities'       => 'array',
        'metadata'           => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'edge_node_id');
    }

    public function syncQueue(): HasMany
    {
        return $this->hasMany(SyncQueue::class, 'edge_node_id');
    }

    public function license(): HasMany
    {
        return $this->hasMany(EdgeLicense::class, 'edge_node_id');
    }

    public function isLicenseValid(): bool
    {
        return $this->license_expires_at?->isFuture() ?? false;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    public function needsLicenseRenewal(): bool
    {
        if (! $this->license_expires_at) {
            return true;
        }

        return $this->license_expires_at->diffInDays(now()) <= config('edge.license_renewal_warning_days', 7);
    }
}
