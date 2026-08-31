<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des notifications voyageur (TRAVEL-415, issue #6067).
 *
 * Chaque tentative d'envoi est tracée (sent/skipped/failed) avec le motif
 * et un payload redacted — conformité RGPD et débogage sans PII inutile.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property int $event_id
 * @property string $event_type
 * @property string $contact_identifier
 * @property string $channel
 * @property string $status
 * @property string $reason
 * @property array<string, mixed> $payload_redacted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelNotificationLog extends Model
{
    use BelongsToCompany;

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'event_id',
        'event_type',
        'contact_identifier',
        'channel',
        'status',
        'reason',
        'payload_redacted',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'payload_redacted' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;
}
