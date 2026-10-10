<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Annuaire de routage des paiements (verticale FUNDRAISING — schema
 * PUBLIC, cross-tenant).
 *
 * (provider, provider_reference) → (company_id, contribution_reference) :
 * les webhooks providers arrivent hors contexte tenant ; cet annuaire les
 * route vers le schema du bon tenant (spec §4.3). Sert aussi au polling
 * public `GET /public/contributions/{reference}`.
 *
 * EXCEPTION TENANT-SCOPE canonique (#7999, liste
 * dev-hub/governance/tenant-scope-exceptions.json) : annuaire plateforme
 * de routage — `company_id` y est un pointeur, pas une clé d'isolation ;
 * BelongsToCompany est donc inapplicable.
 *
 * @property int $id
 * @property string $provider
 * @property string $provider_reference
 * @property string $company_id
 * @property string $contribution_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class FundraisingPaymentRoute extends Model
{
    protected $table = 'fundraising_payment_routes';

    protected $fillable = [
        'provider',
        'provider_reference',
        'company_id',
        'contribution_reference',
    ];
}
