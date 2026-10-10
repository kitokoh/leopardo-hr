<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Annuaire public des cagnottes (verticale FUNDRAISING — schema PUBLIC,
 * cross-tenant).
 *
 * slug court → company_id : permet le lien partageable façon GoFundMe
 * (`/public/fundraisers/{slug}`) alors que la donnée métier vit dans le
 * schema tenant. Écrit à la publication, supprimé/maj aux transitions
 * (une cagnotte `draft|paused|cancelled` n'a pas d'entrée → 404 public
 * anti-énumération, spec §5.1/§6).
 *
 * EXCEPTION TENANT-SCOPE canonique (#7999, liste
 * dev-hub/governance/tenant-scope-exceptions.json) : annuaire plateforme
 * de résolution — `company_id` y est un pointeur de routage, pas une clé
 * d'isolation ; BelongsToCompany est donc inapplicable.
 *
 * @property int $id
 * @property string $slug
 * @property string $company_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class FundraiserPublicLink extends Model
{
    protected $table = 'fundraiser_public_links';

    protected $fillable = [
        'slug',
        'company_id',
        'status',
    ];
}
