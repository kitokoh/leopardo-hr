<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConversationMessage> $messages
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'manager_id',
        'subject_type',
        'subject_id',
        'title',
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
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    /**
     * Whether the given employee is a participant (either party) of this
     * thread, used by the controller/policy to enforce tenant + membership
     * scoping before allowing reads/writes.
     */
    public function hasParticipant(Employee $employee): bool
    {
        return $this->employee_id === $employee->id || $this->manager_id === $employee->id;
    }

    /**
     * Marks the thread as read up to now for the given participant. No-op
     * for anyone who isn't a participant.
     */
    public function markReadFor(Employee $employee): void
    {
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
        if ($this->last_message_at === null) {
            return false;
        }

        $lastRead = $this->employee_id === $employee->id
            ? $this->employee_last_read_at
            : ($this->manager_id === $employee->id ? $this->manager_last_read_at : null);

        return $lastRead === null || $this->last_message_at->greaterThan($lastRead);
    }
}
