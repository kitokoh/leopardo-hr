<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * #5714/#5709 — Prospect (lead) CRM client (tenant-scoped).
 *
 * `company_id` non nullable ; `source` et `status` sont des whitelists
 * contrôlées (Domain\Enums) — jamais de valeur libre.
 *
 * @property int $id
 * @property string $company_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $source
 * @property string $status
 * @property int|null $owner_id
 * @property string|null $notes
 * @property string|null $archived_at
 *
 * @mixin Builder<static>
 */
class CrmLead extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_leads';

    protected $fillable = [
        'company_id',
        'account_id',
        'first_name',
        'last_name',
        'company_name',
        'email',
        'phone',
        'title',
        'source',
        'status',
        'score',
        'tags',
        'owner_id',
        'notes',
        'converted_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'tags' => 'array',
        'converted_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
