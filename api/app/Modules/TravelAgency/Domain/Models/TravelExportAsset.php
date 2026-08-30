<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelExportAssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-505 (#6075) — Asset d'export CSV d'un rapport travel.
 * Statut pending → generated | failed ; fichier privé, URL signée éphémère
 * générée à la lecture ; (tenant, idempotency_key) unique → rejeu = même
 * fichier.
 *
 * @property int $id
 * @property string $company_id
 * @property string $report_type
 * @property string $idempotency_key
 * @property string $status
 * @property Carbon|null $from_at
 * @property Carbon|null $to_at
 * @property string|null $file_path
 * @property Carbon|null $expires_at
 * @property string|null $error_redacted
 * @property int|null $created_by_user_id
 *
 * @mixin Builder<static>
 */
class TravelExportAsset extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelExportAssetFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    public const SIGNED_URL_TTL_MINUTES = 60;

    protected $table = 'travel_export_assets';

    protected $fillable = [
        'company_id',
        'report_type',
        'idempotency_key',
        'status',
        'from_at',
        'to_at',
        'file_path',
        'expires_at',
        'error_redacted',
        'created_by_user_id',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'from_at' => 'datetime',
        'to_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
