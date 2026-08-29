<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Suppression d'adresse email (bounce/complaint/désabonnement) — #5726.
 *
 * L'adresse n'est JAMAIS stockée en clair : seul le hash SHA-256 est
 * conservé (recherche exacte par hash, aucune PII au repos).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $contact_id
 * @property string $email_hash
 * @property string $reason
 * @property string|null $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmEmailSuppression extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_email_suppressions';

    protected $fillable = [
        'company_id',
        'contact_id',
        'email_hash',
        'reason',
        'source',
    ];

    protected $casts = [
        'contact_id' => 'integer',
    ];
}
