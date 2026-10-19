<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-002 — A discussion thread between an employee and their manager.
 *
 * Optionally anchored to a concrete subject (a payroll salary advance, an
 * attendance correction request, or an absence request) via the
 * `subject_type`/`subject_id` polymorphic pair, so both parties can discuss
 * a specific task without losing context. When `subject_type` is null, the
 * thread is a free-standing discussion.
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property int|null $manager_id
 * @property string $kind
 * @property int|null $created_by
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $title
 * @property string $status
 * @property int|null $last_message_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $employee_last_read_at
 * @property Carbon|null $manager_last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Employee|null $manager
 * @property-read Collection<int, ConversationMessage> $messages
 * @property-read Collection<int, ConversationParticipant> $participants
 *
 * @mixin Builder<static>
 */
class ConversationThread extends Model
{
    use BelongsToCompany;
    use HasFactory;

    /** @var array<int, string> Known subject types the discussion can be anchored to. */
    public const SUBJECT_TYPES = [
        'salary_advance',
        'attendance_correction',
        'absence',
        // #8746 [BOS-CHAT] — références typées vers un contexte métier de
        // Business OS. Le `subject_*` reste une RÉFÉRENCE documentaire : il ne
        // donne JAMAIS de droit d'accès (les contrôles d'accès vivent dans
        // ConversationService / ConversationParticipantPolicy).
        'order',
        'booking',
        'professional_space',
        'site',
    ];

    /** #8746 [BOS-CHAT] — fil binaire historique (employé ↔ manager). */
    public const KIND_DIRECT = 'direct';

    /** #8746 [BOS-CHAT] — fil à participants multiples (`conversation_participants`). */
    public const KIND_GROUP = 'group';

    protected $fillable = [
        'company_id',
        'employee_id',
        'manager_id',
        'subject_type',
        'subject_id',
        'title',
        'kind',
        'created_by',
        'status',
        'last_message_id',
        'last_message_at',
        'employee_last_read_at',
        'manager_last_read_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'employee_last_read_at' => 'datetime',
        'manager_last_read_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * #8746 [BOS-CHAT] — participants d'un fil `group`. Vide pour un fil
     * `direct`, dont l'appartenance reste portée par `employee_id`/`manager_id`.
     *
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_thread_id');
    }

    /**
     * #8746 — le fil est-il un groupe ?
     */
    public function isGroup(): bool
    {
        return $this->kind === self::KIND_GROUP;
    }

    /**
     * #8746 — participation effective, tous types de fil confondus.
     *
     * Délègue au participant chargé quand il existe ; sinon retombe sur la
     * règle binaire historique. Ne fait AUCUNE requête supplémentaire si la
     * relation `participants` n'est pas chargée (chemin `direct`).
     */
    public function participantFor(int $employeeId): ?ConversationParticipant
    {
        if (! $this->isGroup()) {
            return null;
        }

        /** @var ConversationParticipant|null $participant */
        $participant = $this->participants->firstWhere('employee_id', $employeeId);

        return $participant;
    }

    /**
     * Whether the given employee is a participant (either party) of this
     * thread, used by the controller/policy to enforce tenant + membership
     * scoping before allowing reads/writes.
     */
    public function hasParticipant(Employee $employee): bool
    {
        // #8746 — un fil `group` est régi par `conversation_participants`,
        // jamais par les deux colonnes binaires (qui restent NULL/indicatives).
        if ($this->isGroup()) {
            return $this->participantFor($employee->id) !== null;
        }

        return $this->employee_id === $employee->id || $this->manager_id === $employee->id;
    }

    /**
     * Marks the thread as read up to now for the given participant. No-op
     * for anyone who isn't a participant.
     */
    public function markReadFor(Employee $employee): void
    {
        // #8746 — un groupe porte l'état de lecture PAR PARTICIPANT
        // (`conversation_participants.last_read_message_id`), pas sur le fil :
        // deux colonnes ne peuvent pas représenter N membres.
        if ($this->isGroup()) {
            $this->loadMissing('participants');

            /** @var ConversationParticipant|null $participant */
            $participant = $this->participantFor($employee->id);

            if ($participant === null) {
                return;
            }

            if ($this->last_message_id !== null && $participant->last_read_message_id !== $this->last_message_id) {
                $participant->last_read_message_id = $this->last_message_id;
                $participant->save();
            }

            return;
        }

        if ($this->employee_id === $employee->id) {
            $this->employee_last_read_at = now();
            $this->save();

            return;
        }

        if ($this->manager_id === $employee->id) {
            $this->manager_last_read_at = now();
            $this->save();
        }
    }

    /**
     * Unread state for the given participant: true when the other party
     * posted a message after this participant's last read timestamp.
     */
    public function isUnreadFor(Employee $employee): bool
    {
        if ($this->last_message_at === null || $this->last_message_id === null) {
            return false;
        }

        // #8746 — comparaison par identifiant de message pour un groupe :
        // robuste aux messages de la même seconde, contrairement à un
        // horodatage comparé naïvement.
        if ($this->isGroup()) {
            $this->loadMissing('participants');

            /** @var ConversationParticipant|null $participant */
            $participant = $this->participantFor($employee->id);

            if ($participant === null) {
                return false;
            }

            return $participant->last_read_message_id === null
                || (int) $participant->last_read_message_id < (int) $this->last_message_id;
        }

        $lastRead = $this->employee_id === $employee->id
            ? $this->employee_last_read_at
            : ($this->manager_id === $employee->id ? $this->manager_last_read_at : null);

        return $lastRead === null || $this->last_message_at->greaterThan($lastRead);
    }
}
