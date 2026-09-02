<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Historique des exports CSV de rapports (TRAVEL-505, issue #6075).
 *
 * Une requête figée = un fichier (idempotence par `request_hash` unique
 * par tenant). L'URL signée est générée à la volée et expire (30 min) ;
 * l'historique est borné (prune au-delà de 50 lignes par tenant).
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
