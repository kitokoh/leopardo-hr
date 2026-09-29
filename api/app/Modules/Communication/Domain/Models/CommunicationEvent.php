<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal des événements de livraison multi-canal (app, push, email, SMS, WhatsApp).
 *
 * Classe canonique depuis l'ADR 0027 (BOS-025, #8219) : la frontière
 * Notification/Communication place le journal des canaux externes dans le
 * module Communication. La table `communication_events` est inchangée — la
 * convention Eloquent donne le même nom de table aux deux FQCN.
 *
 * Direction de dépendance : Notification → Communication autorisée (le
 * dispatcher de notifications écrit ce journal) ; Communication ne dépend
 * jamais de Notification — la relation historique `notification()` n'existe
 * donc que sur l'alias déprécié
 * {@see \App\Modules\Notification\Domain\Models\CommunicationEvent}.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property int|null $notification_id
 * @property string $event_name
 * @property string $channel
 * @property string $status
 * @property string|null $provider
 * @property string|null $template_key
 * @property array<mixed>|null $metadata
 * @property string|null $error_message
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationEvent extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'notification_id',
        'event_name',
        'channel',
        'status',
        'provider',
        'template_key',
        'metadata',
        'error_message',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
