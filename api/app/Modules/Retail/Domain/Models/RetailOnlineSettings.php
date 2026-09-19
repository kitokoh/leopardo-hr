<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opt-in boutique en ligne du module Retail (BC-17 RETAIL, #7807).
 *
 * UNE ligne par tenant (unique `company_id`). `enabled` est le kill switch
 * vendeur de la marketplace publique : defaut FALSE (fail-closed) — aucun
 * produit du tenant n'est visible tant que la boutique n'est pas activee.
 * Le `slug` est unique GLOBALEMENT (identifiant public cross-tenant de la
 * boutique, jamais le company_id). `location_id` (#7808) designe
 * l'emplacement de stock qui sert les commandes en ligne.
 *
 * @property int $id
 * @property string $company_id
 * @property string $slug
 * @property string $display_name
 * @property string|null $description
 * @property bool $enabled
 * @property int|null $location_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailOnlineSettings extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_online_settings';

    protected $fillable = [
        'company_id',
        'slug',
        'display_name',
        'description',
        'enabled',
        'location_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'location_id' => 'integer',
        ];
    }
}
