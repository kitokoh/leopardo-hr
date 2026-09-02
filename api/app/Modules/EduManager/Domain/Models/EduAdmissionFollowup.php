<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Relance marketing d'admission — Issue #5831 (EDU-015).
 *
 * Journal des campagnes de relance envoyées via le CRM/Marketing client sur
 * un dossier d'admission consentant. Idempotente par
 * (company_id, admission_id, campaign_code, channel) ; le consentement est
 * figé dans `consent_snapshot` au moment de l'envoi (RGPD). Aucune PII de
 * l'élève : seules des références et le code campagne/canal.
 *
 * @property int $id
 * @property string $company_id
 * @property int $admission_id
 * @property string $campaign_code
 * @property string $channel
 * @property string $status
 * @property array<string, mixed>|null $consent_snapshot
 * @property Carbon $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAdmissionFollowup extends Model
{
    use BelongsToCompany;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_MAIL = 'mail';

    public const CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
        self::CHANNEL_PHONE,
        self::CHANNEL_MAIL,
    ];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OPTED_OUT = 'opted_out';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_OPTED_OUT,
    ];

    protected $table = 'edu_admission_followups';

    protected $fillable = [
        'company_id',
        'admission_id',
        'campaign_code',
        'channel',
        'status',
        'consent_snapshot',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'consent_snapshot' => 'array',
        'sent_at' => 'datetime',
        'status' => 'string',
    ];

    /** @return BelongsTo<EduAdmission, $this> */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(EduAdmission::class, 'admission_id');
    }
}
