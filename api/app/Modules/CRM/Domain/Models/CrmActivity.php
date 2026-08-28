<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Modules\CRM\Domain\Enums\CrmActivityType;
use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Activité de la timeline CRM (append-only) — Issue #5710 (CRM-V0-06).
 *
 * Une activité est un événement immuable (note, appel, email, rendez-vous) :
 * l'API n'expose pas de route de modification (append-only), seule la
 * suppression par un manager du tenant est autorisée (Policy CRM-V0-07,
 * issue #5711). Chaque mutation (création/suppression) est tracée dans
 * `audit_logs` via le trait `Auditable` (#5439).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $subject
 * @property string $activity_type
 * @property string|null $description
 * @property string|null $related_type
 * @property int|null $related_id
 * @property int|null $owner_id
 * @property Carbon $happened_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmActivity extends Model
{
    use Auditable;
    use BelongsToCompany;

    protected $table = 'crm_activities';

    protected $fillable = [
        'company_id',
        'subject',
        'activity_type',
        'description',
        'related_type',
        'related_id',
        'owner_id',
        'happened_at',
        'metadata',
    ];

    protected $casts = [
        'related_id' => 'integer',
        'owner_id' => 'integer',
        'happened_at' => 'datetime',
        'metadata' => 'encrypted:array',
    ];

    /**
     * Filtrer la timeline sur une cible (lead, opportunity, contact, account).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForRelated(Builder $query, CrmRelatedType $type, int $relatedId): Builder
    {
        return $query->where('related_type', $type->value)
            ->where('related_id', $relatedId);
    }
}
