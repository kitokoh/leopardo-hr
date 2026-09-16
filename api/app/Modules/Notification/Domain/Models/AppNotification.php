<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DÉPRÉCIÉ (#7481) — n'écrivez plus ici.
 *
 * Le store de notification in-app est **`notifications`** (`Notification`),
 * celui que sert `GET /notifications` (web, mobile, assistant) et qui porte
 * les préférences par employé, les heures calmes, les quotas et l'audit
 * (`CommunicationEvent`), écrit par `CommunicationService`.
 *
 * Cette table reste en place (aucune migration destructive) mais **plus aucun
 * code applicatif n'y écrit ni n'y lit** : `NotificationDispatcher`,
 * `SendNotification`, `MarkNotificationsRead` et l'assistant utilisent
 * désormais le store canonique. Une double écriture rendrait de nouveau une
 * notification invisible à la boîte de réception de l'utilisateur.
 *
 * In-app notification entity.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array $data
 * @property bool $read
 * @property string|null $read_at
 *
 * @mixin Builder<static>
 */
class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read',
        'read_at',
        'action_url',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        // #2436 — `user_id` stocke l'id d'un EMPLOYÉ tenant (le dispatcher est
        // appelé avec $submitter->id d'un Employee), pas un id de public.users :
        // la relation pointe donc vers Employee, jamais vers User.
        return $this->belongsTo(Employee::class);
    }
}
