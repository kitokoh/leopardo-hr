<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Message mort (dead letter) d'un job asynchrone du module Delivery
 * (BC-26-D07, issue #6295).
 *
 * Après épuisement des tentatives d'un job (clôture lourde, export), le hook
 * `failed()` des jobs enregistre ici company_id + payload + erreur pour un
 * rejeu contrôlé via `php artisan delivery:replay-dlq` — l'idempotence
 * métier des jobs garantit qu'un rejeu ne produit jamais de doublon.
 *
 * @property int $id
 * @property string $company_id
 * @property string $job_class
 * @property array<string, mixed>|null $payload
 * @property string $queue
 * @property string|null $error
 * @property int $attempts
 * @property string $status
 *
 * @mixin Builder<static>
 */
class DeliveryDeadLetter extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_dead_letters';

    protected $fillable = [
        'company_id',
        'job_class',
        'payload',
        'queue',
        'error',
        'attempts',
        'status',
        'replayed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'replayed_at' => 'datetime',
    ];

    public function markReplayed(): void
    {
        $this->forceFill([
            'status' => 'replayed',
            'replayed_at' => now(),
        ])->save();
    }
}
