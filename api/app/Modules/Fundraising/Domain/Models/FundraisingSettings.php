<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Paramétrage tenant de la verticale FUNDRAISING (cagnottes solidaires).
 *
 * Une seule ligne par entreprise (`company_id` unique) — résolution via la
 * compagnie courante de la requête (pattern `AccountingSettings` #5232) :
 * aucune fuite cross-tenant possible par id d'URL.
 *
 * @property int $id
 * @property string $company_id
 * @property array<int, string> $enabled_methods
 * @property string|null $default_min_amount
 * @property string|null $default_max_amount
 * @property array<int, float|int>|null $default_suggested_amounts
 * @property bool $allow_anonymous
 * @property bool $allow_messages
 * @property bool $show_supporters
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FundraisingSettings extends Model
{
    use BelongsToCompany;

    protected $table = 'fundraising_settings';

    protected $fillable = [
        'company_id',
        'enabled_methods',
        'default_min_amount',
        'default_max_amount',
        'default_suggested_amounts',
        'allow_anonymous',
        'allow_messages',
        'show_supporters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled_methods' => 'array',
            'default_suggested_amounts' => 'array',
            'allow_anonymous' => 'boolean',
            'allow_messages' => 'boolean',
            'show_supporters' => 'boolean',
        ];
    }

    /**
     * Valeurs par défaut d'un tenant qui n'a encore jamais paramétré la
     * verticale : tous les moyens de paiement offerts, options publiques
     * ouvertes, montants délégués à la config (`fundraising.limits`).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled_methods' => array_map(
                static fn (ContributionMethod $method): string => $method->value,
                ContributionMethod::cases(),
            ),
            'default_min_amount' => null,
            'default_max_amount' => null,
            'default_suggested_amounts' => null,
            'allow_anonymous' => true,
            'allow_messages' => true,
            'show_supporters' => true,
        ];
    }

    public function isMethodEnabled(ContributionMethod $method): bool
    {
        return in_array($method->value, $this->enabled_methods ?? [], true);
    }
}
