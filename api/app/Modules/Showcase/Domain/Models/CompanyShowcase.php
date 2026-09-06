<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Modules\Showcase\Domain\Enums\ShowcaseStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Site vitrine public d'un tenant (BC-27 SHOWCASE, #6865).
 *
 * Une seule vitrine par compagnie (`company_id` unique — creation 1-clic
 * US1) ; `slug` unique global = URL publique stable `/vitrine/{slug}` (US3).
 * `settings` JSON = variables de presentation (couleurs, logo_id...),
 * jamais de donnee interne/RH. `custom_domain` reserve phase 2.
 *
 * @property int $id
 * @property string $company_id
 * @property string $slug
 * @property ShowcaseStatus $status
 * @property string $theme
 * @property array<string, mixed>|null $settings
 * @property string|null $custom_domain
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class CompanyShowcase extends Model
{
    use BelongsToCompany;

    protected $table = 'company_showcases';

    protected $fillable = [
        'company_id',
        'slug',
        'status',
        'theme',
        'settings',
        'custom_domain',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShowcaseStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
