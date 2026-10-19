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
 * PA2-COMM-002 — A single message inside a {@see ConversationThread}.
 *
 * At most one attachment is allowed per message ("pieces jointes
 * limitees" acceptance criterion); the attachment is stored on the
 * private `local` disk and only ever served back to the two thread
 * participants through the API, never a public URL.
 *
 * This model intentionally has no `updated_at` column. Since #8747 an
 * author (or a group admin) may edit or delete a message, both recorded in
 * explicit columns (`edited_at`, `deleted_at`) rather than in a global
 * `updated_at`: a message is never silently rewritten — the fact that it was
 * edited stays observable.
 *
 * Deletion is **logical** (`deleted_at` tombstone): the row keeps its `seq`
 * so the ordering of the thread and the replies pointing at it stay
 * consistent. The body and the attachment are wiped at deletion time.
 *
 * @property int $id
 * @property string $company_id
 * @property int $conversation_thread_id
 * @property int $author_id
 * @property string $body
 * @property int|null $seq
 * @property string|null $client_message_id
 * @property int|null $reply_to_message_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by_id
 * @property string|null $attachment_path
 * @property string|null $attachment_original_name
 * @property string|null $attachment_mime_type
 * @property int|null $attachment_size
 * @property string|null $attachment_disk
 * @property Carbon $created_at
 * @property-read ConversationThread|null $thread
 * @property-read Employee|null $author
 * @property-read ConversationMessage|null $replyTo
 *
 * @mixin Builder<static>
 */
class ConversationMessage extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'conversation_thread_id',
        'author_id',
        'body',
        'seq',
        'client_message_id',
        'reply_to_message_id',
        'edited_at',
        'deleted_at',
        'deleted_by_id',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'attachment_disk',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'seq' => 'integer',
        'reply_to_message_id' => 'integer',
        'deleted_by_id' => 'integer',
    ];

    /** @return BelongsTo<ConversationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ConversationThread::class, 'conversation_thread_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    /**
     * #8747 — message du même fil auquel celui-ci répond.
     *
     * @return BelongsTo<ConversationMessage, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /**
     * #8747 — suppression logique : la ligne survit pour préserver l'ordre
     * (`seq`) et les réponses, mais son contenu n'est plus exposé.
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * #8747 — corps exposable au client : vide pour un message supprimé.
     * Le contrôle vit ici, donc aucune surface (API, UI, export) ne peut
     * oublier de masquer le contenu d'un message supprimé.
     */
    public function visibleBody(): string
    {
        return $this->isDeleted() ? '' : $this->body;
    }

    /**
     * #8748 — disque de stockage effectif de la pièce jointe. Les messages
     * écrits avant #8748 n'ont pas de colonne `attachment_disk` renseignée et
     * ont été stockés sur `local` : le repli est donc explicite et documenté,
     * jamais implicite.
     */
    public function attachmentDisk(): string
    {
        return $this->attachment_disk !== null && $this->attachment_disk !== ''
            ? $this->attachment_disk
            : 'local';
    }

    /**
     * #8747 — scope des messages visibles (non supprimés).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }
}
