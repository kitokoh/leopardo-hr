<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Modules\Library\Domain\Enums\LibraryReportReason;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Signalement de contenu (procédure de retrait, droits d'auteur).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $resource_id
 * @property LibraryReportReason $reason
 * @property string|null $details
 * @property string|null $reporter_email
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryReport extends Model
{
    use BelongsToCompany;

    protected $table = 'library_reports';

    protected $fillable = [
        'company_id',
        'resource_id',
        'reason',
        'details',
        'reporter_email',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => LibraryReportReason::class,
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
