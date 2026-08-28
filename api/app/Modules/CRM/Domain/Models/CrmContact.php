<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contact CRM client (personne rattachée à un compte) — Issue #5708
 * (CRM-V0-04).
 *
 * Un contact appartient TOUJOURS à un compte du MÊME tenant (FK composite
 * `(account_id, company_id)` en base). Au plus un contact `is_primary` par
 * compte (index unique partiel).
 *
 * PII : `phone` chiffré au repos (cast `encrypted`) ; `email` en clair en
 * V0. Stratégie HMAC/RGPD portée par l'issue #5713 (CRM-V0-09).
 *
 * @property int $id
 * @property string $company_id
 * @property int $account_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property bool $is_primary
 * @property string $status
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CrmAccount $account
 *
 * @mixin Builder<static>
 */
class CrmContact extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'crm_contacts';

    protected $fillable = [
        'company_id',
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'is_primary',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'is_primary' => 'boolean',
        // PII — chiffré au repos (RGPD).
        'phone' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];

    /** @return BelongsTo<CrmAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
