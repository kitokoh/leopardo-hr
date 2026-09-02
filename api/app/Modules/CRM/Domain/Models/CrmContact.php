<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5714/#5708 — Contact CRM client (tenant-scoped).
 *
 * `company_id` non nullable ; au plus UN contact primaire par compte
 * (contrainte DB partielle + logique d'application, spec module §4.3).
 * Les champs PII (email/phone) sont protégés par le module PII (#5713).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $account_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $title
 * @property string|null $job_title
 * @property string|null $owner_id
 * @property \App\Core\Auth\Domain\Models\Employee|null $owner
 * @property bool $is_primary
 * @property string|null $notes
 * @property string|null $archived_at
 *
 * @mixin Builder<static>
 */
class CrmContact extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'company_id',
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'title',
        'is_primary',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<CrmAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
