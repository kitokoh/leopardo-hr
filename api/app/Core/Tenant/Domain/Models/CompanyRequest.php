<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $employee_id
 * @property int|null $user_id
 * @property string|null $company_name
 * @property string $sector
 * @property string $country
 * @property string $city
 * @property string|null $manager_name
 * @property string|null $manager_id_card
 * @property string|null $manager_phone
 * @property string|null $notes
 * @property string $email
 * @property string|null $phone
 * @property string $description
 * @property string $status
 * @property int|null $approved_company_id
 * @property string|null $admin_notes
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read string $source
 * @property-read string|null $note
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CompanyRequest extends Model
{
    protected $table = 'company_requests';

    protected $fillable = [
        'employee_id',
        'user_id',
        'company_name',
        'sector',
        'country',
        'city',
        'manager_name',
        'manager_id_card',
        'manager_phone',
        'notes',
        'email',
        'phone',
        'description',
        'status',
        'approved_company_id',
        'admin_notes',
        'reviewed_at',
        'verification_token',
        'verification_expires_at',
        'signup_payload',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'signup_payload' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function approvedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'approved_company_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * PA2-ADM-004: acquisition channel of this lead, resolved from the
     * structured `signup_payload.source` captured by the self-service
     * trial flow, falling back to a legacy `description` marker
     * ("... — source: X") and finally to a generic label for requests
     * created by the manager-initiated company request flow.
     */
    public function getSourceAttribute(): string
    {
        $payload = $this->signup_payload;
        if (is_array($payload) && ! empty($payload['source']) && is_string($payload['source'])) {
            return $payload['source'];
        }

        $description = (string) ($this->description ?? '');
        if (preg_match('/source:\s*([^\r\n]+)/i', $description, $matches) === 1) {
            return trim($matches[1]);
        }

        return $this->user_id !== null ? 'manager_request' : 'direct';
    }

    /**
     * PA2-ADM-004: short note shown on the CRM pipeline card — prefers the
     * admin's own note, falling back to the lead's own description/notes
     * so the platform team always has context even before reviewing.
     */
    public function getNoteAttribute(): ?string
    {
        return $this->admin_notes ?: ($this->notes ?: $this->description);
    }
}
