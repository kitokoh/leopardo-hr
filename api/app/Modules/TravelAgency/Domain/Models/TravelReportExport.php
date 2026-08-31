<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Historique des exports CSV de rapports (TRAVEL-505, issue #6075).
 *
 * Une requête figée = un fichier (idempotence par `request_hash` unique
 * par tenant). L'URL signée est générée à la volée et expire (30 min) ;
 * l'historique est borné (prune au-delà de 50 lignes par tenant).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $report_type
 * @property string $request_hash
 * @property array<string, mixed> $filters
 * @property string $storage_path
 * @property string $mime_type
 * @property int $row_count
 * @property int|null $generated_by_user_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelReportExport extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'report_type',
        'request_hash',
        'filters',
        'storage_path',
        'mime_type',
        'row_count',
        'generated_by_user_id',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'row_count' => 'integer',
        'expires_at' => 'datetime',
    ];
}
