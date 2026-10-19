<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * #8746 [BOS-CHAT] — Appartenance à un fil de discussion de type `group`.
 *
 * Avant ce modèle, l'appartenance à un `ConversationThread` était déduite de
 * deux colonnes (`employee_id` + `manager_id`) : un fil ne pouvait donc avoir
 * que DEUX participants, sans rôle ni état de lecture par personne.
 *
 * Ici :
 *   - `role` porte la matrice d'autorisation du groupe (`creator` > `admin` >
 *     `member`) — toujours évaluée côté serveur par
 *     {@see \App\Modules\Notification\Domain\Policies\ConversationParticipantPolicy} ;
 *   - `last_read_message_id` donne un accusé de lecture PAR PARTICIPANT (au
 *     lieu des deux colonnes `employee_last_read_at`/`manager_last_read_at`
 *     du fil binaire historique).
 *
 * Les fils `direct` (PA2-COMM-002) n'ont pas de ligne ici : leur appartenance
 * reste déduite des colonnes historiques. Les deux chemins sont supportés par
 * {@see ConversationThread::isGroup()}.
 *
 * @property int $id
 * @property string $company_id
 * @property int $conversation_thread_id
 * @property int $employee_id
 * @property string $role
 * @property int|null $last_read_message_id
 * @property Carbon $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConversationThread|null $thread
 * @property-read Employee|null $employee
 *
 * @mixin Builder<static>
 */
class ConversationParticipant extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const ROLE_CREATOR = 'creator';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /** @var array<int, string> Rôles acceptés — miroir de la colonne `role`. */
    public const ROLES = [self::ROLE_CREATOR, self::ROLE_ADMIN, self::ROLE_MEMBER];

    protected $fillable = [
        'company_id',
        'conversation_thread_id',
        'employee_id',
        'role',
        'last_read_message_id',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_read_message_id' => 'integer',
    ];

    /** @return BelongsTo<ConversationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ConversationThread::class, 'conversation_thread_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Le participant peut-il administrer le groupe (ajouter/retirer des
     * membres) ? Le créateur et les admins, jamais un simple membre.
     */
    public function canAdminister(): bool
    {
        return $this->role === self::ROLE_CREATOR || $this->role === self::ROLE_ADMIN;
    }

    public function isCreator(): bool
    {
        return $this->role === self::ROLE_CREATOR;
    }
}
