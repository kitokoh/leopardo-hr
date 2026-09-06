<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Site vitrine public d'un tenant (BC-27 SHOWCASE, #6865).
 *
 * Une seule vitrine par tenant (`company_id` unique) ; `slug` unique global —
 * la consultation publique se fait par `/vitrine/{slug}` (spec
 * SOLUTION_SITE_VITRINE.md §5). Statut string `draft|published` (enum PHP
 * côté code), `settings` JSON (variables : couleurs, logo_id…), thème
 * `theme` (3 thèmes v1 en V-THEMES #6868), `custom_domain` réservé phase 2.
 * Tenant-scoped (`company_id`), sans FK (conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property string $slug
 * @property CompanyShowcaseStatus $status
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
            'status' => CompanyShowcaseStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
