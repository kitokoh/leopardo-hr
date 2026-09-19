<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Fil de discussion Gmail synchronise (BC-29 COMMUNICATION, R2 #7687).
 *
 * Minimisation : la ligne ne porte que des METADONNEES (identifiant Gmail,
 * sujet, snippet du dernier message, compteur, date) — le corps chiffre vit
 * sur `CommunicationMessage`, les pieces jointes ne sont jamais stockees.
 *
 * Idempotence resync : unique (company, integration, gmail_thread_id) —
 * l'ingestion met a jour, jamais ne duplique.
 *
 * `company_id` : hors `$fillable` (#7646), pose par le hook `creating` de
 * `BelongsToCompany` (les jobs de sync tournent sous EnsureTenantContext).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string $gmail_thread_id
 * @property string|null $subject
 * @property string|null $snippet
 * @property int $message_count
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationThread extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'communication_threads';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'gmail_thread_id',
        'subject',
        'snippet',
        'message_count',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }

    /**
     * @return HasMany<CommunicationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'thread_id');
    }
}
