<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Configuration d'intégration d'une app de livraison — Issue #6227
 * (RESTO-806). Une ligne par (tenant, provider) ; le secret webhook est
 * chiffré au repos (jamais de secret en clair).
 *
 * @property int $id
 * @property string $company_id
 * @property string $provider
 * @property bool $enabled
 * @property string $external_restaurant_id
 * @property string|null $webhook_secret_encrypted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class RestaurantDeliveryAppConfig extends Model
{
    use BelongsToCompany;

    public const PROVIDER_UBER_EATS = 'uber_eats';

    public const PROVIDER_GLOVO = 'glovo';

    public const PROVIDERS = [
        self::PROVIDER_UBER_EATS,
        self::PROVIDER_GLOVO,
    ];

    protected $table = 'restaurant_delivery_app_configs';

    protected $fillable = [
        'company_id',
        'provider',
        'enabled',
        'external_restaurant_id',
        'webhook_secret_encrypted',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'webhook_secret_encrypted' => 'encrypted',
        'provider' => 'string',
    ];
}
