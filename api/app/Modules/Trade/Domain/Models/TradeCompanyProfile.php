<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeCompanyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Profil commercial Trade (BC-36 TRADE, TRADE-002/#8477).
 *
 * 1:1 company. Porte les DIRECTIONS independantes (`import_enabled` ET
 * `export_enabled` peuvent etre vrais simultanement : aucune entreprise n'est
 * enfermee dans un role irreversible) et la portee domestique (grossiste B2B
 * local reutilisant le meme cycle commercial).
 *
 * Cree paresseusement et idempotemment par le service d'onboarding — un seul
 * profil par tenant (index unique `company_id`).
 *
 * @property int $id
 * @property string $company_id
 * @property bool $import_enabled
 * @property bool $export_enabled
 * @property bool $domestic_scope
 * @property string $hq_country
 * @property list<string>|null $markets_served
 * @property list<string>|null $product_categories
 * @property string $default_currency
 * @property string|null $default_incoterm
 * @property array<string, mixed>|null $capabilities
 * @property string|null $notes
 * @property Carbon|null $onboarding_completed_at
 */
final class TradeCompanyProfile extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeCompanyProfileFactory> */
    use HasFactory;

    protected $table = 'trade_company_profiles';

    protected $fillable = [
        'import_enabled',
        'export_enabled',
        'domestic_scope',
        'hq_country',
        'markets_served',
        'product_categories',
        'default_currency',
        'default_incoterm',
        'capabilities',
        'notes',
        'onboarding_completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'import_enabled' => 'boolean',
            'export_enabled' => 'boolean',
            'domestic_scope' => 'boolean',
            'markets_served' => 'array',
            'product_categories' => 'array',
            'capabilities' => 'array',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Le profil est-il complet (onboarding termine) ?
     */
    public function isOnboarded(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * Directions declarees — au moins une est requise pour operer.
     *
     * @return list<string>
     */
    public function directions(): array
    {
        $directions = [];

        if ($this->import_enabled) {
            $directions[] = 'import';
        }

        if ($this->export_enabled) {
            $directions[] = 'export';
        }

        if ($this->domestic_scope) {
            $directions[] = 'domestic';
        }

        return $directions;
    }
}
