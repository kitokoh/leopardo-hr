<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * #8750 [BOS-CHAT] — signalement d'un message ou d'un compte.
 *
 * Deux cibles possibles, jamais « aucune » (contrainte CHECK en base) :
 *  - `conversation_message_id` — un contenu précis, dans un fil auquel le
 *    signalant appartient (vérifié par le service) ;
 *  - `reported_employee_id` — un comportement, sans cible de message.
 *
 * Le traitement est réservé à un `manager` du MÊME tenant. Un signalement ne
 * donne JAMAIS accès au fil : le manager voit le message signalé s'il est déjà
 * participant, sinon il voit la raison et le statut, jamais le contenu d'une
 * conversation privée à laquelle il n'appartient pas.
 *
 * @property int $id
 * @property string $company_id
 * @property int $reporter_id
 * @property int|null $conversation_thread_id
 * @property int|null $conversation_message_id
 * @property int|null $reported_employee_id
 * @property string $reason
 * @property string|null $details
 * @property string $status
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property string|null $resolution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $reporter
 * @property-read Employee|null $reviewedBy
 *
 * @mixin Builder<static>
 */
class ConversationReport extends Model
{
    use BelongsToCompany;

    public const STATUS_OPEN = 'open';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_REVIEWED, self::STATUS_DISMISSED];

    /**
     * Motifs acceptés. Une liste fermée évite le champ libre qui rendrait le
     * tri et le traitement impossibles.
     *
     * @var list<string>
     */
    public const REASONS = [
        'harassment',
        'spam',
        'inappropriate_content',
        'threat',
        'impersonation',
        'other',
    ];

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'reporter_id',
        'conversation_thread_id',
        'conversation_message_id',
        'reported_employee_id',
        'reason',
        'details',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'resolution',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_id');
    }

    /** @return BelongsTo<ConversationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ConversationThread::class, 'conversation_thread_id');
    }

    /**
     * Le signalement est-il encore à traiter ?
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * #8750 — scope des signalements à traiter.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
