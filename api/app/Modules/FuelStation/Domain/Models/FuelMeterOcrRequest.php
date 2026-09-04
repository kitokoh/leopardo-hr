<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Demande d'OCR de compteur FuelStation (AI-002, #6771).
 *
 * Une photo de compteur soumise par un employé est traitée de façon
 * ASYNCHRONE (queue durable) : la ligne est persistée AVANT le dispatch du
 * job — une perte de queue ne perd jamais la demande. Statuts :
 * queued → processing → succeeded | needs_review | rejected | failed.
 *
 * Garanties métier :
 *  - auto-enregistrement (`reading_id`) UNIQUEMENT si confiance ≥ seuil
 *    configuré ET aucune anomalie (unité, valeur décroissante) ;
 *  - tout doute → `needs_review` (revue humaine par un manager) ;
 *  - l'OCR ne clôture jamais seule une session de pompe/caisse ;
 *  - la photo (`photo_path`) est conservée tant que la demande n'est pas
 *    soldée (revue possible) — politique de purge hors périmètre AI-002.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int $pump_id
 * @property int $meter_id
 * @property int $requested_by_employee_id
 * @property int|null $shift_id
 * @property string $photo_path
 * @property string $status queued|processing|succeeded|needs_review|rejected|failed
 * @property int|null $extracted_value_minor
 * @property string|null $extracted_unit
 * @property float|null $confidence
 * @property list<string>|null $anomalies
 * @property string $correlation_id
 * @property string|null $model_version
 * @property int $attempts
 * @property string|null $error_code
 * @property int|null $reviewed_by_employee_id
 * @property string|null $review_decision accepted|rejected
 * @property Carbon|null $reviewed_at
 * @property int|null $reading_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterOcrRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_meter_ocr_requests';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_SUCCEEDED,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
    ];

    public const REVIEW_DECISION_ACCEPTED = 'accepted';

    public const REVIEW_DECISION_REJECTED = 'rejected';

    public const ANOMALY_LOW_CONFIDENCE = 'LOW_CONFIDENCE';

    public const ANOMALY_UNIT_MISMATCH = 'UNIT_MISMATCH';

    public const ANOMALY_DECREASING_READING = 'DECREASING_READING';

    protected $fillable = [
        'company_id',
        'station_id',
        'pump_id',
        'meter_id',
        'requested_by_employee_id',
        'shift_id',
        'photo_path',
        'status',
        'extracted_value_minor',
        'extracted_unit',
        'confidence',
        'anomalies',
        'correlation_id',
        'model_version',
        'attempts',
        'error_code',
        'reviewed_by_employee_id',
        'review_decision',
        'reviewed_at',
        'reading_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'pump_id' => 'integer',
            'meter_id' => 'integer',
            'requested_by_employee_id' => 'integer',
            'shift_id' => 'integer',
            'extracted_value_minor' => 'integer',
            'confidence' => 'float',
            'anomalies' => 'array',
            'attempts' => 'integer',
            'reviewed_by_employee_id' => 'integer',
            'reading_id' => 'integer',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isQueued(): bool
    {
        return $this->getAttribute('status') === self::STATUS_QUEUED;
    }

    public function isProcessing(): bool
    {
        return $this->getAttribute('status') === self::STATUS_PROCESSING;
    }

    public function isSucceeded(): bool
    {
        return $this->getAttribute('status') === self::STATUS_SUCCEEDED;
    }

    public function isNeedsReview(): bool
    {
        return $this->getAttribute('status') === self::STATUS_NEEDS_REVIEW;
    }

    public function isRejected(): bool
    {
        return $this->getAttribute('status') === self::STATUS_REJECTED;
    }

    public function isFailed(): bool
    {
        return $this->getAttribute('status') === self::STATUS_FAILED;
    }

    /** Une demande rejouable par le job de traitement (jamais deux fois en parallèle). */
    public function canBeProcessed(): bool
    {
        return $this->isQueued() || $this->isFailed();
    }
}
