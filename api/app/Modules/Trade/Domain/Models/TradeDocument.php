<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeDocumentStatus;
use App\Modules\Trade\Domain\Enums\TradeDocumentType;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Document commercial d'un dossier — BC-36 TRADE, TRADE-005/#8480.
 *
 * Stockage sur disque `private` par defaut : le telechargement passe par une
 * route autorisee, jamais par une URL publique devinable. La suppression est
 * LOGIQUE (`SoftDeletes`) pour conserver la tracabilite documentaire exigee
 * par une transaction internationale.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $trade_deal_id
 * @property int|null $trade_order_id
 * @property int|null $trade_quote_id
 * @property int|null $trade_rfq_id
 * @property TradeDocumentType $type
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property TradeDocumentStatus $status
 * @property int|null $uploaded_by_employee_id
 * @property Carbon|null $shared_at
 */
final class TradeDocument extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeDocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_documents';

    protected $fillable = [
        'trade_deal_id',
        'trade_order_id',
        'trade_quote_id',
        'trade_rfq_id',
        'type',
        'title',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'status',
        'uploaded_by_employee_id',
        'shared_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_deal_id' => 'integer',
            'trade_order_id' => 'integer',
            'trade_quote_id' => 'integer',
            'trade_rfq_id' => 'integer',
            'type' => TradeDocumentType::class,
            'status' => TradeDocumentStatus::class,
            'size_bytes' => 'integer',
            'uploaded_by_employee_id' => 'integer',
            'shared_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TradeDeal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(TradeDeal::class, 'trade_deal_id');
    }
}
