<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Etape d'une sequence de relance (BC-29 COMMUNICATION, R4 #7689).
 *
 * `position` 1..3 (UNIQUE par regle) ; `delay_days` compte depuis le message
 * sortant sans reponse (etape 1) ou depuis l'envoi de la relance precedente
 * (etapes suivantes). `template_key` pointe vers un gabarit du
 * `EmailTemplateRegistry` (#7347) — contenu par defaut i18n FR/EN/AR/TR,
 * surcharge possible par locale dans l'admin, jamais de texte libre stocke
 * ici.
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $rule_id
 * @property int $position
 * @property int $delay_days
 * @property string $template_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationFollowUpStep extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'communication_follow_up_steps';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'rule_id',
        'position',
        'delay_days',
        'template_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommunicationFollowUpRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationFollowUpRule::class, 'rule_id');
    }
}
