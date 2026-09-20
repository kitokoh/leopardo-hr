<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compteur mensuel PERSISTANT d'usage IA (#7764).
 *
 * Une ligne par (company, période `YYYY-MM`) — `used` compte les REQUÊTES IA
 * du mois (unité du quota de plan `config('ai.quotas')`), là où le ledger
 * `ai_credit_ledger` compte des TOKENS achetés. Remplace le compteur Cache
 * volatil d'`AIRateLimiter` (perdu à chaque redéploiement).
 *
 * @property int $id
 * @property string $company_id
 * @property string $period
 * @property int $used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AiUsageCounter extends Model
{
    use BelongsToCompany;

    protected $table = 'ai_usage_counters';

    protected $fillable = [
        'company_id',
        'period',
        'used',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
        ];
    }
}
