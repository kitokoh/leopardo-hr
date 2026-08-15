<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification entity.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array  $data
 * @property bool   $read
 * @property string|null $read_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
        'data'    => 'array',
        'read'    => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        // #2436 : user_id stocke un id d'EMPLOYÉ tenant (NotificationDispatcher,
        // NotifyTaxRateValidation, MarkNotificationsRead) — la relation pointait
        // vers public.users (User) où les ids ne correspondent jamais. Le canal
        // moderne écrit via Employee : la relation doit résoudre l'employé.
        return $this->belongsTo(Employee::class, 'user_id');
    }
}
