<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * État courant d'un consentement CRM — Issue #5722.
 *
 * Une ligne par (company_id, contact_id, canal, finalité) ; l'historique
 * immuable des consentements vit dans `audit_logs` (actions
 * consent.granted / consent.denied / consent.withdrawn).
 *
 * Isolation tenant : trait BelongsToCompany (scope global + auto-remplissage
 * company_id, fail-closed #3727) — un consentement d'un autre tenant est
 * introuvable (404), jamais visible.
 *
 * @property int $id
 * @property string $company_id
 * @property int $contact_id
 * @property string $channel
 * @property string $purpose
 * @property string $status
 * @property string $source
 * @property string|null $source_ref
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmConsent extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_consents';

    protected $fillable = [
        'company_id',
        'contact_id',
        'channel',
        'purpose',
        'status',
        'source',
        'source_ref',
        'granted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'contact_id' => 'integer',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];
}
