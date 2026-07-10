<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Signed offline license per Edge node.
 * License payload is signed with a private key; Edge node verifies
 * using the embedded public key without needing a Cloud connection.
 *
 * @property string $id
 * @property string $company_id
 * @property string $edge_node_id
 * @property string $license_key   unique token
 * @property string $signed_payload  JWT-signed license blob
 * @property array<string,mixed> $allowed_features
 * @property int    $max_employees
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property Carbon|null $last_validated_at
 * @property string $validation_status  valid|expired|revoked|pending_renewal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read EdgeNode|null $edgeNode
 * @property-read \App\Core\Tenant\Domain\Models\Company|null $company
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class EdgeLicense extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'edge_licenses';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id', 'edge_node_id', 'license_key', 'signed_payload',
        'allowed_features', 'max_employees',
        'issued_at', 'expires_at', 'last_validated_at', 'validation_status',
    ];

    protected $casts = [
        'allowed_features'  => 'array',
        'issued_at'         => 'datetime',
        'expires_at'        => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function edgeNode(): BelongsTo
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function isValid(): bool
    {
        return $this->validation_status === 'valid'
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->validation_status === 'revoked';
    }

    public function needsRenewal(): bool
    {
        return $this->expires_at->diffInDays(now()) <= config('edge.license_renewal_warning_days', 7);
    }
}

