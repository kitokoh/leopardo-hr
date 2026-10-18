<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Evenement metier du dossier — BC-36 TRADE, TRADE-005/#8480.
 *
 * TIMELINE du dossier (`rfq.sent`, `quote.accepted`, `order.shipped`…). Chaque
 * evenement est ecrit dans la MEME transaction que la transition qu'il
 * decrit : l'historique ne peut pas mentir par desynchronisation.
 *
 * `employee_id` reste null pour un evenement SYSTEME (expiration, cascade) —
 * la distinction « humain » / « systeme » est ainsi toujours lisible.
 *
 * @property int $id
 * @property string $company_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property int|null $employee_id
 */
final class TradeEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'trade_events';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'type',
        'payload',
        'employee_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'payload' => 'array',
            'employee_id' => 'integer',
        ];
    }
}
