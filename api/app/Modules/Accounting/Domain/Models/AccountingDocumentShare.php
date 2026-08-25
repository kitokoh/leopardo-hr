<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Partage tokenisé d'un document comptable (issue #5225).
 *
 * Le contact client reçoit un lien sécurisé (token aléatoire + expiration)
 * limité à SON document — pattern CabinetShare (#1817), RGPD : aucun accès
 * au-delà du document partagé.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $document_id
 * @property string $share_token
 * @property string|null $shared_with_email
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountingDocument|null $document
 *
 * @mixin Builder<static>
 */
class AccountingDocumentShare extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_document_shares';

    protected $fillable = [
        'company_id',
        'document_id',
        'share_token',
        'shared_with_email',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }

    public function isExpired(?Carbon $asOf = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isBefore($asOf ?? now());
    }
}
