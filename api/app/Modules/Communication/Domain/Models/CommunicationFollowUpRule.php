<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Regle de relance automatique d'une boite connectee (BC-29 COMMUNICATION,
 * R4 #7689 — spec MODULE_COMMUNICATION_EMAIL_IA.md §3.4).
 *
 * La regle est PERSONNELLE : elle appartient a la boite (integration R1) de
 * son proprietaire — les relances partent de SON Gmail (scope `gmail.send`)
 * et personne d'autre ne la voit ni ne la modifie
 * (`CommunicationFollowUpRulePolicy`).
 *
 * La sequence (max 3 etapes, delais configurables) vit dans
 * `communication_follow_up_steps` ; les echeances materialisees dans
 * `communication_follow_ups` (deduplication : une relance par echeance).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string $name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationFollowUpRule extends Model
{
    use BelongsToCompany;
    use HasUuids;

    /**
     * Maximum d'etapes d'une sequence (spec §3.4 : « max 3 relances »).
     */
    public const MAX_STEPS = 3;

    protected $table = 'communication_follow_up_rules';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'name',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }

    /**
     * @return HasMany<CommunicationFollowUpStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CommunicationFollowUpStep::class, 'rule_id')->orderBy('position');
    }
}
