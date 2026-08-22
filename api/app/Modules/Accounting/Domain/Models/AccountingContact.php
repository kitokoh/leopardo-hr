<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tiers de facturation (client / fournisseur) — COMPTABILITE_CONCEPTION.md §4.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $type
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $tax_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $currency
 * @property string|null $payment_terms
 * @property string|null $language
 * @property string $source
 * @property int|null $marketing_lead_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AccountingDocument> $documents
 * @mixin Builder<static>
 */
class AccountingContact extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_contacts';

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address',
        'currency',
        'payment_terms',
        'language',
        'source',
        'marketing_lead_id',
        'metadata',
    ];

    protected $casts = [
        'marketing_lead_id' => 'integer',
        // NIF — donnée sensible chiffrée au repos (RGPD / loi 18-07).
        'tax_id' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];

    /** @return HasMany<AccountingDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AccountingDocument::class, 'contact_id');
    }
}
