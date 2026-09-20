<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * #7863 (Encaissements) — encaissement enregistré MANUELLEMENT au local
 * (espèces / TPE au comptoir) : le tenant confirme un encaissement hors PSP
 * (montant, devise, mode, note, date effective).
 *
 * Isolation : `BelongsToCompany` (scope tenant fail-closed #3727) — un tenant
 * ne voit jamais les encaissements d'un autre.
 *
 * @property int $id
 * @property string $company_id
 * @property string $amount
 * @property string $currency
 * @property string $method
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $collected_at
 * @property int|null $created_by
 */
class BillingCollection extends Model
{
    use BelongsToCompany;

    /** Modes d'encaissement au local : espèces ou TPE au comptoir. */
    public const METHODS = ['cash', 'card_terminal'];

    protected $table = 'billing_collections';

    protected $fillable = [
        'company_id',
        'amount',
        'currency',
        'method',
        'note',
        'collected_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    /**
     * Représentation API de l'encaissement.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'note' => $this->note,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
