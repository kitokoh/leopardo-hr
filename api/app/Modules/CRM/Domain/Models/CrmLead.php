<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lead commercial d'un tenant (CRM client V0, issue #5709).
 *
 * Distinct de `MarketingLead` (lead d'acquisition de la PLATEFORME, schéma
 * public) : un lead CRM client appartient à un tenant (`company_id`) et vit
 * dans le schéma tenant.
 *
 * Les statuts et priorités sont bornés par CHECK en base ; `owner_id` pointe
 * vers un employé du MÊME tenant (validité contrôlée au niveau Policy).
 *
 * @property int $id
 * @property string $company_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property string|null $source
 * @property string $status
 * @property string $priority
 * @property int|null $owner_id
 * @property string|null $notes
 * @property Carbon|null $converted_at
 * @property int|null $converted_opportunity_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmLead extends Model
{
    use BelongsToCompany;

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_CONVERTED,
        self::STATUS_REJECTED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
    ];

    protected $table = 'crm_leads';

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'source',
        'status',
        'priority',
        'owner_id',
        'notes',
        'converted_at',
        'converted_opportunity_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function markConverted(int $opportunityId): void
    {
        $this->status = self::STATUS_CONVERTED;
        $this->converted_opportunity_id = $opportunityId;
        $this->converted_at = now();
    }
}
