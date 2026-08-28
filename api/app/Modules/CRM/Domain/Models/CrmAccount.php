<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compte CRM client (entreprise/organisation tierce) — Issue #5708 (CRM-V0-04).
 *
 * Distinct du CRM commercial de la plateforme (super-admin) : un compte CRM
 * client appartient à un tenant (`company_id`) et vit dans le schéma tenant.
 *
 * PII : `phone` et `tax_id` sont chiffrés au repos (casts `encrypted`,
 * pattern `AccountingContact`) ; `email` reste en clair en V0 (recherche et
 * dédup #5718/#5719). La stratégie HMAC / registre RGPD est portée par
 * l'issue #5713 (CRM-V0-09).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $industry
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $tax_id
 * @property string $status
 * @property string $source
 * @property int|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CrmContact> $contacts
 *
 * @mixin Builder<static>
 */
class CrmAccount extends Model
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

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_WEB = 'web';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_OTHER = 'other';

    public const SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_IMPORT,
        self::SOURCE_WEB,
        self::SOURCE_REFERRAL,
        self::SOURCE_OTHER,
    ];

    protected $table = 'crm_accounts';

    protected $fillable = [
        'company_id',
        'name',
        'legal_name',
        'industry',
        'website',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_id',
        'status',
        'source',
        'owner_id',
        'metadata',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        // PII — chiffré au repos (RGPD / loi 18-07, pattern AccountingContact).
        'phone' => 'encrypted',
        'tax_id' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];

    /** @return HasMany<CrmContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'account_id');
    }
}
