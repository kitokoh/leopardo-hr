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
 * Politique de reponse assistee d'une boite pour UNE categorie (BC-29
 * COMMUNICATION, R5 #7690 — spec §3.5).
 *
 * Quatre politiques, choisies PAR L'UTILISATEUR pour SA boite :
 * - `off`     : defaut implicite (aucune ligne = off) — l'IA ne propose rien ;
 * - `draft`   : un brouillon est depose dans le Gmail de l'utilisateur ;
 * - `confirm` : la proposition entre dans la file Pending de l'app et
 *               n'est envoyee QU'APRES validation humaine explicite ;
 * - `auto`    : envoi direct sous garde-fous R4 — OPT-IN explicite,
 *               desactive par defaut, JAMAIS sur les categories
 *               finance/RH/juridique ({@see BLOCKED_AUTO_CATEGORIES},
 *               liste bloquee EN DUR, exigence spec).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string $category_key
 * @property string $policy
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationIntegration|null $integration
 *
 * @mixin Builder<static>
 */
class CommunicationReplyPolicy extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const POLICY_OFF = 'off';

    public const POLICY_DRAFT = 'draft';

    public const POLICY_CONFIRM = 'confirm';

    public const POLICY_AUTO = 'auto';

    public const POLICIES = [
        self::POLICY_OFF,
        self::POLICY_DRAFT,
        self::POLICY_CONFIRM,
        self::POLICY_AUTO,
    ];

    /**
     * Categories INTERDITES en mode `auto` (spec §3.5 : « jamais d'auto sur
     * les categories finance/RH/juridique — liste bloquee en dur »). La
     * liste est volontairement large (cles par defaut de la taxonomie R3 +
     * synonymes FR/EN qu'un tenant pourrait creer) et vit DANS LE CODE,
     * jamais en config : elle n'est pas contournable par environnement.
     */
    public const BLOCKED_AUTO_CATEGORIES = [
        'invoice',
        'finance',
        'billing',
        'payment',
        'payroll',
        'hr',
        'rh',
        'legal',
        'juridique',
        'compliance',
    ];

    protected $table = 'communication_reply_policies';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'category_key',
        'policy',
    ];

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }

    public static function isAutoBlockedCategory(string $categoryKey): bool
    {
        return in_array(mb_strtolower($categoryKey), self::BLOCKED_AUTO_CATEGORIES, true);
    }
}
