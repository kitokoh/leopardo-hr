<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reglages de la boutique en ligne Leopardo Marche d'un tenant
 * (BC-17 RETAIL, #7807).
 *
 * Une seule ligne par tenant (`company_id` UNIQUE, create-or-update).
 * `enabled` est l'opt-in marketplace fail-closed : tant qu'il est false,
 * AUCUN produit du tenant n'apparait sur la vitrine publique, meme
 * `online_visible`. Devise d'affichage publique ISO 4217 (defaut DZD),
 * `version` pour verrou optimiste. Tenant-scoped, sans FK (conventions
 * migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property bool $enabled
 * @property string $shop_name
 * @property string|null $shop_description
 * @property string|null $city
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string $currency
 * @property int $version
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
        'enabled',
        'shop_name',
        'shop_description',
        'city',
        'contact_phone',
        'contact_email',
        'currency',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'version' => 'integer',
        ];
    }
}
