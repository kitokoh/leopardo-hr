<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Progression de lecture d'un employé sur une ressource (BC-40 LIBRARY).
 *
 * ⚠️ `company_id` et `employee_id` sont NON NULLABLES : il n'existe aucune
 * progression anonyme côté serveur. Le scope `BelongsToCompany` garantit
 * qu'une requête ne peut jamais lire la progression d'un autre tenant, et le
 * hook `creating` force `company_id` depuis le tenant actif (anti-spoof).
 *
 * La position est bornée à `[1, total_pages]` par le service applicatif, pas
 * par le modèle : le modèle reste une projection de données.
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property int $resource_id
 * @property int $page
 * @property int|null $total_pages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryReadingProgress extends Model
{
    use BelongsToCompany;

    protected $table = 'library_reading_progress';

    protected $fillable = [
        'company_id',
        'employee_id',
        'resource_id',
        'page',
        'total_pages',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'resource_id' => 'integer',
            'page' => 'integer',
            'total_pages' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LibraryResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(LibraryResource::class, 'resource_id');
    }
}
