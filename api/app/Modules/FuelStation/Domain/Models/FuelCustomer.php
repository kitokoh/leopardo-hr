<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Client fidélité FuelStation (CRM client tenant) — FUEL-016 (issue #5810).
 *
 * `external_id` UNIQUE par tenant → upsert idempotent depuis un POS/ERP.
 * `marketing_consent` : opt-in RGPD explicite. phone/email/metadata chiffrés
 * (casts encrypted). Jamais de lecture du CRM commercial Leopardo.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $external_id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $email
 * @property bool $marketing_consent
 * @property int $loyalty_points
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelCustomer extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_customers';

    protected $fillable = [
        'company_id',
        'station_id',
        'external_id',
        'full_name',
        'phone',
        'email',
        'marketing_consent',
        'loyalty_points',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'marketing_consent' => 'boolean',
            'loyalty_points' => 'integer',
            'metadata' => 'encrypted:array',
        ];
    }
}
